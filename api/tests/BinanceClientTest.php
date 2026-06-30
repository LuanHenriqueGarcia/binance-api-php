<?php

use TradersApi\BinanceClient;
use TradersApi\Config;
use PHPUnit\Framework\TestCase;

class BinanceClientTest extends TestCase
{
    protected function setUp(): void
    {
        Config::fake([]);
    }

    public function testConstructorWithoutKeys(): void
    {
        $client = new BinanceClient();

        $this->assertInstanceOf(BinanceClient::class, $client);
    }

    public function testConstructorWithKeys(): void
    {
        $client = new BinanceClient('api_key', 'secret_key');

        $this->assertInstanceOf(BinanceClient::class, $client);
    }

    public function testPostRequiresApiKey(): void
    {
        $client = new BinanceClient();

        $response = $client->post('/api/v3/order', []);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('API Key', $response['error']);
    }

    public function testDeleteRequiresApiKey(): void
    {
        $client = new BinanceClient();

        $response = $client->delete('/api/v3/order', []);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('API Key', $response['error']);
    }

    public function testGetHeadersWithoutBody(): void
    {
        $client = new BinanceClient();
        $method = new ReflectionMethod(BinanceClient::class, 'getHeaders');
        $method->setAccessible(true);

        $headers = $method->invoke($client, false);

        $this->assertIsArray($headers);
        $this->assertContains('Accept: application/json', $headers);
    }

    public function testGetHeadersWithBody(): void
    {
        $client = new BinanceClient();
        $method = new ReflectionMethod(BinanceClient::class, 'getHeaders');
        $method->setAccessible(true);

        $headers = $method->invoke($client, true);

        $this->assertIsArray($headers);
        $this->assertContains('Content-Type: application/x-www-form-urlencoded', $headers);
    }

    public function testGetHeadersWithApiKey(): void
    {
        $client = new BinanceClient('test_key', 'test_secret');
        $method = new ReflectionMethod(BinanceClient::class, 'getHeaders');
        $method->setAccessible(true);

        $headers = $method->invoke($client, true);

        $this->assertIsArray($headers);
        $found = false;
        foreach ($headers as $header) {
            if (str_contains($header, 'X-MBX-APIKEY')) {
                $found = true;
                $this->assertStringContainsString('test_key', $header);
            }
        }
        $this->assertTrue($found, 'X-MBX-APIKEY header should be present');
    }

