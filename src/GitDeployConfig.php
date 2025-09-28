<?php

declare(strict_types=1);

namespace BenitoQuib\GitDeploy;

use BenitoQuib\GitDeploy\Exceptions\GitDeployException;

/**
 * Configuration manager for GitDeploy package
 */
class GitDeployConfig
{
    private array $config;
    private static ?self $instance = null;
    
    private function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->validateConfig();
    }
    
    public static function getInstance(array $config = []): self
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        
        return self::$instance;
    }
    
    public static function fromEnv(): self
    {
        $config = [
            'jwt_secret' => $_ENV['JWT_SECRET'] ?? null,
            'git_binary' => $_ENV['GIT_BINARY'] ?? '/usr/bin/git',
            'project_root' => $_ENV['PROJECT_ROOT'] ?? getcwd(),
            'timezone' => $_ENV['TIMEZONE'] ?? 'America/Guatemala',
            'telegram' => [
                'bot_token' => $_ENV['TELEGRAM_BOT_TOKEN'] ?? null,
                'chat_id' => $_ENV['TELEGRAM_CHAT_ID'] ?? null,
            ],
            'jwt' => [
                'algorithm' => $_ENV['JWT_ALGO'] ?? 'HS256',
                'issuer' => $_ENV['JWT_ISSUER'] ?? 'central_system',
                'audience' => $_ENV['JWT_AUDIENCE'] ?? 'central_system',
                'expiration' => (int) ($_ENV['JWT_EXPIRATION'] ?? 3600), // 1 hour
                'leeway' => (int) ($_ENV['JWT_LEEWAY'] ?? 30), // 30 seconds
            ],
            'deployment' => [
                'enabled' => filter_var($_ENV['DEPLOYMENT_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
                'auto_composer' => filter_var($_ENV['AUTO_COMPOSER'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
                'backup_commits' => filter_var($_ENV['BACKUP_COMMITS'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
            ]
        ];
        
        return new self($config);
    }
    
    private function getDefaultConfig(): array
    {
        return [
            'jwt_secret' => null,
            'git_binary' => '/usr/bin/git',
            'project_root' => getcwd(),
            'timezone' => 'UTC',
            'telegram' => [
                'bot_token' => null,
                'chat_id' => null,
                'enabled' => true,
            ],
            'jwt' => [
                'algorithm' => 'HS256',
                'issuer' => 'central_system',
                'audience' => 'central_system',
                'expiration' => 3600,
                'leeway' => 30,
            ],
            'deployment' => [
                'enabled' => true,
                'auto_composer' => true,
                'backup_commits' => true,
            ],
            'security' => [
                'validate_gitlab_ips' => false,
                'allowed_ips' => [],
            ]
        ];
    }
    
    private function validateConfig(): void
    {
        if (empty($this->config['jwt_secret'])) {
            throw new GitDeployException('JWT_SECRET is required');
        }
        
        if (!file_exists($this->config['git_binary'])) {
            throw new GitDeployException("Git binary not found at: {$this->config['git_binary']}");
        }
        
        if (!is_dir($this->config['project_root'])) {
            throw new GitDeployException("Project root directory not found: {$this->config['project_root']}");
        }
    }
    
    public function get(string $key, $default = null)
    {
        return $this->getNestedValue($this->config, $key, $default);
    }
    
    public function set(string $key, $value): void
    {
        $this->setNestedValue($this->config, $key, $value);
    }
    
    public function getJwtSecret(): string
    {
        return $this->config['jwt_secret'];
    }
    
    public function getGitBinary(): string
    {
        return $this->config['git_binary'];
    }
    
    public function getProjectRoot(): string
    {
        return $this->config['project_root'];
    }
    
    public function getTelegramConfig(): array
    {
        return $this->config['telegram'];
    }
    
    public function getJwtConfig(): array
    {
        return $this->config['jwt'];
    }
    
    public function getDeploymentConfig(): array
    {
        return $this->config['deployment'];
    }
    
    public function isDeploymentEnabled(): bool
    {
        return $this->config['deployment']['enabled'];
    }
    
    public function isTelegramEnabled(): bool
    {
        return $this->config['telegram']['enabled'] && 
               !empty($this->config['telegram']['bot_token']) && 
               !empty($this->config['telegram']['chat_id']);
    }
    
    private function getNestedValue(array $array, string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $array;
        
        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
    
    private function setNestedValue(array &$array, string $key, $value): void
    {
        $keys = explode('.', $key);
        $current = &$array;
        
        foreach ($keys as $k) {
            if (!isset($current[$k]) || !is_array($current[$k])) {
                $current[$k] = [];
            }
            $current = &$current[$k];
        }
        
        $current = $value;
    }
    
    public function toArray(): array
    {
        return $this->config;
    }
}