<?php

use TradersApi\Cache;
use TradersApi\Config;
use PHPUnit\Framework\TestCase;

class CacheTest extends TestCase
{
    private string $cacheDir;
    private Cache $cache;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/binance_cache_test_' . uniqid();
        Config::fake(['STORAGE_PATH' => sys_get_temp_dir()]);
        $this->cache = new Cache($this->cacheDir);
    }

    protected function tearDown(): void
    {
        // Clean up test cache directory
        $files = glob($this->cacheDir . '/*');
        if ($files) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
        @rmdir($this->cacheDir);
    }

    public function testSetAndGet(): void
    {
        $data = ['symbol' => 'BTCUSDT', 'price' => '50000'];

        $this->cache->set('test_key', $data);
        $result = $this->cache->get('test_key', 3600);

        $this->assertSame($data, $result);
    }

    public function testGetNonExistentKey(): void
    {
        $result = $this->cache->get('non_existent', 3600);

        $this->assertNull($result);
    }

    public function testGetExpiredKey(): void
    {
        $data = ['test' => 'data'];
        $this->cache->set('expired_key', $data);

        // Touch the file to make it old
        $file = $this->cacheDir . '/' . md5('expired_key') . '.json';
        touch($file, time() - 7200); // 2 hours ago

        $result = $this->cache->get('expired_key', 3600); // 1 hour TTL

        $this->assertNull($result);
    }

    public function testCacheCreatesDirectory(): void
    {
        $newDir = sys_get_temp_dir() . '/binance_new_cache_test_' . uniqid();

        $this->assertFalse(is_dir($newDir));

        $cache = new Cache($newDir);
        $cache->set('test', ['data' => 'value']);

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

    public function testCacheWithNestedData(): void
    {
        $data = [
            'filters' => [
                ['filterType' => 'PRICE_FILTER', 'minPrice' => '0.01'],
                ['filterType' => 'LOT_SIZE', 'minQty' => '0.001']
            ],
            'symbols' => ['BTCUSDT', 'ETHUSDT']
        ];

        $this->cache->set('nested', $data);
        $result = $this->cache->get('nested', 3600);

        $this->assertSame($data, $result);
    }

    public function testGetWithValidTTL(): void
    {
        $data = ['fresh' => 'data'];
        $this->cache->set('fresh_key', $data);

        $result = $this->cache->get('fresh_key', 3600);

        $this->assertSame($data, $result);
    }

    public function testMultipleKeysSeparate(): void
    {
        $this->cache->set('key1', ['value' => 1]);
        $this->cache->set('key2', ['value' => 2]);

        $result1 = $this->cache->get('key1', 3600);
        $result2 = $this->cache->get('key2', 3600);

        $this->assertSame(['value' => 1], $result1);
        $this->assertSame(['value' => 2], $result2);
    }

    public function testOverwriteExistingKey(): void
    {
        $this->cache->set('overwrite', ['old' => 'data']);
        $this->cache->set('overwrite', ['new' => 'data']);

        $result = $this->cache->get('overwrite', 3600);

        $this->assertSame(['new' => 'data'], $result);
    }

    public function testGetReturnsNullWhenReadFileFails(): void
    {
        $dir = sys_get_temp_dir() . '/cache_read_fail_' . uniqid();

        $cache = new class($dir) extends \TradersApi\Cache {
            protected function readFile(string $path)
            {
                return false;
            }
        };

        $cache->set('mykey', ['data' => 1]);
        $result = $cache->get('mykey', 3600);

        $this->assertNull($result);

        // cleanup
        $files = glob($dir . '/*');
        if ($files) { foreach ($files as $f) { @unlink($f); } }
        @rmdir($dir);
    }

    public function testGetWithCorruptedFile(): void
    {
        // Write invalid JSON to cache file
        $file = $this->cacheDir . '/' . md5('corrupted') . '.json';
        file_put_contents($file, 'not valid json');

        $result = $this->cache->get('corrupted', 3600);

        $this->assertNull($result);
    }

    public function testGetWithNonArrayJson(): void
    {
        // Write a string JSON to cache file (not an array)
        $file = $this->cacheDir . '/' . md5('string_json') . '.json';
        file_put_contents($file, '"just a string"');

        $result = $this->cache->get('string_json', 3600);

        $this->assertNull($result);
    }

    public function testGetDeletesExpiredFile(): void
    {
        $data = ['will' => 'expire'];
        $this->cache->set('expire_test', $data);

        // Make the file old
        $file = $this->cacheDir . '/' . md5('expire_test') . '.json';
        touch($file, time() - 7200);

        // Get should delete the expired file
        $result = $this->cache->get('expire_test', 3600);
        $this->assertNull($result);

        // File should be deleted
        $this->assertFileDoesNotExist($file);
    }

    public function testGetReturnsNullWhenFileContentIsEmpty(): void
    {
        $file = $this->cacheDir . '/' . md5('empty_content') . '.json';
        file_put_contents($file, '');

        $result = $this->cache->get('empty_content', 3600);
        $this->assertNull($result);
    }
}
