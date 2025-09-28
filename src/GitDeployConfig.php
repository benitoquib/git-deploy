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
            // Main configuration with fallback support
            'jwt_secret' => self::getEnvWithFallback('GITDEPLOY_JWT_SECRET', 'JWT_SECRET'),
            'git_binary' => self::getEnvWithFallback('GITDEPLOY_GIT_BINARY', 'GIT_BINARY', '/usr/bin/git'),
            'project_root' => self::getEnvWithFallback('GITDEPLOY_PROJECT_ROOT', 'PROJECT_ROOT', getcwd()),
            'timezone' => self::getEnvWithFallback('GITDEPLOY_TIMEZONE', 'TIMEZONE', 'America/Guatemala'),
            
            // Telegram configuration
            'telegram' => [
                'bot_token' => self::getEnvWithFallback('GITDEPLOY_TELEGRAM_BOT_TOKEN', 'TELEGRAM_BOT_TOKEN'),
                'chat_id' => self::getEnvWithFallback('GITDEPLOY_TELEGRAM_CHAT_ID', 'TELEGRAM_CHAT_ID'),
                'enabled' => true,
            ],
            
            // JWT configuration
            'jwt' => [
                'algorithm' => self::getEnvWithFallback('GITDEPLOY_JWT_ALGO', 'JWT_ALGO', 'HS256'),
                'issuer' => self::getEnvWithFallback('GITDEPLOY_JWT_ISSUER', 'JWT_ISSUER', 'central_system'),
                'audience' => self::getEnvWithFallback('GITDEPLOY_JWT_AUDIENCE', 'JWT_AUDIENCE', 'central_system'),
                'expiration' => (int) self::getEnvWithFallback('GITDEPLOY_JWT_EXPIRATION', 'JWT_EXPIRATION', '3600'),
                'leeway' => (int) self::getEnvWithFallback('GITDEPLOY_JWT_LEEWAY', 'JWT_LEEWAY', '30'),
            ],
            
            // Deployment configuration
            'deployment' => [
                'enabled' => filter_var(self::getEnvWithFallback('GITDEPLOY_DEPLOYMENT_ENABLED', 'DEPLOYMENT_ENABLED', 'true'), FILTER_VALIDATE_BOOLEAN),
                'auto_composer' => filter_var(self::getEnvWithFallback('GITDEPLOY_AUTO_COMPOSER', 'AUTO_COMPOSER', 'true'), FILTER_VALIDATE_BOOLEAN),
                'backup_commits' => filter_var(self::getEnvWithFallback('GITDEPLOY_BACKUP_COMMITS', 'BACKUP_COMMITS', 'true'), FILTER_VALIDATE_BOOLEAN),
                'clear_cache' => filter_var(self::getEnvWithFallback('GITDEPLOY_CLEAR_CACHE', 'CLEAR_CACHE', 'false'), FILTER_VALIDATE_BOOLEAN),
                'fix_permissions' => filter_var(self::getEnvWithFallback('GITDEPLOY_FIX_PERMISSIONS', 'FIX_PERMISSIONS', 'false'), FILTER_VALIDATE_BOOLEAN),
                'composer_binary' => self::getEnvWithFallback('GITDEPLOY_COMPOSER_BINARY', 'COMPOSER_BINARY'),
                'custom_script' => self::getEnvWithFallback('GITDEPLOY_CUSTOM_SCRIPT_PATH', 'CUSTOM_SCRIPT_PATH'),
                'max_backup_age_hours' => (int) self::getEnvWithFallback('GITDEPLOY_MAX_BACKUP_AGE_HOURS', 'MAX_BACKUP_AGE_HOURS', '168'),
            ],
            
            // Security configuration
            'security' => [
                'validate_gitlab_ips' => filter_var(self::getEnvWithFallback('GITDEPLOY_VALIDATE_GITLAB_IPS', 'VALIDATE_GITLAB_IPS', 'false'), FILTER_VALIDATE_BOOLEAN),
                'allowed_ips' => array_filter(explode(',', self::getEnvWithFallback('GITDEPLOY_ALLOWED_IPS', 'ALLOWED_IPS', ''))),
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
    
    /**
     * Get environment variable with fallback support
     * Checks prefixed version first, then fallback, then default
     */
    private static function getEnvWithFallback(string $prefixedKey, string $fallbackKey = null, string $default = null): ?string
    {
        // Check prefixed version first (new format)
        $value = $_ENV[$prefixedKey] ?? null;
        
        if ($value !== null) {
            return $value;
        }
        
        // Check fallback version (old format for backward compatibility)
        if ($fallbackKey !== null) {
            $value = $_ENV[$fallbackKey] ?? null;
            
            if ($value !== null) {
                // Log deprecation warning for old format
                if (function_exists('error_log')) {
                    error_log("GitDeploy: Using deprecated environment variable '{$fallbackKey}'. Please use '{$prefixedKey}' instead. The old format will be removed in v2.0.0");
                }
                return $value;
            }
        }
        
        return $default;
    }
    
    public function toArray(): array
    {
        return $this->config;
    }
}