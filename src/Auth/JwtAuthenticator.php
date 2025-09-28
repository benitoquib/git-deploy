<?php

declare(strict_types=1);

namespace BenitoQuib\GitDeploy\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use BenitoQuib\GitDeploy\Exceptions\GitDeployException;
use Exception;

/**
 * JWT token authentication handler
 */
class JwtAuthenticator
{
    private array $config;
    
    public function __construct(array $config)
    {
        $this->config = $config;
    }
    
    /**
     * Validate JWT token from Authorization header
     */
    public function validateToken(): bool
    {
        try {
            $token = $this->getAuthHeader();
            if (!$token) {
                throw new GitDeployException('No token provided', 401);
            }
            
            // Set JWT configuration
            JWT::$timestamp = time();
            JWT::$leeway = $this->config['leeway'];
            
            // Get secret from config (handle both formats)
            $secret = $this->config['secret'] ?? $this->config[0] ?? null;
            if (!$secret) {
                throw new GitDeployException('JWT secret not configured', 500);
            }
            
            $decoded = JWT::decode($token, new Key($secret, $this->config['algorithm']));
            
            if ($decoded->exp < time()) {
                throw new GitDeployException('Token expired', 401);
            }
            
            if (!isset($decoded->iss) || $decoded->iss !== $this->config['issuer']) {
                throw new GitDeployException('Invalid token issuer', 401);
            }
            
            return true;
            
        } catch (Exception $e) {
            throw new GitDeployException('Unauthorized: ' . $e->getMessage(), 401);
        }
    }
    
    /**
     * Generate new JWT token
     */
    public function generateToken(array $customClaims = []): string
    {
        $payload = array_merge([
            'iss' => $this->config['issuer'],
            'aud' => $this->config['audience'],
            'iat' => time(),
            'exp' => time() + $this->config['expiration'],
        ], $customClaims);
        
        // Get secret from config (handle both formats)
        $secret = $this->config['secret'] ?? $this->config[0] ?? null;
        if (!$secret) {
            throw new GitDeployException('JWT secret not configured', 500);
        }
        
        return JWT::encode($payload, $secret, $this->config['algorithm']);
    }
    
    /**
     * Get Authorization header token
     */
    private function getAuthHeader(): ?string
    {
        $headers = getallheaders();
        
        if (!$headers) {
            return null;
        }
        
        // Check for Authorization header (case-insensitive)
        foreach ($headers as $name => $value) {
            if (strtolower($name) === 'authorization') {
                return str_replace('Bearer ', '', $value);
            }
        }
        
        return null;
    }
    
    /**
     * Decode token without validation (for debugging)
     */
    public function decodeToken(string $token): object
    {
        $secret = $this->config['secret'] ?? $this->config[0] ?? null;
        if (!$secret) {
            throw new GitDeployException('JWT secret not configured', 500);
        }
        
        return JWT::decode($token, new Key($secret, $this->config['algorithm']));
    }
}