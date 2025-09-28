<?php
/**
 * GitDeploy Programmatic Usage Example
 * 
 * This example shows how to use BenitoQuib\GitDeploy programmatically in your application
 */

require_once __DIR__ . '/../vendor/autoload.php';

use BenitoQuib\GitDeploy\GitDeployConfig;
use BenitoQuib\GitDeploy\WebhookHandler;
use BenitoQuib\GitDeploy\Git\GitManager;
use BenitoQuib\GitDeploy\Deployment\DeploymentManager;
use BenitoQuib\GitDeploy\Notifications\TelegramNotifier;

// Example 1: Basic webhook handling with configuration
function example1_basic_webhook()
{
    // Create configuration
    $config = GitDeployConfig::getInstance([
        'jwt_secret' => 'your-secret-key',
        'git_binary' => '/usr/bin/git',
        'project_root' => '/path/to/your/project',
        'telegram' => [
            'bot_token' => 'your-bot-token',
            'chat_id' => 'your-chat-id',
        ]
    ]);
    
    // Handle webhook
    $handler = new WebhookHandler($config);
    $handler->handle();
}

// Example 2: Manual deployment
function example2_manual_deployment()
{
    $config = GitDeployConfig::fromEnv();
    
    $gitManager = new GitManager($config);
    $deploymentManager = new DeploymentManager($config, $gitManager);
    
    try {
        // Execute deployment
        $result = $deploymentManager->deploy(true); // Force composer
        
        if ($result['success']) {
            echo "Deployment successful!\n";
            print_r($result);
        } else {
            echo "Deployment failed: " . $result['error'] . "\n";
        }
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

// Example 3: Git operations
function example3_git_operations()
{
    $config = GitDeployConfig::fromEnv();
    $gitManager = new GitManager($config);
    
    try {
        // Get current status
        $status = $gitManager->getStatus();
        echo "Current branch: " . $status['branch'] . "\n";
        echo "Current commit: " . $status['commit'] . "\n";
        echo "Working directory clean: " . ($status['clean'] ? 'Yes' : 'No') . "\n";
        
        // Get commit log
        $log = $gitManager->getCommitLog(5);
        echo "\nLast 5 commits:\n";
        foreach ($log['commits'] as $commit) {
            echo "- {$commit['hash']}: {$commit['message']} ({$commit['author_name']})\n";
        }
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

// Example 4: Telegram notifications
function example4_telegram_notifications()
{
    $telegramConfig = [
        'bot_token' => 'your-bot-token',
        'chat_id' => 'your-chat-id',
        'enabled' => true
    ];
    
    $notifier = new TelegramNotifier($telegramConfig);
    
    if ($notifier->isEnabled()) {
        // Test connection
        if ($notifier->testConnection()) {
            echo "Telegram connection successful!\n";
            
            // Send a test message
            $notifier->sendMessage("🚀 GitDeploy test message from PHP!");
            
            // Send formatted message
            $notifier->sendFormattedMessage('Deployment Status', [
                'status' => 'success',
                'branch' => 'main',
                'commit' => 'abc123',
                'execution_time' => 2.5
            ], '📊');
            
        } else {
            echo "Telegram connection failed!\n";
        }
    } else {
        echo "Telegram not configured\n";
    }
}

// Example 5: Laravel Integration
function example5_laravel_integration()
{
    // In a Laravel controller or artisan command
    
    $config = GitDeployConfig::getInstance([
        'jwt_secret' => config('app.key'),
        'project_root' => base_path(),
        'git_binary' => '/usr/bin/git',
        'telegram' => [
            'bot_token' => config('services.telegram.bot_token'),
            'chat_id' => config('services.telegram.chat_id'),
        ],
        'deployment' => [
            'clear_cache' => true,
            'fix_permissions' => false,
        ]
    ]);
    
    $gitManager = new GitManager($config);
    $deploymentManager = new DeploymentManager($config, $gitManager);
    
    // Execute pull and deployment
    try {
        $gitManager->pull();
        $result = $deploymentManager->deploy();
        
        // Log result
        \Log::info('Deployment executed', $result);
        
        return $result;
        
    } catch (Exception $e) {
        \Log::error('Deployment failed', ['error' => $e->getMessage()]);
        throw $e;
    }
}

// Uncomment to run examples
// example2_manual_deployment();
// example3_git_operations();
// example4_telegram_notifications();