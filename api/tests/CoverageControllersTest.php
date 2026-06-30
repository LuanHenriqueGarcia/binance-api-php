<?php

use TradersApi\Controllers\AccountController;
use TradersApi\Controllers\TradingController;
use TradersApi\Controllers\MarketController;
use TradersApi\Controllers\CoinbaseAccountController;
use TradersApi\Controllers\CoinbaseTradingController;
use TradersApi\Contracts\ClientInterface;
use TradersApi\Config;
use PHPUnit\Framework\TestCase;

/**
 * Preenche lacunas de cobertura remanescentes nos controllers:
 *  - ramo getClient() sem cliente injetado (cria cliente real)
 *  - early-returns de validação de credenciais
 *  - blocos catch (cliente lançando exceção)
 *  - ramos específicos de normalização/validação
 */
class CoverageControllersTest extends TestCase
{
    protected function setUp(): void
    {
        Config::fake([]);
    }

    private function clientOk(): ClientInterface
    {
        return new class implements ClientInterface {
            public function get(string $endpoint, array $params = []): array
            {
                return ['ok' => true, 'endpoint' => $endpoint, 'params' => $params];
            }

            public function post(string $endpoint, array $params = []): array
            {
                return ['ok' => true, 'endpoint' => $endpoint, 'params' => $params];
            }

            public function delete(string $endpoint, array $params = []): array
            {
                return ['ok' => true, 'endpoint' => $endpoint, 'params' => $params];
            }
        };
    }

    private function clientThrows(): ClientInterface
    {
        return new class implements ClientInterface {
            public function get(string $endpoint, array $params = []): array
            {
                throw new \RuntimeException('boom');
            }

            public function post(string $endpoint, array $params = []): array
            {
                throw new \RuntimeException('boom');
            }

            public function delete(string $endpoint, array $params = []): array
            {
                throw new \RuntimeException('boom');
            }
        };
    }

    private function clientFailure(): ClientInterface
    {
        return new class implements ClientInterface {
            public function get(string $endpoint, array $params = []): array
            {
                return ['success' => false, 'error' => 'upstream-failure'];
            }

            public function post(string $endpoint, array $params = []): array
            {
                return ['success' => false, 'error' => 'upstream-failure'];
            }

            public function delete(string $endpoint, array $params = []): array
            {
                return ['success' => false, 'error' => 'upstream-failure'];
            }
        };
    }

    // ===================== AccountController =====================

    public function testAccountControllerGetClientCreatesRealClient(): void
    {
        $controller = new AccountController();
        $method = new ReflectionMethod(AccountController::class, 'getClient');
        $method->setAccessible(true);

        $client = $method->invoke($controller, 'api-key', 'secret-key');

        $this->assertInstanceOf(ClientInterface::class, $client);
    }

    public function testAssetBalancePropagatesUpstreamFailure(): void
    {
        $controller = new AccountController($this->clientFailure());

        $response = $controller->getAssetBalance([
            'api_key' => 'k',
            'secret_key' => 's',
            'asset' => 'BTC',
        ]);

        $this->assertFalse($response['success']);
        $this->assertSame('upstream-failure', $response['error']);
    }

    public function testDustTransferAcceptsCsvStringAssets(): void
    {
        $controller = new AccountController($this->clientOk());

        $response = $controller->dustTransfer([
            'api_key' => 'k',
            'secret_key' => 's',
            'assets' => 'btc,eth',
        ]);

        $this->assertTrue($response['success']);
    }

