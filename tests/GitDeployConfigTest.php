<?php

declare(strict_types=1);

namespace BenitoQuib\GitDeploy\Tests;

use PHPUnit\Framework\TestCase;
use BenitoQuib\GitDeploy\GitDeployConfig;
use BenitoQuib\GitDeploy\Exceptions\GitDeployException;

class GitDeployConfigTest extends TestCase
{
    public function testCanCreateConfigFromArray(): void
    {
        $config = GitDeployConfig::getInstance([
            'jwt_secret' => 'test-secret',
            'git_binary' => 'C:\Program Files\Git\cmd\git.exe',
            'project_root' => __DIR__,
        ]);
        
        $this->assertEquals('test-secret', $config->getJwtSecret());
        $this->assertEquals('C:\Program Files\Git\cmd\git.exe', $config->getGitBinary());
        $this->assertEquals(__DIR__, $config->getProjectRoot());
    }
    
    public function testThrowsExceptionWhenJwtSecretMissing(): void
    {
        $this->expectException(GitDeployException::class);
        $this->expectExceptionMessage('JWT_SECRET is required');
        
        GitDeployConfig::getInstance([
            'git_binary' => '/usr/bin/git',
            'project_root' => __DIR__,
        ]);
    }
    
    public function testCanGetNestedValues(): void
    {
        $config = GitDeployConfig::getInstance([
            'jwt_secret' => 'test-secret',
            'git_binary' => 'C:\Program Files\Git\cmd\git.exe',
            'project_root' => __DIR__,
            'telegram' => [
                'bot_token' => 'test-token',
                'chat_id' => 'test-chat-id',
            ]
        ]);
        
        $this->assertEquals('test-token', $config->get('telegram.bot_token'));
        $this->assertEquals('test-chat-id', $config->get('telegram.chat_id'));
        $this->assertNull($config->get('telegram.nonexistent'));
        $this->assertEquals('default', $config->get('telegram.nonexistent', 'default'));
    }
    
    public function testTelegramConfigurationDetection(): void
    {
        $config = GitDeployConfig::getInstance([
            'jwt_secret' => 'test-secret',
            'git_binary' => 'C:\Program Files\Git\cmd\git.exe',
            'project_root' => __DIR__,
            'telegram' => [
                'bot_token' => 'test-token',
                'chat_id' => 'test-chat-id',
                'enabled' => true,
            ]
        ]);
        
        $this->assertTrue($config->isTelegramEnabled());
        
        // Reset singleton for second test
        $reflection = new \ReflectionClass(GitDeployConfig::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
        
        // Test with missing bot_token
        $config2 = GitDeployConfig::getInstance([
            'jwt_secret' => 'test-secret',
            'git_binary' => 'C:\Program Files\Git\cmd\git.exe',
            'project_root' => __DIR__,
            'telegram' => [
                'chat_id' => 'test-chat-id',
                'enabled' => true,
            ]
        ]);
        
        $this->assertFalse($config2->isTelegramEnabled());
    }
    
    protected function tearDown(): void
    {
        // Reset singleton for next test
        $reflection = new \ReflectionClass(GitDeployConfig::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
    }
}