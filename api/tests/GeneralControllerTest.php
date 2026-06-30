<?php

use TradersApi\Controllers\GeneralController;
use TradersApi\Contracts\ClientInterface;
use TradersApi\Cache;
use TradersApi\Config;
use PHPUnit\Framework\TestCase;

class GeneralControllerTest extends TestCase
{
    private GeneralController $controller;

    protected function setUp(): void
    {
        Config::fake([]);
        $this->controller = new GeneralController();
    }

    public function testFormatResponseSuccessWrap(): void
    {
        $method = new ReflectionMethod(GeneralController::class, 'formatResponse');
        $method->setAccessible(true);

        $response = $method->invoke($this->controller, ['hello' => 'world']);
        $this->assertTrue($response['success']);
        $this->assertSame(['hello' => 'world'], $response['data']);
    }

    public function testFormatResponsePropagatesError(): void
    {
        $method = new ReflectionMethod(GeneralController::class, 'formatResponse');
        $method->setAccessible(true);

        $error = ['success' => false, 'error' => 'fail'];
        $response = $method->invoke($this->controller, $error);
        $this->assertFalse($response['success']);
        $this->assertSame('fail', $response['error']);
    }

    public function testPingReturnsArray(): void
    {
        $mock = $this->createMockClient();
        $controller = new GeneralController($mock);

        $response = $controller->ping();

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testTimeReturnsArray(): void
    {
        $mock = $this->createMockClient();
        $controller = new GeneralController($mock);

        $response = $controller->time();

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testExchangeInfoReturnsArray(): void
    {
        $mock = $this->createMockClient();
        $controller = new GeneralController($mock);

        $response = $controller->exchangeInfo([]);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testExchangeInfoWithSymbol(): void
    {
        $mock = $this->createMockClient();
        $controller = new GeneralController($mock);

        $response = $controller->exchangeInfo(['symbol' => 'BTCUSDT']);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testExchangeInfoWithSymbols(): void
    {
        $mock = $this->createMockClient();
        $controller = new GeneralController($mock);

        $response = $controller->exchangeInfo(['symbols' => ['BTCUSDT', 'ETHUSDT']]);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testExchangeInfoWithPermissions(): void
    {
        $mock = $this->createMockClient();
        $controller = new GeneralController($mock);

        $response = $controller->exchangeInfo(['permissions' => 'SPOT']);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testExchangeInfoWithMarket(): void
    {
        $mock = $this->createMockClient();
        $controller = new GeneralController($mock);

        $response = $controller->exchangeInfo(['market' => 'spot']);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testExchangeInfoWithNoCache(): void
    {
        $mock = $this->createMockClient();
        $controller = new GeneralController($mock);

        $response = $controller->exchangeInfo(['noCache' => true]);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testExchangeInfoWithNoCacheString(): void
    {
        $mock = $this->createMockClient();
        $controller = new GeneralController($mock);

        $response = $controller->exchangeInfo(['noCache' => 'true']);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testExchangeInfoWithNoCacheInt(): void
    {
        $mock = $this->createMockClient();
        $controller = new GeneralController($mock);

        $response = $controller->exchangeInfo(['noCache' => 1]);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testExchangeInfoWithNoCacheStringOne(): void
    {
        $mock = $this->createMockClient();
        $controller = new GeneralController($mock);

        $response = $controller->exchangeInfo(['noCache' => '1']);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testExchangeInfoWithPermissionsArray(): void
    {
        $mock = $this->createMockClient();
        $controller = new GeneralController($mock);

        $response = $controller->exchangeInfo(['permissions' => ['spot', 'margin']]);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testExchangeInfoErrorHandling(): void
    {
        $mock = $this->createExceptionMock('Network error');
        $controller = new GeneralController($mock);

        // Use noCache to ensure we hit the API mock
        $response = $controller->exchangeInfo(['noCache' => true]);

        $this->assertIsArray($response);
        $this->assertFalse($response['success']);
        $this->assertArrayHasKey('error', $response);
    }

    public function testPingErrorHandling(): void
    {
        $mock = $this->createExceptionMock('Ping failed');
        $controller = new GeneralController($mock);

        $response = $controller->ping();

        $this->assertIsArray($response);
        $this->assertFalse($response['success']);
        $this->assertArrayHasKey('error', $response);
    }

    public function testTimeErrorHandling(): void
    {
        $mock = $this->createExceptionMock('Time error');
        $controller = new GeneralController($mock);

        $response = $controller->time();

        $this->assertIsArray($response);
        $this->assertFalse($response['success']);
        $this->assertArrayHasKey('error', $response);
    }

    // ===== TESTES COM MOCK =====

    private function createMockClient(): ClientInterface
    {
        return new class implements ClientInterface {
            public function get(string $endpoint, array $params = []): array
            {
                return match ($endpoint) {
                    '/api/v3/ping' => [],
                    '/api/v3/time' => ['serverTime' => 1234567890123],
                    '/api/v3/exchangeInfo' => [
                        'timezone' => 'UTC',
                        'serverTime' => 1234567890123,
                        'symbols' => [
                            ['symbol' => 'BTCUSDT', 'status' => 'TRADING']
                        ]
                    ],
                    default => ['mockData' => true]
                };
            }

            public function post(string $endpoint, array $params = []): array
            {
                return ['success' => true];
            }

            public function delete(string $endpoint, array $params = []): array
            {
                return ['status' => 'ok'];
            }
        };
    }

    private function createExceptionMock(string $errorMessage = 'Mock error'): ClientInterface
    {
        return new class($errorMessage) implements ClientInterface {
            public function __construct(private string $errorMessage)
            {
            }

            public function get(string $endpoint, array $params = []): array
            {
                throw new \Exception($this->errorMessage);
            }

            public function post(string $endpoint, array $params = []): array
            {
                throw new \Exception($this->errorMessage);
            }

            public function delete(string $endpoint, array $params = []): array
            {
                throw new \Exception($this->errorMessage);
            }
        };
    }

    public function testPingWithMockSuccess(): void
    {
        $mock = $this->createMockClient();
        $controller = new GeneralController($mock);

        $response = $controller->ping();

        $this->assertTrue($response['success']);
    }

    public function testTimeWithMockSuccess(): void
    {
        $mock = $this->createMockClient();
        $controller = new GeneralController($mock);

        $response = $controller->time();

        $this->assertTrue($response['success']);
        $this->assertSame(1234567890123, $response['data']['serverTime']);
    }

    public function testExchangeInfoWithMockSuccess(): void
    {
        $mock = $this->createMockClient();
        $controller = new GeneralController($mock);

        $response = $controller->exchangeInfo([]);

        $this->assertTrue($response['success']);
        $this->assertSame('UTC', $response['data']['timezone']);
    }

    public function testExchangeInfoWithMockAndSymbol(): void
    {
        $mock = $this->createMockClient();
        $controller = new GeneralController($mock);

        $response = $controller->exchangeInfo(['symbol' => 'BTCUSDT']);

        $this->assertTrue($response['success']);
    }

    public function testExchangeInfoWithMockAndSymbols(): void
    {
        $mock = $this->createMockClient();
        $controller = new GeneralController($mock);

        $response = $controller->exchangeInfo(['symbols' => ['BTCUSDT', 'ETHUSDT']]);

        $this->assertTrue($response['success']);
    }

    public function testExchangeInfoWithMockAndPermissions(): void
    {
        $mock = $this->createMockClient();
        $controller = new GeneralController($mock);

        $response = $controller->exchangeInfo(['permissions' => 'SPOT']);

        $this->assertTrue($response['success']);
    }

    public function testPingWithMockException(): void
    {
        $mock = $this->createExceptionMock('Ping error');
        $controller = new GeneralController($mock);

        $response = $controller->ping();

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Ping error', $response['error']);
    }

    public function testTimeWithMockException(): void
    {
        $mock = $this->createExceptionMock('Time error');
        $controller = new GeneralController($mock);

        $response = $controller->time();

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Time error', $response['error']);
    }

    public function testExchangeInfoWithMockException(): void
    {
        $mock = $this->createExceptionMock('Exchange info error');
        $controller = new GeneralController($mock);

        // exchangeInfo uses cache, which may call Config before calling client
        // Need to ensure exception propagates
        $response = $controller->exchangeInfo(['noCache' => true]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Exchange info error', $response['error']);
    }

    public function testExchangeInfoWithCacheHit(): void
    {
        // Create a mock client that will NOT be called because cache hits
        $callCount = 0;
        $mock = new class($callCount) implements ClientInterface {
            private int $callCount;

            public function __construct(int &$count)
            {
                $this->callCount = &$count;
            }

            public function get(string $endpoint, array $params = []): array
            {
                $this->callCount++;
                return ['serverTime' => 1234567890123];
            }

            public function post(string $endpoint, array $params = []): array
            {
                return [];
            }

            public function delete(string $endpoint, array $params = []): array
            {
                return [];
            }
        };

        // Create a cache mock that returns cached data
        $cacheProxy = new class extends Cache {
            public function get(string $key, int $ttlSeconds = 60): ?array
            {
                return ['timezone' => 'UTC', 'cached' => true];
            }

            public function set(string $key, array $value): void
            {
                // Do nothing
            }
        };

        $controller = new GeneralController($mock, $cacheProxy);
        $response = $controller->exchangeInfo([]);

        $this->assertTrue($response['success']);
        $this->assertTrue($response['cached'] ?? false);
    }

    public function testExchangeInfoNoCacheForcesFetch(): void
    {
        $mock = $this->createMockClient();

        // Create a cache mock
        $cacheProxy = new class extends Cache {
            public function get(string $key, int $ttlSeconds = 60): ?array
            {
                // Return null to simulate cache miss
                return null;
            }

            public function set(string $key, array $value): void
            {
                // Do nothing
            }
        };

        $controller = new GeneralController($mock, $cacheProxy);
        $response = $controller->exchangeInfo(['noCache' => true]);

        $this->assertTrue($response['success']);
        // Should get fresh data from mock
        $this->assertSame(1234567890123, $response['data']['serverTime']);
    }
}
