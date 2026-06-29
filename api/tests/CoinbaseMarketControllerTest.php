<?php

use BinanceAPI\Controllers\CoinbaseMarketController;
use BinanceAPI\Contracts\ClientInterface;
use BinanceAPI\Config;
use PHPUnit\Framework\TestCase;

class CoinbaseMarketControllerTest extends TestCase
{
    protected function setUp(): void
    {
        Config::fake([]);
    }

    public function testProductRequiresProductId(): void
    {
        $controller = new CoinbaseMarketController();
        $response = $controller->product([]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('product_id', $response['error']);
    }

    public function testProductBookRequiresProductId(): void
    {
        $controller = new CoinbaseMarketController();
        $response = $controller->productBook([]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('product_id', $response['error']);
    }

    public function testTickerRequiresProductId(): void
    {
        $controller = new CoinbaseMarketController();
        $response = $controller->ticker([]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('product_id', $response['error']);
    }

    public function testCandlesRequiresFields(): void
    {
        $controller = new CoinbaseMarketController();
        $response = $controller->candles(['product_id' => 'BTC-USD']);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('start', $response['error']);
    }

    public function testProductsWithMockClient(): void
    {
        $controller = new CoinbaseMarketController($this->createMockClient());
        $response = $controller->products([]);

        $this->assertTrue($response['success']);
        $this->assertIsArray($response['data']);
    }

    public function testProductWithMockClient(): void
    {
        $controller = new CoinbaseMarketController($this->createMockClient());
        $response = $controller->product(['product_id' => 'BTC-USD']);

        $this->assertTrue($response['success']);
        $this->assertIsArray($response['data']);
    }

    public function testProductAcceptsSymbolAlias(): void
    {
        $mock = new class implements ClientInterface {
            public string $endpoint = '';

            public function get(string $endpoint, array $params = []): array
            {
                $this->endpoint = $endpoint;
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

        $controller = new CoinbaseMarketController($mock);
        $response = $controller->product(['symbol' => 'ETH-USD']);

        $this->assertTrue($response['success']);
        $this->assertSame('/api/v3/brokerage/market/products/ETH-USD', $mock->endpoint);
    }

    public function testProductsNormalizesListParamArray(): void
    {
        $mock = new class implements ClientInterface {
            public array $params = [];

            public function get(string $endpoint, array $params = []): array
            {
                $this->params = $params;
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

        $controller = new CoinbaseMarketController($mock);
        $response = $controller->products([
            'product_ids' => ['BTC-USD', '  ', 'ETH-USD'],
            'limit' => 10,
        ]);

        $this->assertTrue($response['success']);
        $this->assertSame('BTC-USD,ETH-USD', $mock->params['product_ids']);
    }

    public function testCandlesSuccessPath(): void
    {
        $controller = new CoinbaseMarketController($this->createMockClient());
        $response = $controller->candles([
            'product_id' => 'BTC-USD',
            'start' => '1710000000',
            'end' => '1710003600',
            'granularity' => 'ONE_MINUTE',
        ]);

        $this->assertTrue($response['success']);
        $this->assertIsArray($response['data']);
    }

    public function testMarketControllerPropagatesClientError(): void
    {
        $errorClient = new class implements ClientInterface {
            public function get(string $endpoint, array $params = []): array
            {
                return ['success' => false, 'error' => 'upstream'];
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

        $controller = new CoinbaseMarketController($errorClient);
        $response = $controller->ticker(['product_id' => 'BTC-USD']);

        $this->assertFalse($response['success']);
        $this->assertSame('upstream', $response['error']);
    }

    public function testMarketControllerHandlesException(): void
    {
        $failingClient = new class implements ClientInterface {
            public function get(string $endpoint, array $params = []): array
            {
                throw new RuntimeException('boom');
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

        $controller = new CoinbaseMarketController($failingClient);
        $response = $controller->products([]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Falha ao listar produtos', $response['error']);
    }

    public function testPrivateHelpersViaReflection(): void
    {
        $controller = new CoinbaseMarketController($this->createMockClient());

        $normalizeListParam = new ReflectionMethod(CoinbaseMarketController::class, 'normalizeListParam');
        $normalizeListParam->setAccessible(true);
        $resolveProductId = new ReflectionMethod(CoinbaseMarketController::class, 'resolveProductId');
        $resolveProductId->setAccessible(true);
        $formatResponse = new ReflectionMethod(CoinbaseMarketController::class, 'formatResponse');
        $formatResponse->setAccessible(true);

        $this->assertSame('BTC-USD,ETH-USD', $normalizeListParam->invoke($controller, ['BTC-USD', ' ', 'ETH-USD']));
        $this->assertSame('BTC-USD', $normalizeListParam->invoke($controller, 'BTC-USD'));
        $this->assertNull($normalizeListParam->invoke($controller, '   '));
        $this->assertNull($normalizeListParam->invoke($controller, []));

        $this->assertSame('BTC-USD', $resolveProductId->invoke($controller, ['product_id' => 'BTC-USD']));
        $this->assertSame('ETH-USD', $resolveProductId->invoke($controller, ['symbol' => 'ETH-USD']));
        $this->assertNull($resolveProductId->invoke($controller, []));

        $error = $formatResponse->invoke($controller, ['success' => false, 'error' => 'x']);
        $ok = $formatResponse->invoke($controller, ['foo' => 'bar']);

        $this->assertFalse($error['success']);
        $this->assertTrue($ok['success']);
    }

    public function testProductBookWithMockClient(): void
    {
        $controller = new CoinbaseMarketController($this->createMockClient());
        $response = $controller->productBook(['product_id' => 'BTC-USD', 'limit' => 10]);

        $this->assertTrue($response['success']);
        $this->assertIsArray($response['data']);
    }

    public function testTickerWithMockClient(): void
    {
        $controller = new CoinbaseMarketController($this->createMockClient());
        $response = $controller->ticker(['product_id' => 'BTC-USD']);

        $this->assertTrue($response['success']);
        $this->assertIsArray($response['data']);
    }

    public function testProductBookHandlesException(): void
    {
        $failing = new class implements ClientInterface {
            public function get(string $endpoint, array $params = []): array { throw new RuntimeException('boom'); }
            public function post(string $endpoint, array $params = []): array { return []; }
            public function delete(string $endpoint, array $params = []): array { return []; }
        };

        $controller = new CoinbaseMarketController($failing);
        $response = $controller->productBook(['product_id' => 'BTC-USD']);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Falha ao obter livro de ofertas', $response['error']);
    }

    public function testTickerHandlesException(): void
    {
        $failing = new class implements ClientInterface {
            public function get(string $endpoint, array $params = []): array { throw new RuntimeException('boom'); }
            public function post(string $endpoint, array $params = []): array { return []; }
            public function delete(string $endpoint, array $params = []): array { return []; }
        };

        $controller = new CoinbaseMarketController($failing);
        $response = $controller->ticker(['product_id' => 'BTC-USD']);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Falha ao obter ticker', $response['error']);
    }

    public function testCandlesHandlesException(): void
    {
        $failing = new class implements ClientInterface {
            public function get(string $endpoint, array $params = []): array { throw new RuntimeException('boom'); }
            public function post(string $endpoint, array $params = []): array { return []; }
            public function delete(string $endpoint, array $params = []): array { return []; }
        };

        $controller = new CoinbaseMarketController($failing);
        $response = $controller->candles(['product_id' => 'BTC-USD', 'start' => '1', 'end' => '2', 'granularity' => 'ONE_MINUTE']);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Falha ao obter candles', $response['error']);
    }

    public function testProductHandlesException(): void
    {
        $failing = new class implements ClientInterface {
            public function get(string $endpoint, array $params = []): array { throw new RuntimeException('boom'); }
            public function post(string $endpoint, array $params = []): array { return []; }
            public function delete(string $endpoint, array $params = []): array { return []; }
        };

        $controller = new CoinbaseMarketController($failing);
        $response = $controller->product(['product_id' => 'BTC-USD']);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Falha ao obter produto', $response['error']);
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
                return ['mock' => true];
            }

            public function delete(string $endpoint, array $params = []): array
            {
                return ['mock' => true];
            }
        };
    }
}
