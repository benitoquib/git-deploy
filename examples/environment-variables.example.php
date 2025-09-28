<?php
/**
 * Example: Environment Variables with Prefix
 * 
 * This example demonstrates the new prefixed environment variables system
 * and backward compatibility features.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use BenitoQuib\GitDeploy\GitDeployConfig;

echo "=== GitDeploy Environment Variables Demo ===\n\n";

// Example 1: Using new prefixed variables
echo "1. Testing prefixed environment variables:\n";
$_ENV['GITDEPLOY_JWT_SECRET'] = 'new-prefixed-secret';
$_ENV['GITDEPLOY_GIT_BINARY'] = 'C:\Program Files\Git\cmd\git.exe';
$_ENV['GITDEPLOY_PROJECT_ROOT'] = __DIR__ . '/../';
$_ENV['GITDEPLOY_TELEGRAM_BOT_TOKEN'] = 'new-bot-token';
$_ENV['GITDEPLOY_DEPLOYMENT_ENABLED'] = 'true';

try {
    $config = GitDeployConfig::fromEnv();
    echo "   ✅ Configuration loaded with prefixed variables\n";
    echo "   - JWT Secret: " . substr($config->getJwtSecret(), 0, 10) . "...\n";
    echo "   - Telegram Token: " . substr($config->get('telegram.bot_token'), 0, 10) . "...\n";
    echo "   - Deployment Enabled: " . ($config->get('deployment.enabled') ? 'Yes' : 'No') . "\n";
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// Reset singleton for next test
$reflection = new ReflectionClass(GitDeployConfig::class);
$instance = $reflection->getProperty('instance');
$instance->setAccessible(true);
$instance->setValue(null, null);

// Example 2: Demonstrating backward compatibility
echo "\n2. Testing backward compatibility:\n";
unset($_ENV['GITDEPLOY_JWT_SECRET']);
unset($_ENV['GITDEPLOY_TELEGRAM_BOT_TOKEN']);

$_ENV['JWT_SECRET'] = 'old-format-secret';
$_ENV['TELEGRAM_BOT_TOKEN'] = 'old-bot-token';

try {
    $config = GitDeployConfig::fromEnv();
    echo "   ✅ Configuration loaded with old format (with deprecation warnings)\n";
    echo "   - JWT Secret: " . substr($config->getJwtSecret(), 0, 10) . "...\n";
    echo "   - Telegram Token: " . substr($config->get('telegram.bot_token'), 0, 10) . "...\n";
    echo "   - Note: Check error logs for deprecation warnings\n";
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// Reset singleton for next test
$instance->setValue(null, null);

// Example 3: Priority demonstration
echo "\n3. Testing variable priority (prefixed takes precedence):\n";
$_ENV['JWT_SECRET'] = 'old-priority-secret';
$_ENV['GITDEPLOY_JWT_SECRET'] = 'new-priority-secret';
$_ENV['TELEGRAM_BOT_TOKEN'] = 'old-priority-token';
$_ENV['GITDEPLOY_TELEGRAM_BOT_TOKEN'] = 'new-priority-token';

try {
    $config = GitDeployConfig::fromEnv();
    echo "   ✅ Prefixed variables take priority\n";
    echo "   - JWT Secret (should be new): " . substr($config->getJwtSecret(), 0, 10) . "...\n";
    echo "   - Telegram Token (should be new): " . substr($config->get('telegram.bot_token'), 0, 10) . "...\n";
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// Example 4: Configuration via array (bypasses environment variables)
echo "\n4. Testing direct configuration (bypasses environment):\n";
$instance->setValue(null, null);

try {
    $config = GitDeployConfig::getInstance([
        'jwt_secret' => 'direct-config-secret',
        'git_binary' => 'C:\Program Files\Git\cmd\git.exe',
        'project_root' => __DIR__ . '/../',
        'telegram' => [
            'bot_token' => 'direct-config-token',
            'chat_id' => 'direct-config-chat',
        ]
    ]);
    echo "   ✅ Direct configuration works (ignores environment variables)\n";
    echo "   - JWT Secret: " . substr($config->getJwtSecret(), 0, 10) . "...\n";
    echo "   - Telegram Token: " . substr($config->get('telegram.bot_token'), 0, 10) . "...\n";
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// Clean up
$envVars = [
    'GITDEPLOY_JWT_SECRET', 'JWT_SECRET',
    'GITDEPLOY_GIT_BINARY', 'GIT_BINARY', 
    'GITDEPLOY_PROJECT_ROOT', 'PROJECT_ROOT',
    'GITDEPLOY_TELEGRAM_BOT_TOKEN', 'TELEGRAM_BOT_TOKEN',
    'GITDEPLOY_TELEGRAM_CHAT_ID', 'TELEGRAM_CHAT_ID',
    'GITDEPLOY_DEPLOYMENT_ENABLED', 'DEPLOYMENT_ENABLED'
];

foreach ($envVars as $var) {
    unset($_ENV[$var]);
}

echo "\n=== Summary ===\n";
echo "✅ Prefixed variables (GITDEPLOY_*) are now the recommended approach\n";
echo "✅ Old variables still work but show deprecation warnings\n";
echo "✅ Prefixed variables take priority over old ones\n";
echo "✅ Direct configuration bypasses all environment variables\n";
echo "✅ Full backward compatibility maintained\n\n";

echo "🚀 Migration recommendation:\n";
echo "1. Update your .env files to use GITDEPLOY_ prefixed variables\n";
echo "2. Remove old variables once migration is complete\n";
echo "3. Use the new .env.example as a reference\n";
echo "4. Old variables will be removed in v2.0.0\n";