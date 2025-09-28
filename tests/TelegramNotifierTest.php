<?php

declare(strict_types=1);

namespace BenitoQuib\GitDeploy\Tests;

use PHPUnit\Framework\TestCase;
use BenitoQuib\GitDeploy\Notifications\TelegramNotifier;

class TelegramNotifierTest extends TestCase
{
    public function testTelegramNotifierInitialization(): void
    {
        $config = [
            'bot_token' => 'test-token',
            'chat_id' => 'test-chat-id',
            'enabled' => true,
        ];
        
        $notifier = new TelegramNotifier($config);
        
        $this->assertTrue($notifier->isEnabled());
    }
    
    public function testTelegramNotifierDisabledWhenMissingToken(): void
    {
        $config = [
            'chat_id' => 'test-chat-id',
            'enabled' => true,
        ];
        
        $notifier = new TelegramNotifier($config);
        
        $this->assertFalse($notifier->isEnabled());
    }
    
    public function testTelegramNotifierDisabledWhenMissingChatId(): void
    {
        $config = [
            'bot_token' => 'test-token',
            'enabled' => true,
        ];
        
        $notifier = new TelegramNotifier($config);
        
        $this->assertFalse($notifier->isEnabled());
    }
    
    public function testTelegramNotifierDisabledWhenExplicitlyDisabled(): void
    {
        $config = [
            'bot_token' => 'test-token',
            'chat_id' => 'test-chat-id',
            'enabled' => false,
        ];
        
        $notifier = new TelegramNotifier($config);
        
        $this->assertFalse($notifier->isEnabled());
    }
}