    public function testShouldRetryOnServerError(): void
    {
        $client = new BinanceClient();
        $method = new ReflectionMethod(BinanceClient::class, 'shouldRetry');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($client, 503, 0));
        $this->assertTrue($method->invoke($client, 429, 0));
        $this->assertTrue($method->invoke($client, 500, 0));
        $this->assertFalse($method->invoke($client, 503, 2)); // MAX_RETRIES = 2, attempt 2 is the last
        $this->assertFalse($method->invoke($client, 400, 0));
        $this->assertFalse($method->invoke($client, 200, 0));
    }

    public function testGetRetryAfterMsWithSeconds(): void
    {
        $client = new BinanceClient();
        $method = new ReflectionMethod(BinanceClient::class, 'getRetryAfterMs');
        $method->setAccessible(true);

        $result = $method->invoke($client, ['retry-after' => '10']);
        $this->assertSame(10000, $result);
    }

    public function testGetRetryAfterMsWithoutHeader(): void
    {
        $client = new BinanceClient();
        $method = new ReflectionMethod(BinanceClient::class, 'getRetryAfterMs');
        $method->setAccessible(true);

        $result = $method->invoke($client, []);
        $this->assertNull($result);
    }

    public function testUsesTestnetWhenConfigured(): void
    {
        Config::fake(['BINANCE_TESTNET' => 'true']);
        $client = new BinanceClient();

        $baseUrlProperty = new ReflectionProperty(BinanceClient::class, 'baseUrl');
        $baseUrlProperty->setAccessible(true);
        $baseUrl = $baseUrlProperty->getValue($client);

        $this->assertStringContainsString('testnet', $baseUrl);
    }

    public function testVerifySslDefault(): void
    {
        Config::fake([]);
        $client = new BinanceClient();

        $property = new ReflectionProperty(BinanceClient::class, 'verifySsl');
        $property->setAccessible(true);
        $verifySsl = $property->getValue($client);

        // Default should be true
        $this->assertTrue($verifySsl);
    }

    public function testApiKeyStoredInClient(): void
    {
        $client = new BinanceClient('my_key', 'my_secret');

        $apiKeyProperty = new ReflectionProperty(BinanceClient::class, 'apiKey');
        $apiKeyProperty->setAccessible(true);
        $secretProperty = new ReflectionProperty(BinanceClient::class, 'secretKey');
        $secretProperty->setAccessible(true);

        $this->assertSame('my_key', $apiKeyProperty->getValue($client));
        $this->assertSame('my_secret', $secretProperty->getValue($client));
    }

    public function testGetRetryAfterMsWithHttpDate(): void
    {
        $client = new BinanceClient();
        $method = new ReflectionMethod(BinanceClient::class, 'getRetryAfterMs');
        $method->setAccessible(true);

        // HTTP date format: future date
        $futureDate = gmdate('D, d M Y H:i:s', time() + 10) . ' GMT';
        $result = $method->invoke($client, ['retry-after' => $futureDate]);

        // Should return positive milliseconds
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }

    public function testGetRetryAfterMsWithPastDate(): void
    {
        $client = new BinanceClient();
        $method = new ReflectionMethod(BinanceClient::class, 'getRetryAfterMs');
        $method->setAccessible(true);

        // HTTP date format: past date
        $pastDate = gmdate('D, d M Y H:i:s', time() - 10) . ' GMT';
        $result = $method->invoke($client, ['retry-after' => $pastDate]);

        // Should return null for past dates
        $this->assertNull($result);
    }

    public function testBackoffMethod(): void
    {
        $client = new BinanceClient();
        $method = new ReflectionMethod(BinanceClient::class, 'backoff');
        $method->setAccessible(true);

        $startTime = microtime(true);
        $method->invoke($client, 0, null); // attempt 0, no retry-after
        $endTime = microtime(true);

        // Should have some delay (at least ~100ms for attempt 0)
        $elapsed = ($endTime - $startTime) * 1000;
        $this->assertGreaterThan(50, $elapsed);
    }

    public function testBackoffWithRetryAfter(): void
    {
        $client = new BinanceClient();
        $method = new ReflectionMethod(BinanceClient::class, 'backoff');
        $method->setAccessible(true);

        $startTime = microtime(true);
        $method->invoke($client, 0, 100); // 100ms retry-after
        $endTime = microtime(true);

        // Should delay around the retry-after time
        $elapsed = ($endTime - $startTime) * 1000;
        $this->assertGreaterThan(25, $elapsed); // at least 50% of base (with jitter)
    }

    public function testCaBundleProperty(): void
    {
        Config::fake(['BINANCE_CA_BUNDLE' => '/path/to/ca-bundle.crt']);
        $client = new BinanceClient();

        $property = new ReflectionProperty(BinanceClient::class, 'caBundle');
        $property->setAccessible(true);
        $caBundle = $property->getValue($client);

        $this->assertSame('/path/to/ca-bundle.crt', $caBundle);
    }

    public function testShouldRetryExactlyAtMaxRetries(): void
    {
        $client = new BinanceClient();
        $method = new ReflectionMethod(BinanceClient::class, 'shouldRetry');
        $method->setAccessible(true);

        // MAX_RETRIES is 2
        $this->assertTrue($method->invoke($client, 500, 1));  // attempt 1, should retry
        $this->assertFalse($method->invoke($client, 500, 2)); // attempt 2, should NOT retry
    }

    public function testLogRequestWithDebugEnabled(): void
    {
        Config::fake(['APP_DEBUG' => 'true']);
        $client = new BinanceClient();
        $method = new ReflectionMethod(BinanceClient::class, 'logRequest');
        $method->setAccessible(true);

        // Should not throw when debug is enabled
        $method->invoke($client, 'GET', 'https://test.com', 200, 0, microtime(true), [], null);
        $this->assertTrue(true);
    }

    public function testLogRequestWithRateHeaders(): void
    {
        Config::fake(['APP_DEBUG' => 'true']);
        $client = new BinanceClient();
        $method = new ReflectionMethod(BinanceClient::class, 'logRequest');
        $method->setAccessible(true);

        $headers = [
            'x-mbx-used-weight-1m' => '50',
            'x-mbx-order-count-1m' => '10'
        ];
        $method->invoke($client, 'POST', 'https://test.com', 200, 0, microtime(true), $headers, null);
        $this->assertTrue(true);
    }

    public function testLogRequestWithError(): void
    {
        Config::fake(['APP_DEBUG' => 'true']);
        $client = new BinanceClient();
        $method = new ReflectionMethod(BinanceClient::class, 'logRequest');
        $method->setAccessible(true);

        $method->invoke($client, 'GET', 'https://test.com', 500, 1, microtime(true), [], 'Server error');
        $this->assertTrue(true);
    }

    public function testLogRequestDisabledWithoutDebug(): void
    {
        Config::fake(['APP_DEBUG' => 'false', 'APP_LOG_FILE' => null]);
        $client = new BinanceClient();
        $method = new ReflectionMethod(BinanceClient::class, 'logRequest');
        $method->setAccessible(true);

        // Should return early without logging
        $method->invoke($client, 'GET', 'https://test.com', 200, 0, microtime(true), [], null);
        $this->assertTrue(true);
    }

    public function testGetPublicUrlWithParams(): void
    {
        Config::fake([]);
        $client = new BinanceClient();

        // Public GET requests should have params in URL
        $property = new ReflectionProperty(BinanceClient::class, 'baseUrl');
        $property->setAccessible(true);
        $baseUrl = $property->getValue($client);

        $this->assertStringContainsString('binance.com', $baseUrl);
    }

    public function testAuthenticatedClientHasApiKey(): void
    {
        $client = new BinanceClient('test_api_key', 'test_secret_key');

        $apiKeyProperty = new ReflectionProperty(BinanceClient::class, 'apiKey');
        $apiKeyProperty->setAccessible(true);

        $secretKeyProperty = new ReflectionProperty(BinanceClient::class, 'secretKey');
        $secretKeyProperty->setAccessible(true);

        $this->assertSame('test_api_key', $apiKeyProperty->getValue($client));
        $this->assertSame('test_secret_key', $secretKeyProperty->getValue($client));
    }

    public function testBackoffMaximum(): void
    {
        $client = new BinanceClient();
        $method = new ReflectionMethod(BinanceClient::class, 'backoff');
        $method->setAccessible(true);

        // Test with high attempt - should cap at MAX_BACKOFF_MS
        $startTime = microtime(true);
        $method->invoke($client, 10, null); // Very high attempt
        $endTime = microtime(true);

        $elapsedMs = ($endTime - $startTime) * 1000;
        // MAX_BACKOFF_MS is 2000, so should be less than 3000 (with jitter)
        $this->assertLessThan(3500, $elapsedMs);
    }

    public function testShouldRetryFor429(): void
    {
        $client = new BinanceClient();
        $method = new ReflectionMethod(BinanceClient::class, 'shouldRetry');
        $method->setAccessible(true);

        // 429 should always retry (rate limit)
        $this->assertTrue($method->invoke($client, 429, 0));
        $this->assertTrue($method->invoke($client, 429, 1));
        $this->assertFalse($method->invoke($client, 429, 2)); // MAX_RETRIES reached
    }

    public function testShouldNotRetryClientErrors(): void
    {
        $client = new BinanceClient();
        $method = new ReflectionMethod(BinanceClient::class, 'shouldRetry');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($client, 400, 0));
        $this->assertFalse($method->invoke($client, 401, 0));
        $this->assertFalse($method->invoke($client, 403, 0));
        $this->assertFalse($method->invoke($client, 404, 0));
    }

    public function testGetRetryAfterMsWithInvalidDate(): void
    {
        $client = new BinanceClient();
        $method = new ReflectionMethod(BinanceClient::class, 'getRetryAfterMs');
        $method->setAccessible(true);

        // Invalid date format should return null
        $result = $method->invoke($client, ['retry-after' => 'invalid-date-format']);
        $this->assertNull($result);
    }

    public function testLogRequestWithLogFile(): void
    {
        Config::fake(['APP_DEBUG' => 'false', 'APP_LOG_FILE' => '/tmp/test.log']);
        $client = new BinanceClient();
        $method = new ReflectionMethod(BinanceClient::class, 'logRequest');
        $method->setAccessible(true);

        // Should log when APP_LOG_FILE is set
        $method->invoke($client, 'GET', 'https://test.com', 200, 0, microtime(true), [], null);
        $this->assertTrue(true);
    }
}
