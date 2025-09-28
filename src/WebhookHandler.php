<?php

declare(strict_types=1);

namespace BenitoQuib\GitDeploy;

use BenitoQuib\GitDeploy\Auth\GitLabAuthenticator;
use BenitoQuib\GitDeploy\Auth\JwtAuthenticator;
use BenitoQuib\GitDeploy\Deployment\DeploymentManager;
use BenitoQuib\GitDeploy\Git\GitManager;
use BenitoQuib\GitDeploy\Notifications\TelegramNotifier;
use BenitoQuib\GitDeploy\Exceptions\GitDeployException;
use Exception;
use Throwable;

/**
 * Main webhook handler for GitDeploy
 */
class WebhookHandler
{
    private GitDeployConfig $config;
    private GitManager $gitManager;
    private TelegramNotifier $notifier;
    private DeploymentManager $deploymentManager;
    private JwtAuthenticator $jwtAuth;
    private GitLabAuthenticator $gitlabAuth;
    
    public function __construct(?GitDeployConfig $config = null)
    {
        $this->config = $config ?? GitDeployConfig::fromEnv();
        
        // Set timezone
        date_default_timezone_set($this->config->get('timezone'));
        
        // Initialize components
        $this->gitManager = new GitManager($this->config);
        $this->notifier = new TelegramNotifier($this->config->getTelegramConfig());
        $this->deploymentManager = new DeploymentManager($this->config, $this->gitManager);
        
        // Prepare JWT config with secret
        $jwtConfig = $this->config->getJwtConfig();
        $jwtConfig['secret'] = $this->config->getJwtSecret();
        $this->jwtAuth = new JwtAuthenticator($jwtConfig);
        
        $this->gitlabAuth = new GitLabAuthenticator($this->config->getJwtSecret());
        
        // Set JSON response header
        header('Content-Type: application/json');
    }
    
    /**
     * Handle incoming webhook request
     */
    public function handle(): void
    {
        try {
            // Authenticate request
            $this->authenticate();
            
            // Get request data
            $requestData = $this->getRequestData();
            $action = $this->determineAction($requestData);
            
            // Execute action
            $result = $this->executeAction($action, $requestData);
            
            // Generate new token for next request
            $result['next_token'] = $this->jwtAuth->generateToken();
            
            // Send response
            $this->sendResponse($result);
            
        } catch (GitDeployException $e) {
            $this->handleError($e, $e->getCode() ?: 400);
        } catch (Throwable $e) {
            $this->handleError($e, 500);
        }
    }
    
    private function authenticate(): void
    {
        // Check if it's a GitLab webhook
        if ($this->gitlabAuth->isGitLabWebhook()) {
            $this->gitlabAuth->validateGitLabWebhook();
            return;
        }
        
        // Otherwise, validate JWT token
        $this->jwtAuth->validateToken();
    }
    
    private function getRequestData(): array
    {
        $input = json_decode(file_get_contents("php://input"), true) ?? [];
        
        // Add server data for GitLab webhooks
        if ($this->gitlabAuth->isGitLabWebhook()) {
            $input['gitlab_event'] = $_SERVER['HTTP_X_GITLAB_EVENT'] ?? null;
            $input['is_gitlab_webhook'] = true;
        }
        
        return $input;
    }
    
    private function determineAction(array $requestData): string
    {
        // GitLab webhook always triggers pull
        if ($requestData['is_gitlab_webhook'] ?? false) {
            return 'pull';
        }
        
        // Get action from request data
        $action = $requestData['action'] ?? null;
        
        if (!$action) {
            throw new GitDeployException('Action is required', 400);
        }
        
        // Validate action
        $validActions = ['pull', 'reset', 'log', 'deploy', 'status'];
        if (!in_array($action, $validActions)) {
            throw new GitDeployException("Invalid action: {$action}. Valid actions: " . implode(', ', $validActions), 400);
        }
        
        return $action;
    }
    
    private function executeAction(string $action, array $requestData): array
    {
        $result = ['action' => $action];
        $isGitLabWebhook = $requestData['is_gitlab_webhook'] ?? false;
        
        switch ($action) {
            case 'pull':
                $result = $this->handlePull($isGitLabWebhook);
                break;
                
            case 'reset':
                $commitId = $requestData['commit_id'] ?? null;
                if (!$commitId) {
                    throw new GitDeployException('commit_id is required for reset action', 400);
                }
                $result = $this->handleReset($commitId);
                break;
                
            case 'log':
                $result = $this->handleLog();
                break;
                
            case 'deploy':
                $forceComposer = $requestData['force_composer'] ?? false;
                $result = $this->handleDeploy($forceComposer);
                break;
                
            case 'status':
                $result = $this->handleStatus();
                break;
        }
        
        return $result;
    }
    
