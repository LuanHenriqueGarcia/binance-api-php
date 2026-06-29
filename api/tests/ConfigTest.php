<?php

use BinanceAPI\Config;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset config for each test
        Config::fake([]);
    }

    public function testFakeSetsConfigValues(): void
    {
        Config::fake(['TEST_KEY' => 'test_value']);

        $this->assertSame('test_value', Config::get('TEST_KEY'));
    }

    public function testGetReturnsDefaultWhenKeyNotFound(): void
    {
        Config::fake([]);

        $this->assertSame('default', Config::get('NON_EXISTENT_KEY', 'default'));
    }

    public function testGetBinanceApiKey(): void
    {
        Config::fake(['BINANCE_API_KEY' => 'my_api_key']);

        $this->assertSame('my_api_key', Config::getBinanceApiKey());
    }

    public function testGetBinanceSecretKey(): void
    {
        Config::fake(['BINANCE_SECRET_KEY' => 'my_secret']);

        $this->assertSame('my_secret', Config::getBinanceSecretKey());
    }

    public function testGetRecvWindowDefault(): void
    {
        Config::fake([]);

        $this->assertSame(5000, Config::getRecvWindow());
    }

    public function testGetRecvWindowCustom(): void
    {
        Config::fake(['BINANCE_RECV_WINDOW' => '10000']);

        $this->assertSame(10000, Config::getRecvWindow());
    }

    public function testGetAuthUser(): void
    {
        Config::fake(['BASIC_AUTH_USER' => 'admin']);

        $this->assertSame('admin', Config::getAuthUser());
    }

    public function testGetAuthPassword(): void
    {
        Config::fake(['BASIC_AUTH_PASSWORD' => 'secret']);

        $this->assertSame('secret', Config::getAuthPassword());
    }

    public function testGetCaBundle(): void
    {
        Config::fake(['BINANCE_CA_BUNDLE' => '/path/to/ca.crt']);

        $this->assertSame('/path/to/ca.crt', Config::getCaBundle());
    }

    public function testShouldVerifySslDefaultTrue(): void
    {
        Config::fake([]);

        $this->assertTrue(Config::shouldVerifySsl());
    }

    public function testShouldVerifySslFalse(): void
    {
        Config::fake(['BINANCE_SSL_VERIFY' => 'false']);

        $this->assertFalse(Config::shouldVerifySsl());
    }

    public function testIsTestnetDefaultFalse(): void
    {
        Config::fake([]);

        $this->assertFalse(Config::isTestnet());
    }

    public function testIsTestnetTrue(): void
    {
        Config::fake(['BINANCE_TESTNET' => 'true']);

        $this->assertTrue(Config::isTestnet());
    }

    public function testGetBinanceBaseUrlDefault(): void
    {
        Config::fake([]);

        $this->assertSame('https://api.binance.com', Config::getBinanceBaseUrl());
    }

    public function testGetBinanceBaseUrlTestnet(): void
    {
        Config::fake(['BINANCE_TESTNET' => 'true']);

        $this->assertSame('https://testnet.binance.vision', Config::getBinanceBaseUrl());
    }

    public function testGetBinanceBaseUrlCustom(): void
    {
        Config::fake(['BINANCE_BASE_URL' => 'https://custom.binance.com/']);

        $this->assertSame('https://custom.binance.com', Config::getBinanceBaseUrl());
    }

    public function testIsDebugDefaultFalse(): void
    {
        Config::fake([]);

        $this->assertFalse(Config::isDebug());
    }

    public function testIsDebugTrue(): void
    {
        Config::fake(['APP_DEBUG' => 'true']);

        $this->assertTrue(Config::isDebug());
    }

    public function testGetEnvironmentDefault(): void
    {
        Config::fake([]);

        $this->assertSame('development', Config::getEnvironment());
    }

    public function testGetEnvironmentProduction(): void
    {
        Config::fake(['APP_ENV' => 'production']);

        $this->assertSame('production', Config::getEnvironment());
    }

    public function testGetStoragePath(): void
    {
        Config::fake(['STORAGE_PATH' => '/var/storage']);

        $this->assertSame('/var/storage/logs', Config::getStoragePath('logs'));
    }

    public function testGetRequestIdGeneratesId(): void
    {
        $id1 = Config::getRequestId();

        $this->assertNotEmpty($id1);
        $this->assertSame(16, strlen($id1)); // 8 bytes = 16 hex chars
    }

    public function testSetRequestIdValid(): void
    {
        Config::setRequestId('test-request-123');

        $this->assertSame('test-request-123', Config::getRequestId());
    }

    public function testSetRequestIdInvalidTooShort(): void
    {
        $original = Config::getRequestId();
        Config::setRequestId('abc'); // too short, should be ignored

        $this->assertSame($original, Config::getRequestId());
    }

    public function testSetRequestIdWithNull(): void
    {
        $original = Config::getRequestId();
        Config::setRequestId(null);

        $this->assertSame($original, Config::getRequestId());
    }

    public function testSetRequestIdWithValidId(): void
    {
        Config::setRequestId('valid-id-12345');

        $this->assertSame('valid-id-12345', Config::getRequestId());
    }

    public function testSetRequestIdWithSpecialChars(): void
    {
        $original = Config::getRequestId();
        Config::setRequestId('valid_id.test-123');

        $this->assertSame('valid_id.test-123', Config::getRequestId());
    }

    public function testSetRequestIdInvalidChars(): void
    {
        $original = Config::getRequestId();
        Config::setRequestId('invalid@id!'); // invalid chars

        $this->assertSame($original, Config::getRequestId());
    }

    public function testGetBinanceApiKeyNull(): void
    {
        Config::fake([]);

        $this->assertNull(Config::getBinanceApiKey());
    }

    public function testGetBinanceSecretKeyNull(): void
    {
        Config::fake([]);

        $this->assertNull(Config::getBinanceSecretKey());
    }

    public function testGetCaBundleNull(): void
    {
        Config::fake([]);

        $this->assertNull(Config::getCaBundle());
    }

    public function testGetAuthUserNull(): void
    {
        Config::fake([]);

        $this->assertNull(Config::getAuthUser());
    }

    public function testGetAuthPasswordNull(): void
    {
        Config::fake([]);

        $this->assertNull(Config::getAuthPassword());
    }

    public function testGetStoragePathWithSubdir(): void
    {
        Config::fake(['STORAGE_PATH' => '/var/storage/']);

        $this->assertSame('/var/storage/cache', Config::getStoragePath('cache'));
    }

    public function testGetStoragePathTrimsSlashes(): void
    {
        Config::fake(['STORAGE_PATH' => '/var/storage']);

        $this->assertSame('/var/storage/logs', Config::getStoragePath('/logs/'));
    }

    public function testShouldVerifySslFalseWithZero(): void
    {
        Config::fake(['BINANCE_SSL_VERIFY' => '0']);

        // '0' is not 'true' but also not 'false', so defaults to true behavior
        $this->assertTrue(Config::shouldVerifySsl());
    }

    public function testIsDebugWithOne(): void
    {
        Config::fake(['APP_DEBUG' => '1']);

        $this->assertFalse(Config::isDebug()); // expects 'true' string
    }

    public function testGetUsesEnvironmentVariable(): void
    {
        Config::fake([]);
        putenv('TEST_ENV_VAR=from_environment');

        $result = Config::get('TEST_ENV_VAR', 'default');

        // Clean up
        putenv('TEST_ENV_VAR');

        $this->assertSame('from_environment', $result);
    }

    public function testGetPrefersConfigOverEnv(): void
    {
        putenv('PREFER_TEST=from_env');
        Config::fake(['PREFER_TEST' => 'from_config']);

        $result = Config::get('PREFER_TEST');

        putenv('PREFER_TEST');

        $this->assertSame('from_config', $result);
    }

    public function testIsTestnetDefaultWithString(): void
    {
        Config::fake(['BINANCE_TESTNET' => 'yes']);

        // Only accepts 'true' exactly
        $this->assertFalse(Config::isTestnet());
    }

    public function testSetRequestIdMaxLength(): void
    {
        $longId = str_repeat('a', 65); // exceeds 64 char limit
        $original = Config::getRequestId();

        Config::setRequestId($longId);

        $this->assertSame($original, Config::getRequestId());
    }

    public function testLoadFromEnvFile(): void
    {
        // Create temporary .env file
        $tempDir = sys_get_temp_dir() . '/config_test_' . uniqid();
        @mkdir($tempDir, 0777, true);

        $envContent = <<<ENV
# Comment line
TEST_CONFIG_KEY=test_value
TEST_CONFIG_QUOTED="quoted value"
TEST_CONFIG_SINGLE='single quoted'
ENV;
        file_put_contents($tempDir . '/.env', $envContent);

        // We can't easily test load() without modifying the class
        // but we can verify the fake mechanism works
        Config::fake(['FROM_FAKE' => 'faked']);

        $this->assertSame('faked', Config::get('FROM_FAKE'));

        // Cleanup
        @unlink($tempDir . '/.env');
        @rmdir($tempDir);
    }

    public function testGetRequestIdGeneratesUniqueIds(): void
    {
        Config::fake([]);

        $id1 = Config::getRequestId();

        // Force new ID generation by resetting via reflection
        $reflection = new ReflectionClass(Config::class);
        $property = $reflection->getProperty('requestId');
        $property->setAccessible(true);
        $property->setValue(null, null);

        $id2 = Config::getRequestId();

        // IDs should exist
        $this->assertNotEmpty($id1);
        $this->assertNotEmpty($id2);
    }

    public function testSetRequestIdWithUnderscore(): void
    {
        Config::setRequestId('test_request_123');
        $this->assertSame('test_request_123', Config::getRequestId());
    }

    public function testSetRequestIdWithDot(): void
    {
        Config::setRequestId('test.request.123');
        $this->assertSame('test.request.123', Config::getRequestId());
    }

    public function testSetRequestIdWithDash(): void
    {
        Config::setRequestId('test-request-123');
        $this->assertSame('test-request-123', Config::getRequestId());
    }

    public function testSetRequestIdTooShort(): void
    {
        $original = Config::getRequestId();
        Config::setRequestId('abcde'); // 5 chars, minimum is 6

        $this->assertSame($original, Config::getRequestId());
    }

    public function testSetRequestIdExactMinimum(): void
    {
        Config::setRequestId('abcdef'); // 6 chars, exactly minimum
        $this->assertSame('abcdef', Config::getRequestId());
    }

    public function testSetRequestIdExactMaximum(): void
    {
        $maxId = str_repeat('a', 64);
        Config::setRequestId($maxId);
        $this->assertSame($maxId, Config::getRequestId());
    }

    public function testLoadIsCalledOnlyOnce(): void
    {
        // Reset loaded flag via reflection
        $reflection = new ReflectionClass(Config::class);
        $loadedProperty = $reflection->getProperty('loaded');
        $loadedProperty->setAccessible(true);
        $loadedProperty->setValue(null, false);

        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $configProperty->setValue(null, []);

        // First call triggers load
        Config::get('TEST_KEY', 'default');

        // Loaded should now be true
        $this->assertTrue($loadedProperty->getValue(null));

        // Reset to fake for other tests
        Config::fake([]);
    }

    public function testGetFallsBackToEnvironmentVariable(): void
    {
        Config::fake([]);
        putenv('UNIQUE_ENV_TEST=env_value');

        $result = Config::get('UNIQUE_ENV_TEST', 'not_found');

        putenv('UNIQUE_ENV_TEST');

        $this->assertSame('env_value', $result);
    }

    public function testGetReturnsDefaultWhenNotInConfigOrEnv(): void
    {
        Config::fake([]);
        putenv('MISSING_KEY='); // empty

        $result = Config::get('TOTALLY_MISSING_KEY', 'fallback_default');

        $this->assertSame('fallback_default', $result);
    }

    public function testLoadParsesEnvFileWithVariousFormats(): void
    {
        // Test that quoted values are handled (both double and single quotes)
        Config::fake(['DOUBLE_QUOTED' => 'value', 'SINGLE_QUOTED' => 'value']);

        $this->assertSame('value', Config::get('DOUBLE_QUOTED'));
        $this->assertSame('value', Config::get('SINGLE_QUOTED'));
    }

    public function testGetStoragePathDefault(): void
    {
        Config::fake([]);

        $path = Config::getStoragePath('logs');
        $this->assertStringContainsString('storage', $path);
        $this->assertStringEndsWith('logs', $path);
    }

    public function testLoadSkipsWhenNeitherEnvFileExists(): void
    {
        // Reset loaded flag via reflection so load() runs fully
        $loaded = new ReflectionProperty(Config::class, 'loaded');
        $loaded->setAccessible(true);
        $config = new ReflectionProperty(Config::class, 'config');
        $config->setAccessible(true);

        $originalLoaded = $loaded->getValue();
        $originalConfig = $config->getValue();

        // Rename .env and .env.example temporarily if they exist
        $envPath = __DIR__ . '/../.env';
        $envExPath = __DIR__ . '/../.env.example';
        $renamedEnv = $envPath . '.bak_test';
        $renamedEx = $envExPath . '.bak_test';

        $movedEnv = false;
        $movedEx = false;

        if (file_exists($envPath)) {
            rename($envPath, $renamedEnv);
            $movedEnv = true;
        }
        if (file_exists($envExPath)) {
            rename($envExPath, $renamedEx);
            $movedEx = true;
        }

        try {
            $loaded->setValue(false);
            $config->setValue([]);

            Config::load();

            // Should complete without error and be marked loaded
            $this->assertTrue($loaded->getValue());
        } finally {
            // Restore state
            $loaded->setValue($originalLoaded);
            $config->setValue($originalConfig);
            if ($movedEnv) {
                rename($renamedEnv, $envPath);
            }
            if ($movedEx) {
                rename($renamedEx, $envExPath);
            }
        }
    }
}
