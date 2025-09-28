<?php

declare(strict_types=1);

namespace BenitoQuib\GitDeploy\Notifications;

use BenitoQuib\GitDeploy\Exceptions\GitDeployException;
use Throwable;

/**
 * Telegram notification handler
 */
class TelegramNotifier
{
    private array $config;
    private string $apiUrl;
    
    public function __construct(array $config)
    {
        $this->config = $config;
        
        if (!empty($config['bot_token'])) {
            $this->apiUrl = "https://api.telegram.org/bot{$config['bot_token']}/sendMessage";
        }
    }
    
    /**
     * Send a message to Telegram
     */
    public function sendMessage(string $message, ?string $parseMode = 'Markdown'): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }
        
        $postData = [
            'chat_id' => $this->config['chat_id'],
            'text' => $message,
            'parse_mode' => $parseMode
        ];
        
        return $this->sendRequest($postData);
    }
    
    /**
     * Send error notification
     */
    public function sendErrorNotification(Throwable $exception, string $context = ''): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }
        
        $domain = $_SERVER['HTTP_HOST'] ?? php_uname('n');
        $dateTime = date('Y-m-d H:i:s');
        
        $message = "*❌ Error en GitDeploy ❌*\n\n" .
            "*Contexto:* `{$context}`\n" .
            "*Dominio:* `{$domain}`\n" .
            "*Fecha/Hora:* `{$dateTime}`\n" .
            "*Error:* `" . addslashes($exception->getMessage()) . "`\n" .
            "*Archivo:* `{$exception->getFile()}:{$exception->getLine()}`";
        
        // Add stack trace for debugging (truncated)
        $stackTrace = $exception->getTraceAsString();
        if (strlen($stackTrace) > 500) {
            $stackTrace = substr($stackTrace, 0, 500) . '...';
        }
        $message .= "\n*Stack Trace:*\n```\n" . addslashes($stackTrace) . "\n```";
        
        return $this->sendMessage($message);
    }
    
    /**
     * Send deployment notification
     */
    public function sendDeploymentNotification(array $deploymentResult, bool $isManual = false): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }
        
        $status = $deploymentResult['success'] ? "✅ Exitoso" : "❌ Falló";
        $type = $isManual ? "Manual" : "Automático";
        
        $message = "*🔧 Deployment {$type} Ejecutado*\n\n" .
            "*Estado:* `{$status}`\n" .
            "*Cambios en Composer:* `" . ($deploymentResult['composer_changes'] ? 'Sí' : 'No') . "`\n" .
            "*Fecha/Hora:* `{$deploymentResult['deployment_time']}`";
        
        if (isset($deploymentResult['composer_install'])) {
            $composerStatus = $deploymentResult['composer_install']['success'] ? "✅ Exitoso" : "❌ Falló";
            $message .= "\n*Composer Install:* `{$composerStatus}`";
        }
        
        if (!$deploymentResult['success'] && isset($deploymentResult['error'])) {
            $message .= "\n*Error:* `" . addslashes($deploymentResult['error']) . "`";
        }
        
        return $this->sendMessage($message);
    }
    
    /**
     * Send pull notification
     */
    public function sendPullNotification(array $pullResult, string $source = 'API Call'): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }
        
        $domain = $_SERVER['HTTP_HOST'] ?? php_uname('n');
        $dateTime = date('Y-m-d H:i:s');
        
        $stashOutput = '';
        if (isset($pullResult['stash'])) {
            $stash = is_array($pullResult['stash']) ? implode("\n", $pullResult['stash']) : (string) $pullResult['stash'];
            $stashOutput = !empty($stash) ? $stash : "No hay cambios para guardar en stash.";
        }
        
        $message = "*🚀 Nuevo Pull Ejecutado 🚀*\n\n" .
            "*Dominio:* `{$domain}`\n" .
            "*Fecha/Hora:* `{$dateTime}`\n" .
            "*Origen:* `{$source}`\n\n" .
            "*Resultados:*\n" .
            "Pull: `✅ Exitoso`\n";
        
        if (!empty($stashOutput)) {
            $message .= "Stash:\n```\n" . htmlspecialchars($stashOutput) . "\n```\n";
        }
        
        return $this->sendMessage($message);
    }
    
    /**
     * Send custom notification with formatting
     */
    public function sendFormattedMessage(string $title, array $data, string $icon = '📝'): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }
        
        $message = "*{$icon} {$title}*\n\n";
        
        foreach ($data as $key => $value) {
            $key = ucfirst(str_replace('_', ' ', $key));
            
            if (is_array($value)) {
                $value = implode(', ', $value);
            } elseif (is_bool($value)) {
                $value = $value ? 'Sí' : 'No';
            }
            
            $message .= "*{$key}:* `{$value}`\n";
        }
        
        return $this->sendMessage($message);
    }
    
    /**
     * Check if Telegram notifications are enabled and configured
     */
    public function isEnabled(): bool
    {
        return isset($this->config['enabled']) && 
               $this->config['enabled'] && 
               !empty($this->config['bot_token']) && 
               !empty($this->config['chat_id']);
    }
    
    /**
     * Send HTTP request to Telegram API
     */
    private function sendRequest(array $postData): bool
    {
        if (!isset($this->apiUrl)) {
            return false;
        }
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->apiUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'GitDeploy/1.0'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            error_log("GitDeploy Telegram cURL error: {$curlError}");
            return false;
        }
        
        if ($httpCode !== 200) {
            error_log("GitDeploy Telegram API error (HTTP {$httpCode}): {$response}");
            return false;
        }
        
        $responseData = json_decode($response, true);
        
        if (!isset($responseData['ok']) || $responseData['ok'] !== true) {
            error_log("GitDeploy Telegram API response error: {$response}");
            return false;
        }
        
        return true;
    }
    
    /**
     * Get Telegram bot info
     */
    public function getBotInfo(): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }
        
        $url = "https://api.telegram.org/bot{$this->config['bot_token']}/getMe";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return $data['result'] ?? null;
        }
        
        return null;
    }
    
    /**
     * Test connection to Telegram
     */
    public function testConnection(): bool
    {
        return $this->getBotInfo() !== null;
    }
}