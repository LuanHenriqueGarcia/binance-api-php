<?php

use TradersApi\Controllers\AccountController;
use TradersApi\Contracts\ClientInterface;
use TradersApi\Config;
use PHPUnit\Framework\TestCase;

class AccountControllerTest extends TestCase
{
    private AccountController $controller;

    protected function setUp(): void
    {
        Config::fake([]);
        $this->controller = new AccountController();
    }

    public function testAccountInfoRequiresKeys(): void
    {
        $response = $this->controller->getAccountInfo([]);
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Chaves de API', $response['error']);
    }

    public function testOpenOrdersRequiresKeys(): void
    {
        $response = $this->controller->getOpenOrders([]);
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Chaves de API', $response['error']);
    }

    public function testOrderHistoryRequiresSymbol(): void
    {
        $response = $this->controller->getOrderHistory([
            'api_key' => 'k',
            'secret_key' => 's',
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('symbol', $response['error']);
    }

    public function testAssetBalanceRequiresAsset(): void
    {
        $response = $this->controller->getAssetBalance([
            'api_key' => 'k',
            'secret_key' => 's',
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('asset', $response['error']);
    }

    public function testMyTradesRequiresSymbol(): void
    {
        $response = $this->controller->getMyTrades([
            'api_key' => 'k',
            'secret_key' => 's',
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('symbol', $response['error']);
    }

    public function testAccountStatusRequiresKeys(): void
    {
        $response = $this->controller->getAccountStatus([]);
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Chaves de API', $response['error']);
    }

    public function testApiTradingStatusRequiresKeys(): void
    {
        $response = $this->controller->getApiTradingStatus([]);
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Chaves de API', $response['error']);
    }

    public function testCapitalConfigRequiresKeys(): void
    {
        $response = $this->controller->getCapitalConfig([]);
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Chaves de API', $response['error']);
    }

    public function testDustTransferRequiresAssets(): void
    {
        $response = $this->controller->dustTransfer([
            'api_key' => 'k',
            'secret_key' => 's',
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('assets', $response['error']);
    }

    public function testAssetDividendRequiresKeys(): void
    {
        $response = $this->controller->assetDividend([]);
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Chaves de API', $response['error']);
    }

    public function testConvertTransferableRequiresFromAndTo(): void
    {
        $response = $this->controller->convertTransferable([
            'api_key' => 'k',
            'secret_key' => 's',
            'fromAsset' => 'BTC',
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('toAsset', $response['error']);
    }

    public function testP2pOrdersRequiresKeys(): void
    {
        $response = $this->controller->p2pOrders([]);
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Chaves de API', $response['error']);
    }

    public function testFormatResponseSuccess(): void
    {
        $method = new ReflectionMethod(AccountController::class, 'formatResponse');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, ['balance' => '1.5']);

        $this->assertTrue($result['success']);
        $this->assertSame(['balance' => '1.5'], $result['data']);
    }

    public function testFormatResponsePropagatesError(): void
    {
        $method = new ReflectionMethod(AccountController::class, 'formatResponse');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, ['success' => false, 'error' => 'test']);

        $this->assertFalse($result['success']);
        $this->assertSame('test', $result['error']);
    }

    public function testFormatResponseBinanceError(): void
    {
        $method = new ReflectionMethod(AccountController::class, 'formatResponse');
        $method->setAccessible(true);

        // AccountController formatResponse wraps Binance errors as success
        // because it doesn't check for code/msg pattern
        $result = $method->invoke($this->controller, ['code' => -2015, 'msg' => 'Invalid API-key']);

        // The controller's simple formatResponse just wraps everything
        $this->assertTrue($result['success']);
        $this->assertSame(['code' => -2015, 'msg' => 'Invalid API-key'], $result['data']);
    }

    public function testOrderHistoryRequiresKeys(): void
    {
        $response = $this->controller->getOrderHistory([]);
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Chaves de API', $response['error']);
    }

    public function testAssetBalanceRequiresKeys(): void
    {
        $response = $this->controller->getAssetBalance([]);
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Chaves de API', $response['error']);
    }

    public function testMyTradesRequiresKeys(): void
    {
        $response = $this->controller->getMyTrades([]);
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Chaves de API', $response['error']);
    }

    public function testDustTransferRequiresKeys(): void
    {
        $response = $this->controller->dustTransfer([]);
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Chaves de API', $response['error']);
    }

    public function testConvertTransferableRequiresKeys(): void
    {
        $response = $this->controller->convertTransferable([]);
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Chaves de API', $response['error']);
    }

    public function testConvertTransferableRequiresFromAsset(): void
    {
        $response = $this->controller->convertTransferable([
            'api_key' => 'k',
            'secret_key' => 's',
            'toAsset' => 'BTC',
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('fromAsset', $response['error']);
    }

    // Tests with mock client to avoid real HTTP calls
    public function testGetAccountInfoWithKeys(): void
    {
        $mock = $this->createMockClient();
        $controller = new AccountController($mock);

        $response = $controller->getAccountInfo([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret'
        ]);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
    }

    public function testGetOpenOrdersWithKeys(): void
    {
        $mock = $this->createMockClient();
        $controller = new AccountController($mock);

        $response = $controller->getOpenOrders([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret'
        ]);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testGetOpenOrdersWithSymbol(): void
    {
        $mock = $this->createMockClient();
        $controller = new AccountController($mock);

        $response = $controller->getOpenOrders([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT'
        ]);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testGetOrderHistoryWithKeys(): void
    {
        $mock = $this->createMockClient();
        $controller = new AccountController($mock);

        $response = $controller->getOrderHistory([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT'
        ]);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testGetOrderHistoryWithLimit(): void
    {
        $mock = $this->createMockClient();
        $controller = new AccountController($mock);

        $response = $controller->getOrderHistory([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT',
            'limit' => 10
        ]);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testGetAssetBalanceWithKeys(): void
    {
        $mock = new class implements ClientInterface {
            public function get(string $endpoint, array $params = []): array
            {
                return [
                    'balances' => [
                        ['asset' => 'BTC', 'free' => '1.5', 'locked' => '0.1'],
                        ['asset' => 'ETH', 'free' => '10.0', 'locked' => '0.0'],
                    ]
                ];
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

        $controller = new AccountController($mock);

        $response = $controller->getAssetBalance([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'asset' => 'BTC'
        ]);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testGetMyTradesWithKeys(): void
    {
        $mock = $this->createMockClient();
        $controller = new AccountController($mock);

        $response = $controller->getMyTrades([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT'
        ]);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testGetMyTradesWithLimit(): void
    {
        $mock = $this->createMockClient();
        $controller = new AccountController($mock);

        $response = $controller->getMyTrades([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT',
            'limit' => 10
        ]);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testGetAccountStatusWithKeys(): void
    {
        $mock = $this->createMockClient();
        $controller = new AccountController($mock);

        $response = $controller->getAccountStatus([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret'
        ]);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testGetApiTradingStatusWithKeys(): void
    {
        $mock = $this->createMockClient();
        $controller = new AccountController($mock);

        $response = $controller->getApiTradingStatus([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret'
        ]);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testGetCapitalConfigWithKeys(): void
    {
        $mock = $this->createMockClient();
        $controller = new AccountController($mock);

        $response = $controller->getCapitalConfig([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret'
        ]);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testDustTransferWithKeys(): void
    {
        $mock = $this->createMockClient();
        $controller = new AccountController($mock);

        $response = $controller->dustTransfer([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'assets' => ['SHIB', 'DOGE']
        ]);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testAssetDividendWithKeys(): void
    {
        $mock = $this->createMockClient();
        $controller = new AccountController($mock);

        $response = $controller->assetDividend([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret'
        ]);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testAssetDividendWithAsset(): void
    {
        $mock = $this->createMockClient();
        $controller = new AccountController($mock);

        $response = $controller->assetDividend([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'asset' => 'BTC'
        ]);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testConvertTransferableWithKeys(): void
    {
        $mock = $this->createMockClient();
        $controller = new AccountController($mock);

        $response = $controller->convertTransferable([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'fromAsset' => 'BTC',
            'toAsset' => 'USDT'
        ]);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testP2pOrdersWithKeys(): void
    {
        $mock = $this->createMockClient();
        $controller = new AccountController($mock);

        $response = $controller->p2pOrders([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret'
        ]);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    // ===== TESTES COM MOCK =====

    private function createMockClient(): ClientInterface
    {
        return new class implements ClientInterface {
            public function get(string $endpoint, array $params = []): array
            {
                return ['mockData' => true, 'endpoint' => $endpoint];
            }

            public function post(string $endpoint, array $params = []): array
            {
                return ['success' => true, 'endpoint' => $endpoint];
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

    public function testGetAccountInfoWithMockSuccess(): void
    {
        $mock = $this->createMockClient();
        $controller = new AccountController($mock);

        $response = $controller->getAccountInfo([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret'
        ]);

        $this->assertTrue($response['success']);
    }

    public function testGetOpenOrdersWithMockSuccess(): void
    {
        $mock = $this->createMockClient();
        $controller = new AccountController($mock);

        $response = $controller->getOpenOrders([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret'
        ]);

        $this->assertTrue($response['success']);
    }

    public function testGetOrderHistoryWithMockSuccess(): void
    {
        $mock = $this->createMockClient();
        $controller = new AccountController($mock);

        $response = $controller->getOrderHistory([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT'
        ]);

        $this->assertTrue($response['success']);
    }

    public function testGetAssetBalanceWithMockSuccess(): void
    {
        $mock = new class implements ClientInterface {
            public function get(string $endpoint, array $params = []): array
            {
                return [
                    'balances' => [
                        ['asset' => 'BTC', 'free' => '1.5', 'locked' => '0.1'],
                        ['asset' => 'ETH', 'free' => '10.0', 'locked' => '0.0'],
                    ]
                ];
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

        $controller = new AccountController($mock);

        $response = $controller->getAssetBalance([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'asset' => 'BTC'
        ]);

        $this->assertTrue($response['success']);
        $this->assertSame('BTC', $response['data']['asset']);
        $this->assertSame('1.5', $response['data']['free']);
    }

    public function testGetAssetBalanceNotFound(): void
    {
        $mock = new class implements ClientInterface {
            public function get(string $endpoint, array $params = []): array
            {
                return [
                    'balances' => [
                        ['asset' => 'ETH', 'free' => '10.0', 'locked' => '0.0'],
                    ]
                ];
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

        $controller = new AccountController($mock);

        $response = $controller->getAssetBalance([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'asset' => 'BTC'
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('não encontrado', $response['error']);
    }

    public function testGetMyTradesWithMockSuccess(): void
    {
        $mock = $this->createMockClient();
        $controller = new AccountController($mock);

        $response = $controller->getMyTrades([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT'
        ]);

        $this->assertTrue($response['success']);
    }

    public function testGetAccountStatusWithMockSuccess(): void
    {
        $mock = $this->createMockClient();
        $controller = new AccountController($mock);

        $response = $controller->getAccountStatus([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret'
        ]);

        $this->assertTrue($response['success']);
    }

    public function testGetApiTradingStatusWithMockSuccess(): void
    {
        $mock = $this->createMockClient();
        $controller = new AccountController($mock);

        $response = $controller->getApiTradingStatus([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret'
        ]);

        $this->assertTrue($response['success']);
    }

    public function testGetCapitalConfigWithMockSuccess(): void
    {
        $mock = $this->createMockClient();
        $controller = new AccountController($mock);

        $response = $controller->getCapitalConfig([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret'
        ]);

        $this->assertTrue($response['success']);
    }

    public function testDustTransferWithMockSuccess(): void
    {
        $mock = $this->createMockClient();
        $controller = new AccountController($mock);

        $response = $controller->dustTransfer([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'assets' => ['SHIB', 'DOGE']
        ]);

        $this->assertTrue($response['success']);
    }

    public function testAssetDividendWithMockSuccess(): void
    {
        $mock = $this->createMockClient();
        $controller = new AccountController($mock);

        $response = $controller->assetDividend([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret'
        ]);

        $this->assertTrue($response['success']);
    }

    public function testConvertTransferableWithMockSuccess(): void
    {
        $mock = $this->createMockClient();
        $controller = new AccountController($mock);

        $response = $controller->convertTransferable([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'fromAsset' => 'BTC',
            'toAsset' => 'USDT'
        ]);

        $this->assertTrue($response['success']);
    }

    public function testP2pOrdersWithMockSuccess(): void
    {
        $mock = $this->createMockClient();
        $controller = new AccountController($mock);

        $response = $controller->p2pOrders([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret'
        ]);

        $this->assertTrue($response['success']);
    }

    public function testGetAccountInfoWithMockException(): void
    {
        $mock = $this->createExceptionMock('Account error');
        $controller = new AccountController($mock);

        $response = $controller->getAccountInfo([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret'
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Account error', $response['error']);
    }

    public function testGetOpenOrdersWithMockException(): void
    {
        $mock = $this->createExceptionMock('Open orders error');
        $controller = new AccountController($mock);

        $response = $controller->getOpenOrders([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret'
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Open orders error', $response['error']);
    }

    public function testGetOrderHistoryWithMockException(): void
    {
        $mock = $this->createExceptionMock('Order history error');
        $controller = new AccountController($mock);

        $response = $controller->getOrderHistory([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT'
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Order history error', $response['error']);
    }

    public function testGetAssetBalanceWithMockException(): void
    {
        $mock = $this->createExceptionMock('Balance error');
        $controller = new AccountController($mock);

        $response = $controller->getAssetBalance([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'asset' => 'BTC'
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Balance error', $response['error']);
    }

    public function testGetMyTradesWithMockException(): void
    {
        $mock = $this->createExceptionMock('Trades error');
        $controller = new AccountController($mock);

        $response = $controller->getMyTrades([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT'
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Trades error', $response['error']);
    }

    public function testGetAccountStatusWithMockException(): void
    {
        $mock = $this->createExceptionMock('Status error');
        $controller = new AccountController($mock);

        $response = $controller->getAccountStatus([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret'
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Status error', $response['error']);
    }

    public function testGetApiTradingStatusWithMockException(): void
    {
        $mock = $this->createExceptionMock('Trading status error');
        $controller = new AccountController($mock);

        $response = $controller->getApiTradingStatus([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret'
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Trading status error', $response['error']);
    }

    public function testGetCapitalConfigWithMockException(): void
    {
        $mock = $this->createExceptionMock('Capital error');
        $controller = new AccountController($mock);

        $response = $controller->getCapitalConfig([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret'
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Capital error', $response['error']);
    }

    public function testDustTransferWithMockException(): void
    {
        $mock = $this->createExceptionMock('Dust error');
        $controller = new AccountController($mock);

        $response = $controller->dustTransfer([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'assets' => ['SHIB']
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Dust error', $response['error']);
    }

    public function testAssetDividendWithMockException(): void
    {
        $mock = $this->createExceptionMock('Dividend error');
        $controller = new AccountController($mock);

        $response = $controller->assetDividend([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret'
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Dividend error', $response['error']);
    }

    public function testConvertTransferableWithMockException(): void
    {
        $mock = $this->createExceptionMock('Convert error');
        $controller = new AccountController($mock);

        $response = $controller->convertTransferable([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'fromAsset' => 'BTC',
            'toAsset' => 'USDT'
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Convert error', $response['error']);
    }

    public function testP2pOrdersWithMockException(): void
    {
        $mock = $this->createExceptionMock('P2P error');
        $controller = new AccountController($mock);

        $response = $controller->p2pOrders([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret'
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('P2P error', $response['error']);
    }
}
