<?php
/**
 * Test script to verify GitDeploy functionality
 * Run this script to test basic functionality before publishing
 */

require_once __DIR__ . '/../vendor/autoload.php';

use BenitoQuib\GitDeploy\GitDeployConfig;
use BenitoQuib\GitDeploy\Git\GitManager;
use BenitoQuib\GitDeploy\Notifications\TelegramNotifier;
use BenitoQuib\GitDeploy\Auth\JwtAuthenticator;

echo "=== GitDeploy Test Script ===\n\n";

try {
    // Test 1: Configuration
    echo "1. Testing configuration...\n";
    $config = GitDeployConfig::getInstance([
        'jwt_secret' => 'test-secret-key-for-testing-only',
        'git_binary' => 'C:\Program Files\Git\cmd\git.exe', // Windows path
        'project_root' => __DIR__ . '/../',
        'telegram' => [
            'bot_token' => null, // Not testing actual Telegram
            'chat_id' => null,
            'enabled' => false,
        ]
    ]);
    echo "   ✅ Configuration created successfully\n";
    echo "   - JWT Secret: " . substr($config->getJwtSecret(), 0, 10) . "...\n";
    echo "   - Git Binary: " . $config->getGitBinary() . "\n";
    echo "   - Project Root: " . $config->getProjectRoot() . "\n";
    
    // Test 2: JWT Authentication
    echo "\n2. Testing JWT authentication...\n";
    $jwtConfig = $config->getJwtConfig();
    $jwtConfig['secret'] = $config->getJwtSecret();
    $jwtAuth = new JwtAuthenticator($jwtConfig);
    
    $token = $jwtAuth->generateToken(['test' => true]);
    echo "   ✅ JWT token generated: " . substr($token, 0, 20) . "...\n";
    
    // Test 3: Git Manager (basic initialization)
    echo "\n3. Testing Git manager...\n";
    $gitManager = new GitManager($config);
    echo "   ✅ Git manager initialized\n";
    
    // Try to get current branch (this will work if we're in a git repo)
    try {
        $currentBranch = $gitManager->getCurrentBranch();
        echo "   ✅ Current branch: $currentBranch\n";
    } catch (Exception $e) {
        echo "   ⚠️  Could not get current branch (not in git repo): " . $e->getMessage() . "\n";
    }
    
    // Test 4: Telegram Notifier (disabled)
    echo "\n4. Testing Telegram notifier...\n";
    $telegramConfig = $config->getTelegramConfig();
    $notifier = new TelegramNotifier($telegramConfig);
    echo "   ✅ Telegram notifier initialized\n";
    echo "   - Enabled: " . ($notifier->isEnabled() ? 'Yes' : 'No') . "\n";
    
    // Test 5: Configuration methods
    echo "\n5. Testing configuration methods...\n";
    echo "   - Telegram enabled: " . ($config->isTelegramEnabled() ? 'Yes' : 'No') . "\n";
    echo "   - Deployment enabled: " . ($config->isDeploymentEnabled() ? 'Yes' : 'No') . "\n";
    echo "   - Nested config test: " . ($config->get('telegram.enabled', 'default') ?: 'false') . "\n";
    
    echo "\n=== ✅ All tests passed! GitDeploy is ready for use ===\n";
    
} catch (Exception $e) {
    echo "\n=== ❌ Test failed ===\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}

echo "\n🚀 Next steps:\n";
echo "1. Run: composer test (to run PHPUnit tests)\n";
echo "2. Run: composer analyse (to run PHPStan analysis)\n";
echo "3. Create your .env file using examples/.env.example\n";
echo "4. Set up your webhook endpoint using examples/webhook.example.php\n";
echo "5. Configure GitLab webhook to point to your endpoint\n";
echo "\n📚 See README.md for detailed documentation\n";