    private function handlePull(bool $isGitLabWebhook = false): array
    {
        $result = ['action' => 'pull'];
        
        try {
            // Backup current commit if enabled
            if ($this->config->get('deployment.backup_commits')) {
                $this->deploymentManager->saveCurrentCommit();
            }
            
            // Execute git operations
            $stashResult = $this->gitManager->stashChanges();
            $pullResult = $this->gitManager->pull();
            
            $result['stash'] = $stashResult;
            $result['pull'] = 'Pull successful';
            
            // Execute deployment if enabled
            $deploymentMessage = "";
            if ($this->config->isDeploymentEnabled()) {
                try {
                    $deploymentResult = $this->deploymentManager->deploy();
                    $result['deployment'] = $deploymentResult;
                    
                    if ($deploymentResult['composer_changes']) {
                        $composerStatus = $deploymentResult['composer_install']['success'] ? "✅ Exitoso" : "❌ Falló";
                        $deploymentMessage = "\n\n*📦 Deployment Ejecutado:*\n" .
                            "Composer Install: `{$composerStatus}`\n" .
                            "Método de verificación: `{$deploymentResult['check_method']}`\n";
                        
                        if (!$deploymentResult['success']) {
                            $deploymentMessage .= "❌ *Error en deployment*";
                        }
                    }
                } catch (Exception $e) {
                    $result['deployment'] = [
                        'success' => false,
                        'error' => $e->getMessage(),
                        'critical' => false
                    ];
                    $deploymentMessage = "\n\n⚠️ *Deployment con errores (no crítico):* `" . addslashes($e->getMessage()) . "`";
                }
            }
            
            // Send Telegram notification
            if ($this->config->isTelegramEnabled()) {
                $this->sendPullNotification($result, $isGitLabWebhook, $deploymentMessage);
            }
            
        } catch (Exception $e) {
            // Notify error but don't stop execution
            if ($this->config->isTelegramEnabled()) {
                $this->notifier->sendErrorNotification($e, 'Pull Operation');
            }
            throw $e;
        }
        
        return $result;
    }
    
    private function handleReset(string $commitId): array
    {
        $result = $this->gitManager->resetToCommit($commitId);
        
        if ($this->config->isTelegramEnabled()) {
            $this->notifier->sendMessage(
                "*🔄 Git Reset Ejecutado*\n\n" .
                "*Commit:* `{$commitId}`\n" .
                "*Fecha/Hora:* `" . date('Y-m-d H:i:s') . "`"
            );
        }
        
        return ['action' => 'reset', 'commit_id' => $commitId, 'result' => $result];
    }
    
    private function handleLog(): array
    {
        $logData = $this->gitManager->getCommitLog();
        
        return [
            'action' => 'log',
            'data' => [
                'commits' => $logData['commits'],
                'branch' => $logData['branch']
            ]
        ];
    }
    
    private function handleDeploy(bool $forceComposer = false): array
    {
        $deployResult = $this->deploymentManager->deploy($forceComposer);
        
        if ($this->config->isTelegramEnabled()) {
            $status = $deployResult['success'] ? "✅ Exitoso" : "❌ Falló";
            $this->notifier->sendMessage(
                "*🔧 Deployment Manual Ejecutado*\n\n" .
                "*Estado:* `{$status}`\n" .
                "*Cambios en Composer:* `" . ($deployResult['composer_changes'] ? 'Sí' : 'No') . "`\n" .
                "*Forzado:* `" . ($forceComposer ? 'Sí' : 'No') . "`\n" .
                "*Fecha/Hora:* `" . $deployResult['deployment_time'] . "`"
            );
        }
        
        return [
            'action' => 'deploy',
            'deployment' => $deployResult,
            'forced' => $forceComposer
        ];
    }
    
    private function handleStatus(): array
    {
        $status = $this->gitManager->getStatus();
        
        return [
            'action' => 'status',
            'git_status' => $status,
            'config' => [
                'deployment_enabled' => $this->config->isDeploymentEnabled(),
                'telegram_enabled' => $this->config->isTelegramEnabled(),
                'project_root' => $this->config->getProjectRoot(),
                'current_branch' => $this->gitManager->getCurrentBranch()
            ]
        ];
    }
    
    private function sendPullNotification(array $result, bool $isGitLabWebhook, string $deploymentMessage): void
    {
        $domain = $_SERVER['HTTP_HOST'] ?? php_uname('n');
        $currentBranch = $this->gitManager->getCurrentBranch();
        $dateTime = date('Y-m-d H:i:s');
        $source = $isGitLabWebhook ? "GitLab Webhook" : "API Call";
        
        $stashOutput = is_array($result['stash']) ? implode("\n", $result['stash']) : (string) $result['stash'];
        $stashOutput = !empty($stashOutput) ? $stashOutput : "No hay cambios para guardar en stash.";
        
        $message = "*🚀 Nuevo Pull Ejecutado 🚀*\n\n" .
            "*Dominio:* `{$domain}`\n" .
            "*Rama:* `{$currentBranch}`\n" .
            "*Fecha/Hora:* `{$dateTime}`\n" .
            "*Origen:* `{$source}`\n\n" .
            "*Resultados:*\n" .
            "Stash:\n```\n" . htmlspecialchars($stashOutput) . "\n```\n" .
            "Pull: `✅ Exitoso`\n" .
            $deploymentMessage;
        
        $this->notifier->sendMessage($message);
    }
    
    private function sendResponse(array $data): void
    {
        echo json_encode($data, JSON_PRETTY_PRINT);
    }
    
    private function handleError(Throwable $e, int $httpCode): void
    {
        http_response_code($httpCode);
        
        $errorData = [
            'error' => $httpCode === 500 ? 'Internal Server Error' : 'Bad Request',
            'message' => $e->getMessage()
        ];
        
        // Send error notification if possible
        if ($this->config->isTelegramEnabled()) {
            $this->notifier->sendErrorNotification($e, 'WebhookHandler Error');
        }
        
        echo json_encode($errorData, JSON_PRETTY_PRINT);
        exit;
    }
}