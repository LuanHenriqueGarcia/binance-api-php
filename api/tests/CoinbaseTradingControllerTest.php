<?php

use TradersApi\Controllers\CoinbaseTradingController;
use TradersApi\Contracts\ClientInterface;
use TradersApi\Config;
use PHPUnit\Framework\TestCase;

class CoinbaseTradingControllerTest extends TestCase
{
    private CoinbaseTradingController $controller;

    protected function setUp(): void
    {
        Config::fake([
            'COINBASE_API_KEY' => 'test-key',
            'COINBASE_API_SECRET' => 'test-secret',
        ]);
        $this->controller = new CoinbaseTradingController($this->createMockClient());
    }

    public function testCreateOrderRequiresProductId(): void
    {
        $response = $this->controller->createOrder([
            'side' => 'BUY',
            'type' => 'MARKET',
            'quote_size' => '10'
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('product_id', $response['error']);
    }

    public function testCreateOrderRequiresSide(): void
    {
        $response = $this->controller->createOrder([
            'product_id' => 'BTC-USD',
            'type' => 'MARKET',
            'quote_size' => '10'
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('side', $response['error']);
    }

    public function testCreateOrderRequiresType(): void
    {
        $response = $this->controller->createOrder([
            'product_id' => 'BTC-USD',
            'side' => 'BUY',
            'quote_size' => '10'
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('type', $response['error']);
    }

    public function testCancelOrderRequiresOrderId(): void
    {
        $response = $this->controller->cancelOrder([]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('order', $response['error']);
    }

    public function testGetOrderRequiresOrderId(): void
    {
        $response = $this->controller->getOrder([]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('order_id', $response['error']);
    }

    public function testListOrdersWithMockClient(): void
    {
        $response = $this->controller->listOrders([]);

        $this->assertTrue($response['success']);
        $this->assertIsArray($response['data']);
    }

    public function testCreateOrderInvalidSide(): void
    {
        $response = $this->controller->createOrder([
            'product_id' => 'BTC-USD',
            'side' => 'HOLD',
            'type' => 'MARKET',
            'quote_size' => '10'
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('side', $response['error']);
    }

    public function testCreateOrderInvalidType(): void
    {
        $response = $this->controller->createOrder([
            'product_id' => 'BTC-USD',
            'side' => 'BUY',
            'type' => 'STOP'
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('type', $response['error']);
    }

    public function testCreateOrderMarketRequiresAmount(): void
    {
        $response = $this->controller->createOrder([
            'product_id' => 'BTC-USD',
            'side' => 'BUY',
            'type' => 'MARKET'
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('base_size', $response['error']);
    }

    public function testCreateOrderLimitRequiresBaseAndPrice(): void
    {
        $missingBase = $this->controller->createOrder([
            'product_id' => 'BTC-USD',
            'side' => 'BUY',
            'type' => 'LIMIT',
            'limit_price' => '50000'
        ]);

        $missingPrice = $this->controller->createOrder([
            'product_id' => 'BTC-USD',
            'side' => 'BUY',
            'type' => 'LIMIT',
            'base_size' => '0.1'
        ]);

        $this->assertFalse($missingBase['success']);
        $this->assertStringContainsString('base_size', $missingBase['error']);
        $this->assertFalse($missingPrice['success']);
        $this->assertStringContainsString('limit_price', $missingPrice['error']);
    }

    public function testCreateOrderLimitInvalidTimeInForce(): void
    {
        $response = $this->controller->createOrder([
            'product_id' => 'BTC-USD',
            'side' => 'BUY',
            'type' => 'LIMIT',
            'base_size' => '0.1',
            'limit_price' => '50000',
            'time_in_force' => 'BAD',
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('time_in_force', $response['error']);
    }

    public function testCreateOrderLimitIocAndFokAndGtc(): void
    {
        $client = new class implements ClientInterface {
            public array $lastPost = [];

            public function get(string $endpoint, array $params = []): array
            {
                return ['ok' => true];
            }

            public function post(string $endpoint, array $params = []): array
            {
                $this->lastPost = $params;
                return ['ok' => true, 'endpoint' => $endpoint, 'params' => $params];
            }

            public function delete(string $endpoint, array $params = []): array
            {
                return ['ok' => true];
            }
        };

        $controller = new CoinbaseTradingController($client);

        $ioc = $controller->createOrder([
            'api_key' => 'k',
            'api_secret' => 's',
            'product_id' => 'BTC-USD',
            'side' => 'BUY',
            'type' => 'LIMIT',
            'base_size' => '0.1',
            'limit_price' => '50000',
            'time_in_force' => 'IOC',
        ]);
        $this->assertTrue($ioc['success']);
        $this->assertArrayHasKey('sor_limit_ioc', $client->lastPost['order_configuration']);

        $fok = $controller->createOrder([
            'api_key' => 'k',
            'api_secret' => 's',
            'product_id' => 'BTC-USD',
            'side' => 'BUY',
            'type' => 'LIMIT',
            'base_size' => '0.1',
            'limit_price' => '50000',
            'time_in_force' => 'FOK',
        ]);
        $this->assertTrue($fok['success']);
        $this->assertArrayHasKey('limit_limit_fok', $client->lastPost['order_configuration']);

        $gtc = $controller->createOrder([
            'api_key' => 'k',
            'api_secret' => 's',
            'product_id' => 'BTC-USD',
            'side' => 'BUY',
            'type' => 'LIMIT',
            'base_size' => '0.1',
            'limit_price' => '50000',
            'time_in_force' => 'GTC',
            'post_only' => 'yes',
        ]);
        $this->assertTrue($gtc['success']);
        $this->assertArrayHasKey('limit_limit_gtc', $client->lastPost['order_configuration']);
        $this->assertTrue($client->lastPost['order_configuration']['limit_limit_gtc']['post_only']);
    }

    public function testCancelOrderAcceptsCsvOrderIds(): void
    {
        $client = new class implements ClientInterface {
            public array $lastPost = [];

            public function get(string $endpoint, array $params = []): array
            {
                return [];
            }

            public function post(string $endpoint, array $params = []): array
            {
                $this->lastPost = $params;
                return ['ok' => true];
            }

            public function delete(string $endpoint, array $params = []): array
            {
                return [];
            }
        };

        $controller = new CoinbaseTradingController($client);
        $response = $controller->cancelOrder([
            'api_key' => 'k',
            'api_secret' => 's',
            'order_ids' => 'a1,b2,c3',
        ]);

        $this->assertTrue($response['success']);
        $this->assertSame(['a1', 'b2', 'c3'], $client->lastPost['order_ids']);
    }

    public function testGetOrderSuccessPath(): void
    {
        $response = $this->controller->getOrder([
            'order_id' => 'ord-1',
        ]);

        $this->assertTrue($response['success']);
        $this->assertIsArray($response['data']);
    }

    public function testListOrdersNormalizesArrayParams(): void
    {
        $client = new class implements ClientInterface {
            public array $lastGet = [];

            public function get(string $endpoint, array $params = []): array
            {
                $this->lastGet = $params;
                return ['ok' => true];
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

        $controller = new CoinbaseTradingController($client);
        $response = $controller->listOrders([
            'api_key' => 'k',
            'api_secret' => 's',
            'order_ids' => ['o1', 'o2'],
            'product_ids' => ['BTC-USD', 'ETH-USD'],
            'asset_filters' => ['BTC', 'ETH'],
        ]);

        $this->assertTrue($response['success']);
        $this->assertSame('o1,o2', $client->lastGet['order_ids']);
        $this->assertSame('BTC-USD,ETH-USD', $client->lastGet['product_ids']);
        $this->assertSame('BTC,ETH', $client->lastGet['asset_filters']);
    }

    public function testTradingControllerHandlesException(): void
    {
        $failing = new class implements ClientInterface {
            public function get(string $endpoint, array $params = []): array
            {
                return [];
            }

            public function post(string $endpoint, array $params = []): array
            {
                throw new RuntimeException('upstream failed');
            }

            public function delete(string $endpoint, array $params = []): array
            {
                return [];
            }
        };

        $controller = new CoinbaseTradingController($failing);
        $response = $controller->createOrder([
            'api_key' => 'k',
            'api_secret' => 's',
            'product_id' => 'BTC-USD',
            'side' => 'BUY',
            'type' => 'MARKET',
            'quote_size' => '10',
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Falha ao criar ordem', $response['error']);
    }

    public function testPrivateHelpersViaReflection(): void
    {
        $resolveCredentials = new ReflectionMethod(CoinbaseTradingController::class, 'resolveCredentials');
        $resolveCredentials->setAccessible(true);
        $validateCredentials = new ReflectionMethod(CoinbaseTradingController::class, 'validateCredentials');
        $validateCredentials->setAccessible(true);
        $normalizeListToArray = new ReflectionMethod(CoinbaseTradingController::class, 'normalizeListToArray');
        $normalizeListToArray->setAccessible(true);
        $normalizeListParam = new ReflectionMethod(CoinbaseTradingController::class, 'normalizeListParam');
        $normalizeListParam->setAccessible(true);
        $resolveProductId = new ReflectionMethod(CoinbaseTradingController::class, 'resolveProductId');
        $resolveProductId->setAccessible(true);
        $normalizeBool = new ReflectionMethod(CoinbaseTradingController::class, 'normalizeBool');
        $normalizeBool->setAccessible(true);
        $generateClientOrderId = new ReflectionMethod(CoinbaseTradingController::class, 'generateClientOrderId');
        $generateClientOrderId->setAccessible(true);
        $formatResponse = new ReflectionMethod(CoinbaseTradingController::class, 'formatResponse');
        $formatResponse->setAccessible(true);

        $creds = $resolveCredentials->invoke($this->controller, ['api_key' => 'a', 'api_secret' => 'b']);
        $this->assertSame(['a', 'b', null], $creds);

        $this->assertNull($validateCredentials->invoke($this->controller, 'a', 'b', null));
        $this->assertNull($validateCredentials->invoke($this->controller, null, null, '/tmp/key.json'));
        $this->assertStringContainsString('Chaves de API', $validateCredentials->invoke($this->controller, null, null, null));

        $this->assertSame(['a', 'b'], $normalizeListToArray->invoke($this->controller, 'a,b'));
        $this->assertSame(['x', 'y'], $normalizeListToArray->invoke($this->controller, ['x', ' ', 'y']));
        $this->assertSame([], $normalizeListToArray->invoke($this->controller, null));

        $this->assertSame('a,b', $normalizeListParam->invoke($this->controller, ['a', ' ', 'b']));
        $this->assertSame('abc', $normalizeListParam->invoke($this->controller, 'abc'));
        $this->assertNull($normalizeListParam->invoke($this->controller, '   '));

        $this->assertSame('BTC-USD', $resolveProductId->invoke($this->controller, ['product_id' => 'BTC-USD']));
        $this->assertSame('ETH-USD', $resolveProductId->invoke($this->controller, ['symbol' => 'ETH-USD']));
        $this->assertNull($resolveProductId->invoke($this->controller, []));

        $this->assertTrue($normalizeBool->invoke($this->controller, true));
        $this->assertTrue($normalizeBool->invoke($this->controller, 'yes'));
        $this->assertFalse($normalizeBool->invoke($this->controller, 'no'));

        $generatedId = $generateClientOrderId->invoke($this->controller);
        $this->assertIsString($generatedId);
        $this->assertSame(32, strlen($generatedId));

        $this->assertSame(['success' => false, 'error' => 'x'], $formatResponse->invoke($this->controller, ['success' => false, 'error' => 'x']));
        $wrapped = $formatResponse->invoke($this->controller, ['ok' => true]);
        $this->assertTrue($wrapped['success']);
    }

    private function createMockClient(): ClientInterface
    {
        return new class implements ClientInterface {
            public function get(string $endpoint, array $params = []): array
            {
                return ['mock' => true, 'endpoint' => $endpoint, 'params' => $params];
            }

            public function post(string $endpoint, array $params = []): array
            {
                return ['mock' => true, 'endpoint' => $endpoint, 'params' => $params];
            }

            public function delete(string $endpoint, array $params = []): array
            {
                return ['mock' => true, 'endpoint' => $endpoint, 'params' => $params];
            }
        };
    }
}
