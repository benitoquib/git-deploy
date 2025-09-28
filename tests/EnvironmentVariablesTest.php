<?php

declare(strict_types=1);

namespace BenitoQuib\GitDeploy\Tests;

use PHPUnit\Framework\TestCase;
use BenitoQuib\GitDeploy\GitDeployConfig;

class EnvironmentVariablesTest extends TestCase
{
    protected function setUp(): void
    {
        // Clear environment variables before each test
        $envVars = [
            'GITDEPLOY_JWT_SECRET', 'JWT_SECRET',
            'GITDEPLOY_GIT_BINARY', 'GIT_BINARY',
            'GITDEPLOY_PROJECT_ROOT', 'PROJECT_ROOT',
            'GITDEPLOY_TELEGRAM_BOT_TOKEN', 'TELEGRAM_BOT_TOKEN',
            'GITDEPLOY_TELEGRAM_CHAT_ID', 'TELEGRAM_CHAT_ID',
        ];
        
        foreach ($envVars as $var) {
            unset($_ENV[$var]);
        }
        
        // Reset singleton
        $reflection = new \ReflectionClass(GitDeployConfig::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
    }
    
    public function testPrefixedVariablesTakePriority(): void
    {
        // Set both prefixed and non-prefixed versions
        $_ENV['JWT_SECRET'] = 'old-secret';
        $_ENV['GITDEPLOY_JWT_SECRET'] = 'new-secret';
        $_ENV['GIT_BINARY'] = '/old/path/git';
        $_ENV['GITDEPLOY_GIT_BINARY'] = 'C:\Program Files\Git\cmd\git.exe';
        $_ENV['PROJECT_ROOT'] = '/old/project';
        $_ENV['GITDEPLOY_PROJECT_ROOT'] = __DIR__;
        
        $config = GitDeployConfig::fromEnv();
        
        // Prefixed versions should take priority
        $this->assertEquals('new-secret', $config->getJwtSecret());
        $this->assertEquals('C:\Program Files\Git\cmd\git.exe', $config->getGitBinary());
        $this->assertEquals(__DIR__, $config->getProjectRoot());
    }
    
    public function testFallbackToOldVariables(): void
    {
        // Only set old format variables
        $_ENV['JWT_SECRET'] = 'fallback-secret';
        $_ENV['GIT_BINARY'] = 'C:\Program Files\Git\cmd\git.exe';
        $_ENV['PROJECT_ROOT'] = __DIR__;
        $_ENV['TELEGRAM_BOT_TOKEN'] = 'fallback-bot-token';
        $_ENV['TELEGRAM_CHAT_ID'] = 'fallback-chat-id';
        
        $config = GitDeployConfig::fromEnv();
        
        // Should use fallback values
        $this->assertEquals('fallback-secret', $config->getJwtSecret());
        $this->assertEquals('C:\Program Files\Git\cmd\git.exe', $config->getGitBinary());
        $this->assertEquals(__DIR__, $config->getProjectRoot());
        
        $telegramConfig = $config->getTelegramConfig();
        $this->assertEquals('fallback-bot-token', $telegramConfig['bot_token']);
        $this->assertEquals('fallback-chat-id', $telegramConfig['chat_id']);
    }
    
    public function testDefaultValuesWhenNoEnvVars(): void
    {
        // Set only required variable
        $_ENV['GITDEPLOY_JWT_SECRET'] = 'required-secret';
        $_ENV['GITDEPLOY_GIT_BINARY'] = 'C:\Program Files\Git\cmd\git.exe'; // Required for validation
        
        $config = GitDeployConfig::fromEnv();
        
        // Should use default values
        $this->assertEquals('required-secret', $config->getJwtSecret());
        $this->assertEquals('America/Guatemala', $config->get('timezone'));
        $this->assertTrue($config->get('deployment.enabled'));
        $this->assertTrue($config->get('deployment.auto_composer'));
        $this->assertTrue($config->get('deployment.backup_commits'));
        $this->assertFalse($config->get('deployment.clear_cache'));
        $this->assertFalse($config->get('deployment.fix_permissions'));
    }
    
    public function testBooleanEnvironmentVariables(): void
    {
        $_ENV['GITDEPLOY_JWT_SECRET'] = 'test-secret';
        $_ENV['GITDEPLOY_GIT_BINARY'] = 'C:\Program Files\Git\cmd\git.exe';
        $_ENV['GITDEPLOY_PROJECT_ROOT'] = __DIR__;
        
        // Test boolean variables
        $_ENV['GITDEPLOY_DEPLOYMENT_ENABLED'] = 'false';
        $_ENV['GITDEPLOY_AUTO_COMPOSER'] = 'true';
        $_ENV['GITDEPLOY_BACKUP_COMMITS'] = '1';
        $_ENV['GITDEPLOY_CLEAR_CACHE'] = 'yes';
        $_ENV['GITDEPLOY_FIX_PERMISSIONS'] = '0';
        $_ENV['GITDEPLOY_VALIDATE_GITLAB_IPS'] = 'on';
        
        $config = GitDeployConfig::fromEnv();
        
        $deploymentConfig = $config->getDeploymentConfig();
        $this->assertFalse($deploymentConfig['enabled']);
        $this->assertTrue($deploymentConfig['auto_composer']);
        $this->assertTrue($deploymentConfig['backup_commits']);
        $this->assertTrue($deploymentConfig['clear_cache']);
        $this->assertFalse($deploymentConfig['fix_permissions']);
        
        $securityConfig = $config->get('security');
        $this->assertTrue($securityConfig['validate_gitlab_ips']);
    }
    
    public function testIntegerEnvironmentVariables(): void
    {
        $_ENV['GITDEPLOY_JWT_SECRET'] = 'test-secret';
        $_ENV['GITDEPLOY_GIT_BINARY'] = 'C:\Program Files\Git\cmd\git.exe';
        $_ENV['GITDEPLOY_PROJECT_ROOT'] = __DIR__;
        
        // Test integer variables
        $_ENV['GITDEPLOY_JWT_EXPIRATION'] = '7200';
        $_ENV['GITDEPLOY_JWT_LEEWAY'] = '60';
        $_ENV['GITDEPLOY_MAX_BACKUP_AGE_HOURS'] = '336';
        
        $config = GitDeployConfig::fromEnv();
        
        $jwtConfig = $config->getJwtConfig();
        $this->assertEquals(7200, $jwtConfig['expiration']);
        $this->assertEquals(60, $jwtConfig['leeway']);
        
        $deploymentConfig = $config->getDeploymentConfig();
        $this->assertEquals(336, $deploymentConfig['max_backup_age_hours']);
    }
    
    public function testArrayEnvironmentVariables(): void
    {
        $_ENV['GITDEPLOY_JWT_SECRET'] = 'test-secret';
        $_ENV['GITDEPLOY_GIT_BINARY'] = 'C:\Program Files\Git\cmd\git.exe';
        $_ENV['GITDEPLOY_PROJECT_ROOT'] = __DIR__;
        
        // Test array variable (comma-separated IPs)
        $_ENV['GITDEPLOY_ALLOWED_IPS'] = '192.168.1.0/24,10.0.0.0/8,172.16.0.0/12';
        
        $config = GitDeployConfig::fromEnv();
        
        $securityConfig = $config->get('security');
        $expectedIps = ['192.168.1.0/24', '10.0.0.0/8', '172.16.0.0/12'];
        $this->assertEquals($expectedIps, $securityConfig['allowed_ips']);
    }
    
    protected function tearDown(): void
    {
        // Clean up environment variables
        $envVars = [
            'GITDEPLOY_JWT_SECRET', 'JWT_SECRET',
            'GITDEPLOY_GIT_BINARY', 'GIT_BINARY',
            'GITDEPLOY_PROJECT_ROOT', 'PROJECT_ROOT',
            'GITDEPLOY_TELEGRAM_BOT_TOKEN', 'TELEGRAM_BOT_TOKEN',
            'GITDEPLOY_TELEGRAM_CHAT_ID', 'TELEGRAM_CHAT_ID',
            'GITDEPLOY_DEPLOYMENT_ENABLED', 'DEPLOYMENT_ENABLED',
            'GITDEPLOY_AUTO_COMPOSER', 'AUTO_COMPOSER',
            'GITDEPLOY_BACKUP_COMMITS', 'BACKUP_COMMITS',
            'GITDEPLOY_CLEAR_CACHE', 'CLEAR_CACHE',
            'GITDEPLOY_FIX_PERMISSIONS', 'FIX_PERMISSIONS',
            'GITDEPLOY_VALIDATE_GITLAB_IPS', 'VALIDATE_GITLAB_IPS',
            'GITDEPLOY_JWT_EXPIRATION', 'JWT_EXPIRATION',
            'GITDEPLOY_JWT_LEEWAY', 'JWT_LEEWAY',
            'GITDEPLOY_MAX_BACKUP_AGE_HOURS', 'MAX_BACKUP_AGE_HOURS',
            'GITDEPLOY_ALLOWED_IPS', 'ALLOWED_IPS',
        ];
        
        foreach ($envVars as $var) {
            unset($_ENV[$var]);
        }
    }
}