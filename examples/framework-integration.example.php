<?php
/**
 * Framework Integration Examples
 * 
 * Examples of how to integrate GitDeploy with different PHP frameworks
 */

// ============================================================================
// LARAVEL INTEGRATION
// ============================================================================

// 1. Laravel Controller
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use BenitoQuib\GitDeploy\WebhookHandler;
use BenitoQuib\GitDeploy\GitDeployConfig;

class DeploymentController extends Controller
{
    public function webhook(Request $request): JsonResponse
    {
        try {
            $config = GitDeployConfig::getInstance([
                'jwt_secret' => config('app.key'),
                'project_root' => base_path(),
                'git_binary' => config('deployment.git_binary', '/usr/bin/git'),
                'telegram' => [
                    'bot_token' => config('services.telegram.bot_token'),
                    'chat_id' => config('services.telegram.chat_id'),
                    'enabled' => config('services.telegram.enabled', false),
                ],
                'deployment' => [
                    'clear_cache' => true, // Laravel specific
                    'enabled' => config('deployment.enabled', true),
                ]
            ]);
            
            $handler = new WebhookHandler($config);
            
            // Capture output
            ob_start();
            $handler->handle();
            $output = ob_get_clean();
            
            return response()->json(json_decode($output, true));
            
        } catch (\Exception $e) {
            \Log::error('Deployment webhook error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Deployment failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

// 2. Laravel Artisan Command
namespace App\Console\Commands;

use Illuminate\Console\Command;
use BenitoQuib\GitDeploy\Git\GitManager;
use BenitoQuib\GitDeploy\Deployment\DeploymentManager;
use BenitoQuib\GitDeploy\GitDeployConfig;

class DeployCommand extends Command
{
    protected $signature = 'deploy {--force-composer : Force composer install}';
    protected $description = 'Execute manual deployment';
    
    public function handle()
    {
        $this->info('Starting deployment...');
        
        try {
            $config = GitDeployConfig::getInstance([
                'jwt_secret' => config('app.key'),
                'project_root' => base_path(),
                'deployment' => [
                    'clear_cache' => true,
                    'enabled' => true,
                ]
            ]);
            
            $gitManager = new GitManager($config);
            $deploymentManager = new DeploymentManager($config, $gitManager);
            
            // Execute deployment
            $result = $deploymentManager->deploy($this->option('force-composer'));
            
            if ($result['success']) {
                $this->info('✅ Deployment successful!');
                
                if ($result['composer_changes']) {
                    $this->line('📦 Composer packages updated');
                }
                
                $this->line("⏱️ Execution time: {$result['execution_time']}s");
            } else {
                $this->error('❌ Deployment failed: ' . $result['error']);
                return 1;
            }
            
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
}

// ============================================================================
// SYMFONY INTEGRATION
// ============================================================================

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use BenitoQuib\GitDeploy\WebhookHandler;
use BenitoQuib\GitDeploy\GitDeployConfig;

class DeploymentController extends AbstractController
{
    #[Route('/webhook/deploy', name: 'deployment_webhook', methods: ['POST'])]
    public function webhook(Request $request): JsonResponse
    {
        try {
            $config = GitDeployConfig::getInstance([
                'jwt_secret' => $this->getParameter('app.secret'),
                'project_root' => $this->getParameter('kernel.project_dir'),
                'telegram' => [
                    'bot_token' => $this->getParameter('telegram.bot_token'),
                    'chat_id' => $this->getParameter('telegram.chat_id'),
                ]
            ]);
            
            $handler = new WebhookHandler($config);
            
            ob_start();
            $handler->handle();
            $output = ob_get_clean();
            
            return new JsonResponse(json_decode($output, true));
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Deployment failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

// ============================================================================
// CODEIGNITER 4 INTEGRATION
// ============================================================================

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use BenitoQuib\GitDeploy\WebhookHandler;
use BenitoQuib\GitDeploy\GitDeployConfig;

class Deployment extends ResourceController
{
    public function webhook()
    {
        try {
            $config = GitDeployConfig::getInstance([
                'jwt_secret' => env('app.deploymentSecret', 'your-secret'),
                'project_root' => ROOTPATH,
                'telegram' => [
                    'bot_token' => env('telegram.botToken'),
                    'chat_id' => env('telegram.chatId'),
                ]
            ]);
            
            $handler = new WebhookHandler($config);
            
            ob_start();
            $handler->handle();
            $output = ob_get_clean();
            
            return $this->response->setJSON(json_decode($output, true));
            
        } catch (\Exception $e) {
            log_message('error', 'Deployment webhook error: ' . $e->getMessage());
            
            return $this->response
                ->setStatusCode(500)
                ->setJSON([
                    'error' => 'Deployment failed',
                    'message' => $e->getMessage()
                ]);
        }
    }
}

// ============================================================================
// PLAIN PHP INTEGRATION
// ============================================================================

// For plain PHP projects or custom frameworks
class PlainPhpDeployment
{
    public static function handleWebhook()
    {
        // Load configuration from your preferred method
        $config = [
            'jwt_secret' => $_ENV['JWT_SECRET'] ?? 'fallback-secret',
            'project_root' => __DIR__,
            'git_binary' => '/usr/bin/git',
            'telegram' => [
                'bot_token' => $_ENV['TELEGRAM_BOT_TOKEN'] ?? null,
                'chat_id' => $_ENV['TELEGRAM_CHAT_ID'] ?? null,
            ]
        ];
        
        try {
            $gitDeployConfig = GitDeployConfig::getInstance($config);
            $handler = new WebhookHandler($gitDeployConfig);
            $handler->handle();
            
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'error' => 'Deployment failed',
                'message' => $e->getMessage()
            ]);
        }
    }
}

// Usage in webhook.php
// PlainPhpDeployment::handleWebhook();

// ============================================================================
// WORDPRESS INTEGRATION (as a plugin)
// ============================================================================

// In your WordPress plugin
class GitDeployWordPressPlugin
{
    public function __construct()
    {
        add_action('init', [$this, 'handleWebhook']);
        add_action('admin_menu', [$this, 'addAdminMenu']);
    }
    
    public function handleWebhook()
    {
        if (isset($_GET['git-deploy-webhook']) && $_GET['git-deploy-webhook'] === '1') {
            try {
                $config = GitDeployConfig::getInstance([
                    'jwt_secret' => get_option('git_deploy_jwt_secret'),
                    'project_root' => ABSPATH,
                    'git_binary' => get_option('git_deploy_git_binary', '/usr/bin/git'),
                    'telegram' => [
                        'bot_token' => get_option('git_deploy_telegram_token'),
                        'chat_id' => get_option('git_deploy_telegram_chat'),
                    ]
                ]);
                
                $handler = new WebhookHandler($config);
                $handler->handle();
                
            } catch (\Exception $e) {
                wp_die(json_encode([
                    'error' => 'Deployment failed',
                    'message' => $e->getMessage()
                ]), '', ['response' => 500]);
            }
            
            exit;
        }
    }
    
    public function addAdminMenu()
    {
        add_options_page(
            'Git Deploy Settings',
            'Git Deploy',
            'manage_options',
            'git-deploy',
            [$this, 'settingsPage']
        );
    }
    
    public function settingsPage()
    {
        // Admin settings page for configuration
        echo '<div class="wrap">';
        echo '<h1>Git Deploy Settings</h1>';
        echo '<p>Webhook URL: ' . site_url('?git-deploy-webhook=1') . '</p>';
        // Add form for configuration options
        echo '</div>';
    }
}