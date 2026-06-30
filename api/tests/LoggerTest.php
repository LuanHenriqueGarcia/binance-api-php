<?php

use TradersApi\Logger;
use TradersApi\Config;
use PHPUnit\Framework\TestCase;

class LoggerTest extends TestCase
{
    private string $logFile;

    protected function setUp(): void
    {
        $this->logFile = sys_get_temp_dir() . '/binance_test_' . uniqid() . '.log';
        Config::fake([
            'APP_DEBUG' => 'true',
            'APP_LOG_FILE' => $this->logFile
        ]);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->logFile)) {
            @unlink($this->logFile);
        }
    }

    public function testInfoLogsWhenDebugEnabled(): void
    {
        Config::fake([
            'APP_DEBUG' => 'true',
            'APP_LOG_FILE' => $this->logFile
        ]);

        Logger::info(['message' => 'Test info message']);

        $this->assertFileExists($this->logFile);
        $content = file_get_contents($this->logFile);
        $this->assertStringContainsString('Test info message', $content);
        $this->assertStringContainsString('"level":"info"', $content);
    }

    public function testInfoDoesNotLogWhenDebugDisabled(): void
    {
        Config::fake([
            'APP_DEBUG' => 'false',
            'APP_LOG_FILE' => $this->logFile
        ]);

        Logger::info(['message' => 'Should not appear']);

        $this->assertFileDoesNotExist($this->logFile);
    }

    public function testErrorAlwaysLogs(): void
    {
        Config::fake([
            'APP_DEBUG' => 'false',
            'APP_LOG_FILE' => $this->logFile
        ]);

        Logger::error(['message' => 'Error message']);

        $this->assertFileExists($this->logFile);
        $content = file_get_contents($this->logFile);
        $this->assertStringContainsString('Error message', $content);
        $this->assertStringContainsString('"level":"error"', $content);
    }

    public function testMasksApiKey(): void
    {
        Config::fake([
            'APP_DEBUG' => 'true',
            'APP_LOG_FILE' => $this->logFile
        ]);

        Logger::info([
            'api_key' => 'vmPUZE6mv9SD5VNHk4HlWFsOr6aKE2zvsw0MuIgwCIPy6utIco14y7Ju91duEh8A'
        ]);

        $content = file_get_contents($this->logFile);

        // Should NOT contain full API key
        $this->assertStringNotContainsString('vmPUZE6mv9SD5VNHk4HlWFsOr6aKE2zvsw0MuIgwCIPy6utIco14y7Ju91duEh8A', $content);
        // Should contain masked version
        $this->assertStringContainsString('vmPU****', $content);
    }

    public function testMasksSecretKey(): void
    {
        Config::fake([
            'APP_DEBUG' => 'true',
            'APP_LOG_FILE' => $this->logFile
        ]);

        Logger::info([
            'secret_key' => 'NhqPtmdSJYdKjVHjA7PZj4Mge3R5YNiP1e3UZjInClVN65XAbvqqM6A7H5fATj0j'
        ]);

        $content = file_get_contents($this->logFile);

        $this->assertStringNotContainsString('NhqPtmdSJYdKjVHjA7PZj4Mge3R5YNiP1e3UZjInClVN65XAbvqqM6A7H5fATj0j', $content);
        $this->assertStringContainsString('NhqP****', $content);
    }

    public function testLogContainsTimestamp(): void
    {
        Config::fake([
            'APP_DEBUG' => 'true',
            'APP_LOG_FILE' => $this->logFile
        ]);

        Logger::info(['test' => 'timestamp']);

        $content = file_get_contents($this->logFile);
        $this->assertStringContainsString('"ts":', $content);
    }

    public function testLogCreatesDirectory(): void
    {
        $newDir = sys_get_temp_dir() . '/binance_log_dir_' . uniqid();
        $newLogFile = $newDir . '/app.log';

        Config::fake([
            'APP_DEBUG' => 'true',
            'APP_LOG_FILE' => $newLogFile
        ]);

        Logger::info(['message' => 'Create dir test']);

        $this->assertDirectoryExists($newDir);
        $this->assertFileExists($newLogFile);

        // Cleanup
        @unlink($newLogFile);
        @rmdir($newDir);
    }

    public function testMasksXMbxApiKey(): void
    {
        Config::fake([
            'APP_DEBUG' => 'true',
            'APP_LOG_FILE' => $this->logFile
        ]);

        Logger::info([
            'X-MBX-APIKEY' => 'abcdefghijklmnop1234567890'
        ]);

        $content = file_get_contents($this->logFile);

        // Should NOT contain full API key
        $this->assertStringNotContainsString('abcdefghijklmnop1234567890', $content);
        // Should contain masked version
        $this->assertStringContainsString('abcd****', $content);
    }

    public function testErrorLogsWithoutLogFile(): void
    {
        Config::fake([
            'APP_DEBUG' => 'false'
            // No APP_LOG_FILE - should use error_log()
        ]);

        // This should not throw
        Logger::error(['message' => 'Error without file']);

        // We can't easily verify error_log was called, but test shouldn't fail
        $this->assertTrue(true);
    }

    public function testInfoDoesNotLogWithoutDebugAndFile(): void
    {
        Config::fake([
            'APP_DEBUG' => 'false'
            // No APP_LOG_FILE
        ]);

        Logger::info(['message' => 'Should not log']);

        // No file should be created
        $this->assertFileDoesNotExist($this->logFile);
    }

    public function testLogWithMultipleContextItems(): void
    {
        Config::fake([
            'APP_DEBUG' => 'true',
            'APP_LOG_FILE' => $this->logFile
        ]);

        Logger::info([
            'event' => 'test_event',
            'user_id' => 123,
            'action' => 'create_order',
            'symbol' => 'BTCUSDT'
        ]);

        $content = file_get_contents($this->logFile);
        $this->assertStringContainsString('test_event', $content);
        $this->assertStringContainsString('123', $content);
        $this->assertStringContainsString('create_order', $content);
        $this->assertStringContainsString('BTCUSDT', $content);
    }

    public function testMasksKeysCaseInsensitively(): void
    {
        Config::fake([
            'APP_DEBUG' => 'true',
            'APP_LOG_FILE' => $this->logFile
        ]);

        Logger::info([
            'API_KEY' => 'UPPERcaseSecretValue123456',
            'Authorization' => 'Bearer tok_abcdef1234567890',
        ]);

        $content = file_get_contents($this->logFile);

        // Mascarado mesmo com case diferente e para a chave Authorization
        $this->assertStringNotContainsString('UPPERcaseSecretValue123456', $content);
        $this->assertStringContainsString('UPPE****', $content);
        $this->assertStringNotContainsString('Bearer tok_abcdef1234567890', $content);
        $this->assertStringContainsString('Bear****', $content);
    }

    public function testMaskDoesNotAffectNonSensitiveKeys(): void
    {
        Config::fake([
            'APP_DEBUG' => 'true',
            'APP_LOG_FILE' => $this->logFile
        ]);

        Logger::info([
            'symbol' => 'BTCUSDT',
            'price' => '50000.00',
            'quantity' => '0.001'
        ]);

        $content = file_get_contents($this->logFile);
        // Non-sensitive data should NOT be masked
        $this->assertStringContainsString('BTCUSDT', $content);
        $this->assertStringContainsString('50000.00', $content);
        $this->assertStringContainsString('0.001', $content);
    }
}
