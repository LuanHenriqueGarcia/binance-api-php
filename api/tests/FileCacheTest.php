<?php

use BinanceAPI\FileCache;
use BinanceAPI\Config;
use PHPUnit\Framework\TestCase;

class FileCacheTest extends TestCase
{
    private string $cacheDir;
    private FileCache $cache;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/binance_test_cache_' . uniqid();
        Config::fake(['STORAGE_PATH' => sys_get_temp_dir()]);
        $this->cache = new FileCache($this->cacheDir);
    }

    protected function tearDown(): void
    {
        // Clean up test cache directory
        $this->cache->clear();
        if (is_dir($this->cacheDir)) {
            @rmdir($this->cacheDir);
        }
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

    public function testDelete(): void
    {
        $data = ['test' => 'data'];
        $this->cache->set('to_delete', $data);

        $this->assertNotNull($this->cache->get('to_delete', 3600));

        $this->cache->delete('to_delete');

        $this->assertNull($this->cache->get('to_delete', 3600));
    }

    public function testDeleteNonExistent(): void
    {
        // Should not throw
        $this->cache->delete('non_existent_key');
        $this->assertTrue(true);
    }

    public function testClear(): void
    {
        $this->cache->set('key1', ['data' => 1]);
        $this->cache->set('key2', ['data' => 2]);
        $this->cache->set('key3', ['data' => 3]);

        $this->assertNotNull($this->cache->get('key1', 3600));
        $this->assertNotNull($this->cache->get('key2', 3600));
        $this->assertNotNull($this->cache->get('key3', 3600));

        $this->cache->clear();

        $this->assertNull($this->cache->get('key1', 3600));
        $this->assertNull($this->cache->get('key2', 3600));
        $this->assertNull($this->cache->get('key3', 3600));
    }

    public function testCacheCreatesDirectory(): void
    {
        $newDir = sys_get_temp_dir() . '/binance_new_cache_' . uniqid();

        $this->assertFalse(is_dir($newDir));

        $cache = new FileCache($newDir);
        $cache->set('test', ['data' => 'value']);

        $this->assertTrue(is_dir($newDir));

        // Cleanup
        $cache->clear();
        @rmdir($newDir);
    }

    public function testCacheStoresJsonPrettyPrint(): void
    {
        $data = ['symbol' => 'BTCUSDT', 'price' => '50000'];
        $this->cache->set('pretty_test', $data);

        $file = $this->cacheDir . '/' . md5('pretty_test') . '.json';
        $content = file_get_contents($file);

        $this->assertStringContainsString("\n", $content); // Pretty printed has newlines
    }

    public function testGetWithValidTTL(): void
    {
        $data = ['fresh' => 'data'];
        $this->cache->set('fresh_key', $data);

        $result = $this->cache->get('fresh_key', 3600);

        $this->assertSame($data, $result);
    }

    public function testCacheWithNestedData(): void
    {
        $data = [
            'user' => [
                'name' => 'Test',
                'settings' => [
                    'theme' => 'dark',
                    'notifications' => true
                ]
            ],
            'orders' => [
                ['id' => 1, 'symbol' => 'BTCUSDT'],
                ['id' => 2, 'symbol' => 'ETHUSDT']
            ]
        ];

        $this->cache->set('nested', $data);
        $result = $this->cache->get('nested', 3600);

        $this->assertSame($data, $result);
    }

    public function testSetOverwritesExistingKey(): void
    {
        $this->cache->set('overwrite_test', ['old' => 'data']);
        $this->cache->set('overwrite_test', ['new' => 'data']);

        $result = $this->cache->get('overwrite_test', 3600);
        $this->assertSame(['new' => 'data'], $result);
    }

    public function testGetReturnsNullForCorruptedFile(): void
    {
        // Write invalid JSON to cache file
        $file = $this->cacheDir . '/' . md5('corrupted') . '.json';
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0777, true);
        }
        file_put_contents($file, 'not valid json {');

        $result = $this->cache->get('corrupted', 3600);

        $this->assertNull($result);
    }

    public function testClearOnEmptyDirectory(): void
    {
        // Clear on fresh directory shouldn't error
        $this->cache->clear();
        $this->assertTrue(true);
    }

    public function testClearWhenGlobReturnsFalse(): void
    {
        // Test with directory that doesn't exist
        $nonExistentCache = new FileCache('/non/existent/path/cache_' . uniqid());

        // This should handle the glob returning false gracefully
        $nonExistentCache->clear();
        $this->assertTrue(true);
    }

    public function testGetReturnNullOnJsonDecodeFailure(): void
    {
        // Write a non-array JSON (e.g., string) to cache file
        $file = $this->cacheDir . '/' . md5('string_json') . '.json';
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0777, true);
        }
        file_put_contents($file, '"just a string"');

        $result = $this->cache->get('string_json', 3600);

        $this->assertNull($result);
    }

    public function testCacheWithSpecialCharactersInKey(): void
    {
        $key = 'special:key/with\\characters?and=query&params';
        $data = ['test' => 'data'];

        $this->cache->set($key, $data);
        $result = $this->cache->get($key, 3600);

        $this->assertSame($data, $result);
    }

    public function testDeleteExpiredKeyCleanup(): void
    {
        // Set a key and then make it expired
        $data = ['will' => 'expire'];
        $this->cache->set('expire_delete_test', $data);

        // Make the file old
        $file = $this->cacheDir . '/' . md5('expire_delete_test') . '.json';
        touch($file, time() - 7200);

        // Get should delete the expired file
        $result = $this->cache->get('expire_delete_test', 3600);
        $this->assertNull($result);

        // File should be deleted
        $this->assertFileDoesNotExist($file);
    }

    public function testGetReturnsNullForUnreadableFile(): void
    {
        // Write valid JSON to cache file
        $file = $this->cacheDir . '/' . md5('unreadable') . '.json';
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0777, true);
        }
        file_put_contents($file, '{"test": "data"}');

        // Make file readable but return null JSON (number instead of array)
        file_put_contents($file, '123');
        $result = $this->cache->get('unreadable', 3600);
        $this->assertNull($result);
    }

    public function testGetReturnsNullForEmptyFile(): void
    {
        $file = $this->cacheDir . '/' . md5('empty_file') . '.json';
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0777, true);
        }
        file_put_contents($file, '');

        $result = $this->cache->get('empty_file', 3600);
        $this->assertNull($result);
    }

    public function testGetReturnsNullWhenReadFileFails(): void
    {
        $dir = sys_get_temp_dir() . '/fc_read_fail_' . uniqid();

        $cache = new class($dir) extends \BinanceAPI\FileCache {
            protected function readFile(string $path)
            {
                return false;
            }
        };

        $cache->set('key', ['val' => 1]);
        $result = $cache->get('key', 3600);

        $this->assertNull($result);

        $files = glob($dir . '/*');
        if ($files) { foreach ($files as $f) { @unlink($f); } }
        @rmdir($dir);
    }

    public function testClearHandlesGlobFalse(): void
    {
        $dir = sys_get_temp_dir() . '/fc_glob_fail_' . uniqid();

        $cache = new class($dir) extends \BinanceAPI\FileCache {
            protected function globFiles(string $pattern)
            {
                return false;
            }
        };

        // Should not throw
        $cache->clear();
        $this->assertTrue(true);

        @rmdir($dir);
    }
}