    public function testDustTransferRejectsEmptyAssetListAfterFilter(): void
    {
        $controller = new AccountController($this->clientOk());

        $response = $controller->dustTransfer([
            'api_key' => 'k',
            'secret_key' => 's',
            'assets' => ',,',
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('ao menos um asset', $response['error']);
    }

    // ===================== TradingController =====================

    public function testTradingControllerGetClientCreatesRealClient(): void
    {
        $controller = new TradingController();
        $method = new ReflectionMethod(TradingController::class, 'getClient');
        $method->setAccessible(true);

        $client = $method->invoke($controller, 'api-key', 'secret-key');

        $this->assertInstanceOf(ClientInterface::class, $client);
    }

    public function testQueryOrderRequiresSymbol(): void
    {
        $controller = new TradingController($this->clientOk());

        $response = $controller->queryOrder([
            'api_key' => 'k',
            'secret_key' => 's',
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('symbol', $response['error']);
    }

    public function testBuildOrderParamsRequiresSymbolSideType(): void
    {
        $controller = new TradingController($this->clientOk());

        // api_key/secret presentes, mas faltam symbol/side/type
        $response = $controller->createOrder([
            'api_key' => 'k',
            'secret_key' => 's',
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('symbol', $response['error']);
    }

    public function testBuildOrderParamsNonMarketRequiresQuantity(): void
    {
        $controller = new TradingController($this->clientOk());

        // LIMIT sem quantity -> ramo else exige "quantity"
        $response = $controller->createOrder([
            'api_key' => 'k',
            'secret_key' => 's',
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'type' => 'LIMIT',
            'price' => '50000',
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('quantity', $response['error']);
    }

    // ===================== CoinbaseAccountController =====================

    public function testCoinbaseAccountGetClientCreatesRealClient(): void
    {
        $controller = new CoinbaseAccountController();
        $method = new ReflectionMethod(CoinbaseAccountController::class, 'getClient');
        $method->setAccessible(true);

        $client = $method->invoke($controller, 'key', 'secret', null);

        $this->assertInstanceOf(ClientInterface::class, $client);
    }

    public function testCoinbaseAccountAccountHandlesException(): void
    {
        Config::fake([
            'COINBASE_API_KEY' => 'k',
            'COINBASE_API_SECRET' => 's',
        ]);
        $controller = new CoinbaseAccountController($this->clientThrows());

        $response = $controller->account(['account_uuid' => 'uuid-123']);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Falha ao obter conta', $response['error']);
        $this->assertStringContainsString('boom', $response['error']);
    }

    // ===================== CoinbaseTradingController =====================

    public function testCoinbaseTradingGetClientCreatesRealClient(): void
    {
        $controller = new CoinbaseTradingController();
        $method = new ReflectionMethod(CoinbaseTradingController::class, 'getClient');
        $method->setAccessible(true);

        $client = $method->invoke($controller, 'key', 'secret', null);

        $this->assertInstanceOf(ClientInterface::class, $client);
    }

    public function testCoinbaseCancelOrderRequiresCredentials(): void
    {
        $controller = new CoinbaseTradingController($this->clientOk());

        $response = $controller->cancelOrder(['order_ids' => 'a,b']);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Chaves de API', $response['error']);
    }

    public function testCoinbaseCancelOrderHandlesException(): void
    {
        $controller = new CoinbaseTradingController($this->clientThrows());

        $response = $controller->cancelOrder([
            'api_key' => 'k',
            'api_secret' => 's',
            'order_ids' => 'a,b',
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Falha ao cancelar ordem', $response['error']);
    }

    public function testCoinbaseGetOrderRequiresCredentials(): void
    {
        $controller = new CoinbaseTradingController($this->clientOk());

        $response = $controller->getOrder(['order_id' => 'o1']);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Chaves de API', $response['error']);
    }

    public function testCoinbaseGetOrderHandlesException(): void
    {
        $controller = new CoinbaseTradingController($this->clientThrows());

        $response = $controller->getOrder([
            'api_key' => 'k',
            'api_secret' => 's',
            'order_id' => 'o1',
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Falha ao consultar ordem', $response['error']);
    }

    public function testCoinbaseListOrdersRequiresCredentials(): void
    {
        $controller = new CoinbaseTradingController($this->clientOk());

        $response = $controller->listOrders([]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Chaves de API', $response['error']);
    }

    public function testCoinbaseListOrdersHandlesException(): void
    {
        $controller = new CoinbaseTradingController($this->clientThrows());

        $response = $controller->listOrders([
            'api_key' => 'k',
            'api_secret' => 's',
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Falha ao listar ordens', $response['error']);
    }

    public function testCoinbaseNormalizeBoolWithNonBoolNonString(): void
    {
        $controller = new CoinbaseTradingController($this->clientOk());
        $method = new ReflectionMethod(CoinbaseTradingController::class, 'normalizeBool');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($controller, 1));
        $this->assertFalse($method->invoke($controller, 0));
    }

    // ===================== MarketController =====================

    public function testRollingWindowTickerWithTypeAndSymbols(): void
    {
        $controller = new MarketController($this->clientOk());

        $withType = $controller->rollingWindowTicker([
            'symbol' => 'BTCUSDT',
            'type' => 'FULL',
        ]);
        $this->assertTrue($withType['success']);

        $withSymbols = $controller->rollingWindowTicker([
            'symbols' => '["BTCUSDT","ETHUSDT"]',
        ]);
        $this->assertTrue($withSymbols['success']);
    }
}
