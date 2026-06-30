<?php

use TradersApi\RateLimiter;
use TradersApi\Config;
use PHPUnit\Framework\TestCase;

class RateLimiterTest extends TestCase
{
    private string $testDir;
    private RateLimiter $rateLimiter;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/binance_ratelimit_test_' . uniqid();
        Config::fake(['RATE_LIMIT_MAX' => '5', 'RATE_LIMIT_WINDOW' => '60']);
        $this->rateLimiter = new RateLimiter($this->testDir, 5, 60);
    }

    protected function tearDown(): void
    {
        // Clean up test directory
        $files = glob($this->testDir . '/*');
        if ($files) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
        @rmdir($this->testDir);
    }

    public function testHitAllowsFirstRequest(): void
    {
        $result = $this->rateLimiter->hit('test_key');

        $this->assertTrue($result['allowed']);
        $this->assertNull($result['retryAfter']);
    }

    public function testHitAllowsMultipleRequestsWithinLimit(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $result = $this->rateLimiter->hit('multiple_test');
            $this->assertTrue($result['allowed']);
        }
    }

    public function testHitBlocksWhenLimitExceeded(): void
    {
        // Create rate limiter with low limit
        $limiter = new RateLimiter($this->testDir, 3, 60);

        // Hit 3 times (should all be allowed)
        for ($i = 0; $i < 3; $i++) {
            $result = $limiter->hit('limit_test');
            $this->assertTrue($result['allowed'], "Request $i should be allowed");
        }

        // 4th hit should be blocked
        $result = $limiter->hit('limit_test');
        $this->assertFalse($result['allowed']);
        $this->assertNotNull($result['retryAfter']);
        $this->assertGreaterThan(0, $result['retryAfter']);
    }

    public function testHitDifferentKeysAreIndependent(): void
    {
        $limiter = new RateLimiter($this->testDir, 2, 60);

        // Hit key1 twice (reaches limit)
        $limiter->hit('key1');
        $limiter->hit('key1');
        $result1 = $limiter->hit('key1');

        // key1 should be blocked
        $this->assertFalse($result1['allowed']);

        // key2 should still be allowed
        $result2 = $limiter->hit('key2');
        $this->assertTrue($result2['allowed']);
    }

    public function testRateLimiterCreatesDirectory(): void
    {
        $newDir = sys_get_temp_dir() . '/binance_ratelimit_new_' . uniqid();

        $this->assertFalse(is_dir($newDir));

        $limiter = new RateLimiter($newDir, 10, 60);
        $limiter->hit('test');

        $this->assertTrue(is_dir($newDir));

        // Cleanup
        $files = glob($newDir . '/*');
        if ($files) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
        @rmdir($newDir);
    }

    public function testRetryAfterIsPositive(): void
    {
        $limiter = new RateLimiter($this->testDir, 1, 60);

        $limiter->hit('retry_test');
        $result = $limiter->hit('retry_test');

        $this->assertFalse($result['allowed']);
        $this->assertGreaterThanOrEqual(1, $result['retryAfter']);
        $this->assertLessThanOrEqual(60, $result['retryAfter']);
    }

    public function testHitWithNullParams(): void
    {
        // Test with all null params (uses Config defaults)
        Config::fake([
            'STORAGE_PATH' => $this->testDir,
            'RATE_LIMIT_MAX' => '10',
            'RATE_LIMIT_WINDOW' => '30'
        ]);

        $limiter = new RateLimiter($this->testDir);
        $result = $limiter->hit('null_params_test');

        $this->assertTrue($result['allowed']);
        $this->assertNull($result['retryAfter']);
    }

    public function testRateLimiterWithExistingData(): void
    {
        $limiter = new RateLimiter($this->testDir, 5, 60);

        // Create some hits
        $limiter->hit('existing_data');
        $limiter->hit('existing_data');

        // Create new limiter instance (simulating next request)
        $limiter2 = new RateLimiter($this->testDir, 5, 60);
        $result = $limiter2->hit('existing_data');

        $this->assertTrue($result['allowed']);
    }

    public function testRateLimiterRespectsWindow(): void
    {
        // Very short window
        $limiter = new RateLimiter($this->testDir, 2, 1);

        $limiter->hit('window_test');
        $limiter->hit('window_test');
        $result = $limiter->hit('window_test');

        $this->assertFalse($result['allowed']);
        $this->assertNotNull($result['retryAfter']);
        $this->assertLessThanOrEqual(1, $result['retryAfter']);
    }

    public function testHitReturnsTrueWhenFopenFails(): void
    {
        $limiter = new class($this->testDir, 10, 60) extends \TradersApi\RateLimiter {
            protected function openFile(string $file)
            {
                return false;
            }
        };

        $result = $limiter->hit('fopen_fail_key');

        $this->assertTrue($result['allowed']);
        $this->assertNull($result['retryAfter']);
    }
}
