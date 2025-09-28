<?php
/**
 * GitDeploy Webhook Endpoint Example
 * 
 * This file shows how to set up a webhook endpoint for GitDeploy.
 * Place this file in your web-accessible directory and point your GitLab webhook to it.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use BenitoQuib\GitDeploy\WebhookHandler;
use BenitoQuib\GitDeploy\GitDeployConfig;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad(); // Use safeLoad to avoid errors if .env doesn't exist

try {
    // Create configuration from environment variables
    $config = GitDeployConfig::fromEnv();
    
    // Initialize and handle webhook
    $handler = new WebhookHandler($config);
    $handler->handle();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal Server Error',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}