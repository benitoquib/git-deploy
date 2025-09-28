<?php

declare(strict_types=1);

namespace BenitoQuib\GitDeploy\Git;

use BenitoQuib\GitDeploy\GitDeployConfig;
use BenitoQuib\GitDeploy\Exceptions\GitDeployException;
use CzProject\GitPhp\Git;
use CzProject\GitPhp\GitRepository;
use CzProject\GitPhp\Runners\CliRunner;
use Exception;

/**
 * Git operations manager
 */
class GitManager
{
    private GitDeployConfig $config;
    private Git $git;
    private GitRepository $repository;
    
    public function __construct(GitDeployConfig $config)
    {
        $this->config = $config;
        $this->initializeGit();
    }
    
    private function initializeGit(): void
    {
        $gitBinary = $this->config->getGitBinary();
        
        if (!file_exists($gitBinary)) {
            throw new GitDeployException("Git binary not found at: {$gitBinary}");
        }
        
        $this->git = new Git(new CliRunner($gitBinary));
        
        try {
            $this->repository = $this->git->open($this->config->getProjectRoot());
        } catch (Exception $e) {
            throw new GitDeployException("Failed to open Git repository: " . $e->getMessage());
        }
    }
    
    /**
     * Stash current changes
     */
    public function stashChanges(): array
    {
        try {
            $result = $this->repository->execute(
                'stash',
                'push',
                '--keep-index',
                '--',
                '.',
                ':!.htaccess',
                ':!public/.htaccess'
            );
            return is_array($result) ? $result : [$result];
        } catch (Exception $e) {
            throw new GitDeployException("Failed to stash changes: " . $e->getMessage());
        }
    }
    
    /**
     * Execute git pull
     */
    public function pull(): bool
    {
        try {
            $this->repository->pull();
            return true;
        } catch (Exception $e) {
            throw new GitDeployException("Failed to pull changes: " . $e->getMessage());
        }
    }
    
    /**
     * Pop stashed changes
     */
    public function stashPop(): array
    {
        try {
            $result = $this->repository->execute('stash', 'pop');
            return is_array($result) ? $result : [$result];
        } catch (Exception $e) {
            throw new GitDeployException("Failed to pop stash: " . $e->getMessage());
        }
    }
    
    /**
     * Reset to specific commit
     */
    public function resetToCommit(string $commitId): array
    {
        try {
            $result = $this->repository->execute('reset', '--hard', $commitId);
            return is_array($result) ? $result : [$result];
        } catch (Exception $e) {
            throw new GitDeployException("Failed to reset to commit {$commitId}: " . $e->getMessage());
        }
    }
    
    /**
     * Get current branch name
     */
    public function getCurrentBranch(): string
    {
        try {
            return $this->repository->getCurrentBranchName();
        } catch (Exception $e) {
            throw new GitDeployException("Failed to get current branch: " . $e->getMessage());
        }
    }
    
    /**
     * Get current commit hash
     */
    public function getCurrentCommitHash(): string
    {
        try {
            $result = $this->repository->execute('rev-parse', 'HEAD');
            return is_array($result) ? trim($result[0]) : trim($result);
        } catch (Exception $e) {
            throw new GitDeployException("Failed to get current commit hash: " . $e->getMessage());
        }
    }
    
    /**
     * Get commit log with detailed information
     */
    public function getCommitLog(int $limit = 10): array
    {
        try {
            $logFormat = '%h|%s|%an|%ae|%ad|%cn|%ce|%cd';
            $commits = $this->repository->execute('log', '--pretty=format:' . $logFormat, '--date=iso', '-n', (string)$limit);
            
            $formattedCommits = [];
            $commitLines = is_array($commits) ? $commits : [$commits];
            
            foreach ($commitLines as $commit) {
                if (empty($commit)) continue;
                
                $parts = explode('|', $commit);
                if (count($parts) === 8) {
                    $formattedCommits[] = [
                        'hash' => $parts[0],
                        'message' => $parts[1],
                        'author_name' => $parts[2],
                        'author_email' => $parts[3],
                        'author_date' => $parts[4],
                        'committer_name' => $parts[5],
                        'committer_email' => $parts[6],
                        'commit_date' => $parts[7]
                    ];
                }
            }
            
            return [
                'commits' => $formattedCommits,
                'branch' => $this->getCurrentBranch(),
                'total_commits' => count($formattedCommits)
            ];
            
        } catch (Exception $e) {
            throw new GitDeployException("Failed to get commit log: " . $e->getMessage());
        }
    }
    
    /**
     * Get repository status
     */
    public function getStatus(): array
    {
        try {
            $status = $this->repository->execute('status', '--porcelain');
            $statusLines = is_array($status) ? $status : [$status];
            
            $modified = [];
            $added = [];
            $deleted = [];
            $untracked = [];
            
            foreach ($statusLines as $line) {
                if (empty($line)) continue;
                
                $statusCode = substr($line, 0, 2);
                $file = trim(substr($line, 3));
                
                switch (trim($statusCode)) {
                    case 'M':
                    case 'AM':
                        $modified[] = $file;
                        break;
                    case 'A':
                        $added[] = $file;
                        break;
                    case 'D':
                        $deleted[] = $file;
                        break;
                    case '??':
                        $untracked[] = $file;
                        break;
                }
            }
            
            return [
                'branch' => $this->getCurrentBranch(),
                'commit' => $this->getCurrentCommitHash(),
                'modified' => $modified,
                'added' => $added,
                'deleted' => $deleted,
                'untracked' => $untracked,
                'clean' => empty($modified) && empty($added) && empty($deleted) && empty($untracked)
            ];
            
        } catch (Exception $e) {
            throw new GitDeployException("Failed to get repository status: " . $e->getMessage());
        }
    }
    
    /**
     * Check if working directory is clean
     */
    public function isWorkingDirectoryClean(): bool
    {
        $status = $this->getStatus();
        return $status['clean'];
    }
    
    /**
     * Get last commit info
     */
    public function getLastCommitInfo(): array
    {
        try {
            $format = '%H|%s|%an|%ae|%ad';
            $result = $this->repository->execute('log', '-1', '--pretty=format:' . $format, '--date=iso');
            $commitLine = is_array($result) ? $result[0] : $result;
            
            $parts = explode('|', $commitLine);
            
            if (count($parts) === 5) {
                return [
                    'hash' => $parts[0],
                    'message' => $parts[1],
                    'author_name' => $parts[2],
                    'author_email' => $parts[3],
                    'date' => $parts[4]
                ];
            }
            
            throw new GitDeployException("Invalid commit format");
            
        } catch (Exception $e) {
            throw new GitDeployException("Failed to get last commit info: " . $e->getMessage());
        }
    }
    
    /**
     * Execute custom git command
     */
    public function executeCommand(string ...$args): array
    {
        try {
            $result = $this->repository->execute(...$args);
            return is_array($result) ? $result : [$result];
        } catch (Exception $e) {
            throw new GitDeployException("Failed to execute git command: " . $e->getMessage());
        }
    }
    
    /**
     * Get Git repository instance
     */
    public function getRepository(): GitRepository
    {
        return $this->repository;
    }
}