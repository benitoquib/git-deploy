<?php

declare(strict_types=1);

namespace BenitoQuib\GitDeploy\Auth;

use BenitoQuib\GitDeploy\Exceptions\GitDeployException;

/**
 * GitLab webhook authentication handler
 */
class GitLabAuthenticator
{
    private string $secret;
    private array $allowedIps;
    private bool $validateIps;
    
    public function __construct(string $secret, array $allowedIps = [], bool $validateIps = false)
    {
        $this->secret = $secret;
        $this->allowedIps = $allowedIps;
        $this->validateIps = $validateIps;
    }
    
    /**
     * Check if request is a GitLab webhook
     */
    public function isGitLabWebhook(): bool
    {
        $event = $_SERVER['HTTP_X_GITLAB_EVENT'] ?? null;
        $token = $_SERVER['HTTP_X_GITLAB_TOKEN'] ?? null;
        
        return !empty($event) && !empty($token);
    }
    
    /**
     * Validate GitLab webhook request
     */
    public function validateGitLabWebhook(): bool
    {
        if (!$this->isGitLabWebhook()) {
            throw new GitDeployException('Not a GitLab webhook request', 400);
        }
        
        $token = $_SERVER['HTTP_X_GITLAB_TOKEN'] ?? null;
        
        if ($token !== $this->secret) {
            throw new GitDeployException('Invalid GitLab webhook token', 403);
        }
        
        // Validate IP if enabled
        if ($this->validateIps && !$this->isValidGitLabIp()) {
            throw new GitDeployException('Request not from authorized GitLab IP', 403);
        }
        
        return true;
    }
    
    /**
     * Get GitLab event type
     */
    public function getGitLabEvent(): ?string
    {
        return $_SERVER['HTTP_X_GITLAB_EVENT'] ?? null;
    }
    
    /**
     * Get GitLab event UUID
     */
    public function getGitLabEventUuid(): ?string
    {
        return $_SERVER['HTTP_X_GITLAB_EVENT_UUID'] ?? null;
    }
    
    /**
     * Check if request comes from valid GitLab IP
     */
    private function isValidGitLabIp(): bool
    {
        $clientIp = $this->getClientIp();
        
        if (empty($this->allowedIps)) {
            // Default GitLab.com IP ranges (you should update these as needed)
            $this->allowedIps = [
                '172.65.192.0/18',
                '185.199.108.0/22',
                '192.30.252.0/22',
                '140.82.112.0/20',
                '143.55.64.0/20',
                '34.74.90.64/26',
                '34.74.226.0/26'
            ];
        }
        
        foreach ($this->allowedIps as $allowedIp) {
            if ($this->ipInRange($clientIp, $allowedIp)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get client IP address
     */
    private function getClientIp(): string
    {
        $ipKeys = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) && !empty($_SERVER[$key])) {
                $ip = trim($_SERVER[$key]);
                
                // Handle comma-separated IPs
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Check if IP is in range
     */
    private function ipInRange(string $ip, string $range): bool
    {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }
        
        list($subnet, $mask) = explode('/', $range);
        
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $maskLong = -1 << (32 - (int)$mask);
        
        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }
    
    /**
     * Validate webhook signature (if GitLab is configured to send it)
     */
    public function validateSignature(?string $payload = null): bool
    {
        $signature = $_SERVER['HTTP_X_GITLAB_SIGNATURE'] ?? null;
        
        if (!$signature) {
            // No signature header, skip validation
            return true;
        }
        
        if ($payload === null) {
            $payload = file_get_contents('php://input');
        }
        
        $expectedSignature = hash_hmac('sha256', $payload, $this->secret);
        
        return hash_equals($signature, $expectedSignature);
    }
}