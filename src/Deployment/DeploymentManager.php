<?php

declare(strict_types=1);

namespace BenitoQuib\GitDeploy\Deployment;

use BenitoQuib\GitDeploy\GitDeployConfig;
use BenitoQuib\GitDeploy\Git\GitManager;
use BenitoQuib\GitDeploy\Exceptions\GitDeployException;
use Exception;

/**
 * Deployment operations manager
 */
class DeploymentManager
{
    private GitDeployConfig $config;
    private GitManager $gitManager;
    private string $backupFile;
    
    public function __construct(GitDeployConfig $config, GitManager $gitManager)
    {
        $this->config = $config;
        $this->gitManager = $gitManager;
        $this->backupFile = $config->getProjectRoot() . '/.git-deploy-backup';
    }
    
    /**
     * Execute deployment process
     */
    public function deploy(bool $forceComposer = false): array
    {
        $startTime = microtime(true);
        $deploymentTime = date('Y-m-d H:i:s');
        
        try {
            $result = [
                'success' => true,
                'deployment_time' => $deploymentTime,
                'composer_changes' => false,
                'composer_install' => null,
                'check_method' => 'file_exists',
                'execution_time' => 0
            ];
            
            // Check if composer changes are needed
            if ($forceComposer || $this->hasComposerChanges()) {
                $result['composer_changes'] = true;
                $result['composer_install'] = $this->runComposerInstall();
                
                if (!$result['composer_install']['success']) {
                    $result['success'] = false;
                }
            }
            
            // Run additional deployment tasks if configured
            $additionalTasks = $this->runAdditionalTasks();
            if (!empty($additionalTasks)) {
                $result['additional_tasks'] = $additionalTasks;
            }
            
            $result['execution_time'] = round(microtime(true) - $startTime, 2);
            
            return $result;
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'deployment_time' => $deploymentTime,
                'error' => $e->getMessage(),
                'execution_time' => round(microtime(true) - $startTime, 2)
            ];
        }
    }
    
    /**
     * Save current commit for potential rollback
     */
    public function saveCurrentCommit(): bool
    {
        if (!$this->config->get('deployment.backup_commits')) {
            return false;
        }
        
        try {
            $currentCommit = $this->gitManager->getCurrentCommitHash();
            $currentBranch = $this->gitManager->getCurrentBranch();
            $timestamp = date('Y-m-d H:i:s');
            
            $backupData = [
                'commit' => $currentCommit,
                'branch' => $currentBranch,
                'timestamp' => $timestamp,
                'saved_at' => time()
            ];
            
            file_put_contents($this->backupFile, json_encode($backupData, JSON_PRETTY_PRINT));
            
            return true;
            
        } catch (Exception $e) {
            error_log("Failed to save current commit: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Rollback to previously saved commit
     */
    public function rollbackToSavedCommit(): array
    {
        if (!file_exists($this->backupFile)) {
            throw new GitDeployException('No backup commit found');
        }
        
        $backupData = json_decode(file_get_contents($this->backupFile), true);
        
        if (!$backupData || !isset($backupData['commit'])) {
            throw new GitDeployException('Invalid backup data');
        }
        
        try {
            $result = $this->gitManager->resetToCommit($backupData['commit']);
            
            return [
                'success' => true,
                'rollback_commit' => $backupData['commit'],
                'rollback_branch' => $backupData['branch'],
                'backup_timestamp' => $backupData['timestamp'],
                'result' => $result
            ];
            
        } catch (Exception $e) {
            throw new GitDeployException("Rollback failed: " . $e->getMessage());
        }
    }
    
    /**
     * Check if composer.json or composer.lock has changes
     */
    private function hasComposerChanges(): bool
    {
        $projectRoot = $this->config->getProjectRoot();
        $composerJson = $projectRoot . '/composer.json';
        $composerLock = $projectRoot . '/composer.lock';
        
        // Check if composer files exist
        if (!file_exists($composerJson)) {
            return false;
        }
        
        try {
            // Get last commit hash for composer files
            $lastCommit = $this->gitManager->executeCommand('log', '-1', '--pretty=format:%H', '--', 'composer.json', 'composer.lock');
            
            if (empty($lastCommit)) {
                return false;
            }
            
            // Check if composer files have been modified in the last commit
            $modifiedFiles = $this->gitManager->executeCommand('diff-tree', '--no-commit-id', '--name-only', '-r', $lastCommit[0]);
            
            foreach ($modifiedFiles as $file) {
                if (in_array(trim($file), ['composer.json', 'composer.lock'])) {
                    return true;
                }
            }
            
            return false;
            
        } catch (Exception $e) {
            // Fallback: assume changes exist if we can't determine
            error_log("Error checking composer changes: " . $e->getMessage());
            return true;
        }
    }
    
    /**
     * Run composer install
     */
    private function runComposerInstall(): array
    {
        $projectRoot = $this->config->getProjectRoot();
        $composerBinary = $this->findComposerBinary();
        
        if (!$composerBinary) {
            return [
                'success' => false,
                'error' => 'Composer binary not found',
                'output' => []
            ];
        }
        
        $command = "cd \"{$projectRoot}\" && {$composerBinary} install --no-dev --optimize-autoloader 2>&1";
        
        $startTime = microtime(true);
        $output = [];
        $returnCode = 0;
        
        exec($command, $output, $returnCode);
        
        $executionTime = round(microtime(true) - $startTime, 2);
        
        return [
            'success' => $returnCode === 0,
            'command' => $command,
            'output' => $output,
            'return_code' => $returnCode,
            'execution_time' => $executionTime
        ];
    }
    
    /**
     * Find composer binary in common locations
     */
    private function findComposerBinary(): ?string
    {
        $possiblePaths = [
            '/usr/local/bin/composer',
            '/usr/bin/composer',
            '/usr/local/bin/composer.phar',
            '/usr/bin/composer.phar',
            'composer',
            'composer.phar'
        ];
        
        foreach ($possiblePaths as $path) {
            if ($this->commandExists($path)) {
                return $path;
            }
        }
        
        return null;
    }
    
    /**
     * Check if command exists
     */
    private function commandExists(string $command): bool
    {
        $test = "which {$command} 2>/dev/null || command -v {$command} 2>/dev/null";
        return !empty(shell_exec($test));
    }
    
    /**
     * Run additional deployment tasks
     */
    private function runAdditionalTasks(): array
    {
        $tasks = [];
        
        // Cache clearing
        if ($this->shouldClearCache()) {
            $tasks['cache_clear'] = $this->clearCache();
        }
        
        // Permission fixes
        if ($this->shouldFixPermissions()) {
            $tasks['permissions'] = $this->fixPermissions();
        }
        
        // Custom deployment script
        $customScript = $this->config->get('deployment.custom_script');
        if (!empty($customScript) && file_exists($customScript)) {
            $tasks['custom_script'] = $this->runCustomScript($customScript);
        }
        
        return $tasks;
    }
    
    /**
     * Check if cache should be cleared
     */
    private function shouldClearCache(): bool
    {
        return $this->config->get('deployment.clear_cache', false);
    }
    
    /**
     * Clear application cache
     */
    private function clearCache(): array
    {
        $projectRoot = $this->config->getProjectRoot();
        $commands = [];
        
        // Laravel cache clearing
        if (file_exists($projectRoot . '/artisan')) {
            $commands[] = "cd \"{$projectRoot}\" && php artisan cache:clear";
            $commands[] = "cd \"{$projectRoot}\" && php artisan config:clear";
            $commands[] = "cd \"{$projectRoot}\" && php artisan view:clear";
        }
        
        // Generic cache directories
        $cacheDirs = [
            $projectRoot . '/cache',
            $projectRoot . '/tmp',
            $projectRoot . '/storage/cache'
        ];
        
        foreach ($cacheDirs as $dir) {
            if (is_dir($dir)) {
                $commands[] = "find \"{$dir}\" -type f -name '*.cache' -delete";
            }
        }
        
        $results = [];
        foreach ($commands as $command) {
            $output = [];
            $returnCode = 0;
            exec($command . ' 2>&1', $output, $returnCode);
            
            $results[] = [
                'command' => $command,
                'success' => $returnCode === 0,
                'output' => $output
            ];
        }
        
        return $results;
    }
    
    /**
     * Check if permissions should be fixed
     */
    private function shouldFixPermissions(): bool
    {
        return $this->config->get('deployment.fix_permissions', false);
    }
    
    /**
     * Fix file permissions
     */
    private function fixPermissions(): array
    {
        $projectRoot = $this->config->getProjectRoot();
        $commands = [
            "find \"{$projectRoot}\" -type f -exec chmod 644 {} \\;",
            "find \"{$projectRoot}\" -type d -exec chmod 755 {} \\;"
        ];
        
        // Make specific files executable
        $executableFiles = $this->config->get('deployment.executable_files', []);
        foreach ($executableFiles as $file) {
            $fullPath = $projectRoot . '/' . ltrim($file, '/');
            if (file_exists($fullPath)) {
                $commands[] = "chmod +x \"{$fullPath}\"";
            }
        }
        
        $results = [];
        foreach ($commands as $command) {
            $output = [];
            $returnCode = 0;
            exec($command . ' 2>&1', $output, $returnCode);
            
            $results[] = [
                'command' => $command,
                'success' => $returnCode === 0,
                'output' => $output
            ];
        }
        
        return $results;
    }
    
    /**
     * Run custom deployment script
     */
    private function runCustomScript(string $scriptPath): array
    {
        $output = [];
        $returnCode = 0;
        
        $command = "bash \"{$scriptPath}\" 2>&1";
        exec($command, $output, $returnCode);
        
        return [
            'script' => $scriptPath,
            'command' => $command,
            'success' => $returnCode === 0,
            'output' => $output,
            'return_code' => $returnCode
        ];
    }
    
    /**
     * Get backup information
     */
    public function getBackupInfo(): ?array
    {
        if (!file_exists($this->backupFile)) {
            return null;
        }
        
        $data = json_decode(file_get_contents($this->backupFile), true);
        
        if (!$data) {
            return null;
        }
        
        $data['backup_age_hours'] = round((time() - $data['saved_at']) / 3600, 1);
        
        return $data;
    }
    
    /**
     * Clean old backup files
     */
    public function cleanOldBackups(int $maxAgeHours = 168): bool // 7 days default
    {
        if (!file_exists($this->backupFile)) {
            return true;
        }
        
        $backupInfo = $this->getBackupInfo();
        
        if ($backupInfo && $backupInfo['backup_age_hours'] > $maxAgeHours) {
            return unlink($this->backupFile);
        }
        
        return true;
    }
}