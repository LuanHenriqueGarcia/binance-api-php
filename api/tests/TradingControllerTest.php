<?php

use TradersApi\Controllers\TradingController;
use TradersApi\Contracts\ClientInterface;
use TradersApi\Config;
use PHPUnit\Framework\TestCase;

class TradingControllerTest extends TestCase
{
    private TradingController $controller;

    protected function setUp(): void
    {
        Config::fake([]);
        $this->controller = new TradingController();
    }

    public function testRequiresCredentials(): void
    {
        $response = $this->controller->createOrder([]);
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('api_key', $response['error']);
    }

    public function testRejectsInvalidType(): void
    {
        $response = $this->controller->createOrder([
            'api_key' => 'k',
            'secret_key' => 's',
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'type' => 'UNKNOWN',
            'quantity' => '1'
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('type', $response['error']);
    }

    public function testRejectsInvalidSide(): void
    {
        $response = $this->controller->createOrder([
            'api_key' => 'k',
            'secret_key' => 's',
            'symbol' => 'BTCUSDT',
            'side' => 'HOLD',
            'type' => 'LIMIT',
            'quantity' => '1',
            'price' => '10'
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('side', $response['error']);
    }

    public function testMarketRequiresQuantityOrQuote(): void
    {
        $response = $this->controller->createOrder([
            'api_key' => 'k',
            'secret_key' => 's',
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'type' => 'MARKET'
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('quantity', $response['error']);
    }

    public function testLimitRequiresPrice(): void
    {
        $response = $this->controller->createOrder([
            'api_key' => 'k',
            'secret_key' => 's',
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'type' => 'LIMIT',
            'quantity' => '0.001'
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('price', $response['error']);
    }

    public function testInvalidSymbol(): void
    {
        $response = $this->controller->createOrder([
            'api_key' => 'k',
            'secret_key' => 's',
            'symbol' => 'BTC',
            'side' => 'BUY',
            'type' => 'LIMIT',
            'quantity' => '0.001',
            'price' => '10'
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('symbol', $response['error']);
    }

    public function testInvalidTimeInForce(): void
    {
        $response = $this->controller->createOrder([
            'api_key' => 'k',
            'secret_key' => 's',
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'type' => 'LIMIT',
            'quantity' => '0.001',
            'price' => '10',
            'timeInForce' => 'ABC'
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('timeInForce', $response['error']);
    }

    public function testStopRequiresStopPrice(): void
    {
        $response = $this->controller->createOrder([
            'api_key' => 'k',
            'secret_key' => 's',
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'type' => 'STOP_LOSS',
            'quantity' => '0.001'
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('stopPrice', $response['error']);
    }

    public function testQueryOrderRequiresIdentifier(): void
    {
        $response = $this->controller->queryOrder([
            'api_key' => 'k',
            'secret_key' => 's',
            'symbol' => 'BTCUSDT',
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('orderId', $response['error']);
    }

    public function testCancelOpenOrdersRequiresSymbol(): void
    {
        $response = $this->controller->cancelOpenOrders([
            'api_key' => 'k',
            'secret_key' => 's',
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('symbol', $response['error']);
    }

    public function testCreateOcoRequiresStopPrice(): void
    {
        $response = $this->controller->createOco([
            'api_key' => 'k',
            'secret_key' => 's',
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'quantity' => '0.1',
            'price' => '1.0',
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('stopPrice', $response['error']);
    }

    public function testCommissionRateRequiresSymbol(): void
    {
        $response = $this->controller->commissionRate([
            'api_key' => 'k',
            'secret_key' => 's',
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('symbol', $response['error']);
    }

    public function testCancelReplaceRequiresCancelIdentifier(): void
    {
        $response = $this->controller->cancelReplace([
            'api_key' => 'k',
            'secret_key' => 's',
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'type' => 'LIMIT',
            'quantity' => '0.001',
            'price' => '10',
            'cancelReplaceMode' => 'STOP_ON_FAILURE'
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('cancelOrderId', $response['error']);
    }

    public function testCancelReplaceRequiresMode(): void
    {
        $response = $this->controller->cancelReplace([
            'api_key' => 'k',
            'secret_key' => 's',
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'type' => 'LIMIT',
            'quantity' => '0.001',
            'price' => '10',
            'cancelOrderId' => '1',
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('cancelReplaceMode', $response['error']);
    }

    public function testFormatResponseSuccess(): void
    {
        $method = new ReflectionMethod(TradingController::class, 'formatResponse');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, ['orderId' => '12345']);

        $this->assertTrue($result['success']);
        $this->assertSame(['orderId' => '12345'], $result['data']);
    }

    public function testFormatResponsePropagatesError(): void
    {
        $method = new ReflectionMethod(TradingController::class, 'formatResponse');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, ['success' => false, 'error' => 'Order rejected']);

        $this->assertFalse($result['success']);
        $this->assertSame('Order rejected', $result['error']);
    }

    public function testCancelOrderRequiresKeys(): void
    {
        $response = $this->controller->cancelOrder([]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('api_key', $response['error']);
    }

    public function testCancelOrderRequiresSymbol(): void
    {
        $response = $this->controller->cancelOrder([
            'api_key' => 'k',
            'secret_key' => 's',
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('symbol', $response['error']);
    }

    public function testCancelOrderRequiresOrderId(): void
    {
        $response = $this->controller->cancelOrder([
            'api_key' => 'k',
            'secret_key' => 's',
            'symbol' => 'BTCUSDT',
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('orderId', $response['error']);
    }

    public function testTestOrderRequiresKeys(): void
    {
        $response = $this->controller->testOrder([]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('api_key', $response['error']);
    }

    public function testListOcoRequiresKeys(): void
    {
        $response = $this->controller->listOco([]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('api_key', $response['error']);
    }

    public function testCancelOcoRequiresKeys(): void
    {
        $response = $this->controller->cancelOco([]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('api_key', $response['error']);
    }

    public function testCancelOcoRequiresOrderIdOrListClientOrderId(): void
    {
        $response = $this->controller->cancelOco([
            'api_key' => 'k',
            'secret_key' => 's',
            'symbol' => 'BTCUSDT',
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('orderListId', $response['error']);
    }

    public function testOrderRateLimitRequiresKeys(): void
    {
        $response = $this->controller->orderRateLimit([]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('api_key', $response['error']);
    }

    public function testFormatResponseBinanceError(): void
    {
        $method = new ReflectionMethod(TradingController::class, 'formatResponse');
        $method->setAccessible(true);

        // TradingController formatResponse wraps everything as success
        $result = $method->invoke($this->controller, ['code' => -1013, 'msg' => 'Invalid quantity']);

        $this->assertTrue($result['success']);
        $this->assertSame(['code' => -1013, 'msg' => 'Invalid quantity'], $result['data']);
    }

    // Test with valid params using mock client
    public function testCreateOrderWithValidParams(): void
    {
        $mock = $this->createMockClient();
        $controller = new TradingController($mock);

        $response = $controller->createOrder([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'type' => 'LIMIT',
            'quantity' => '0.001',
            'price' => '10000',
            'timeInForce' => 'GTC'
        ]);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('success', $response);
        $this->assertTrue($response['success']);
    }

    public function testCreateMarketOrderWithQuantity(): void
    {
        $mock = $this->createMockClient();
        $controller = new TradingController($mock);

        $response = $controller->createOrder([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'type' => 'MARKET',
            'quantity' => '0.001'
        ]);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testCreateMarketOrderWithQuoteOrderQty(): void
    {
        $mock = $this->createMockClient();
        $controller = new TradingController($mock);

        $response = $controller->createOrder([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'type' => 'MARKET',
            'quoteOrderQty' => '100'
        ]);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testCreateStopLossOrder(): void
    {
        $mock = $this->createMockClient();
        $controller = new TradingController($mock);

        $response = $controller->createOrder([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT',
            'side' => 'SELL',
            'type' => 'STOP_LOSS',
            'quantity' => '0.001',
            'stopPrice' => '9000'
        ]);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testCreateStopLossLimitOrder(): void
    {
        $mock = $this->createMockClient();
        $controller = new TradingController($mock);

        $response = $controller->createOrder([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT',
            'side' => 'SELL',
            'type' => 'STOP_LOSS_LIMIT',
            'quantity' => '0.001',
            'stopPrice' => '9000',
            'price' => '8900',
            'timeInForce' => 'GTC'
        ]);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testCreateTakeProfitOrder(): void
    {
        $mock = $this->createMockClient();
        $controller = new TradingController($mock);

        $response = $controller->createOrder([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT',
            'side' => 'SELL',
            'type' => 'TAKE_PROFIT',
            'quantity' => '0.001',
            'stopPrice' => '11000'
        ]);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testCreateTakeProfitLimitOrder(): void
    {
        $mock = $this->createMockClient();
        $controller = new TradingController($mock);

        $response = $controller->createOrder([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT',
            'side' => 'SELL',
            'type' => 'TAKE_PROFIT_LIMIT',
            'quantity' => '0.001',
            'stopPrice' => '11000',
            'price' => '11100',
            'timeInForce' => 'GTC'
        ]);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testCreateLimitMakerOrder(): void
    {
        $mock = $this->createMockClient();
        $controller = new TradingController($mock);

        $response = $controller->createOrder([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'type' => 'LIMIT_MAKER',
            'quantity' => '0.001',
            'price' => '9000'
        ]);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testCancelOrderWithKeys(): void
    {
        $mock = $this->createMockClient();
        $controller = new TradingController($mock);

        $response = $controller->cancelOrder([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT',
            'orderId' => '12345'
        ]);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testTestOrderWithKeys(): void
    {
        $mock = $this->createMockClient();
        $controller = new TradingController($mock);

        $response = $controller->testOrder([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'type' => 'LIMIT',
            'quantity' => '0.001',
            'price' => '10000',
            'timeInForce' => 'GTC'
        ]);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testQueryOrderWithOrderId(): void
    {
        $mock = $this->createMockClient();
        $controller = new TradingController($mock);

        $response = $controller->queryOrder([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT',
            'orderId' => '12345'
        ]);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testQueryOrderWithOrigClientOrderId(): void
    {
        $mock = $this->createMockClient();
        $controller = new TradingController($mock);

        $response = $controller->queryOrder([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT',
            'origClientOrderId' => 'my_order_123'
        ]);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testCancelOpenOrdersWithKeys(): void
    {
        $mock = $this->createMockClient();
        $controller = new TradingController($mock);

        $response = $controller->cancelOpenOrders([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT'
        ]);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testCreateOcoWithKeys(): void
    {
        $mock = $this->createMockClient();
        $controller = new TradingController($mock);

        $response = $controller->createOco([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT',
            'side' => 'SELL',
            'quantity' => '0.001',
            'price' => '11000',
            'stopPrice' => '9000'
        ]);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testCreateOcoWithAllParams(): void
    {
        $mock = $this->createMockClient();
        $controller = new TradingController($mock);

        $response = $controller->createOco([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT',
            'side' => 'SELL',
            'quantity' => '0.001',
            'price' => '11000',
            'stopPrice' => '9000',
            'stopLimitPrice' => '8900',
            'stopLimitTimeInForce' => 'GTC'
        ]);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testListOcoWithKeys(): void
    {
        $mock = $this->createMockClient();
        $controller = new TradingController($mock);

        $response = $controller->listOco([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret'
        ]);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testCancelOcoWithOrderListId(): void
    {
        $mock = $this->createMockClient();
        $controller = new TradingController($mock);

        $response = $controller->cancelOco([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT',
            'orderListId' => '12345'
        ]);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testCancelOcoWithListClientOrderId(): void
    {
        $mock = $this->createMockClient();
        $controller = new TradingController($mock);

        $response = $controller->cancelOco([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT',
            'listClientOrderId' => 'my_oco_123'
        ]);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testOrderRateLimitWithKeys(): void
    {
        $mock = $this->createMockClient();
        $controller = new TradingController($mock);

        $response = $controller->orderRateLimit([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret'
        ]);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testCommissionRateWithKeys(): void
    {
        $mock = $this->createMockClient();
        $controller = new TradingController($mock);

        $response = $controller->commissionRate([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT'
        ]);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testCancelReplaceWithKeys(): void
    {
        $mock = $this->createMockClient();
        $controller = new TradingController($mock);

        $response = $controller->cancelReplace([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'type' => 'LIMIT',
            'quantity' => '0.001',
            'price' => '10000',
            'cancelReplaceMode' => 'STOP_ON_FAILURE',
            'cancelOrderId' => '12345',
            'timeInForce' => 'GTC'
        ]);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    public function testCancelReplaceWithOrigClientOrderId(): void
    {
        $mock = $this->createMockClient();
        $controller = new TradingController($mock);

        $response = $controller->cancelReplace([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'type' => 'LIMIT',
            'quantity' => '0.001',
            'price' => '10000',
            'cancelReplaceMode' => 'ALLOW_FAILURE',
            'cancelOrigClientOrderId' => 'my_order_123',
            'timeInForce' => 'GTC'
        ]);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
    }

    // ===== TESTES COM MOCK =====

    private function createMockClient(): ClientInterface
    {
        return new class implements ClientInterface {
            public array $lastCall = [];

            public function get(string $endpoint, array $params = []): array
            {
                $this->lastCall = ['method' => 'get', 'endpoint' => $endpoint, 'params' => $params];
                return ['mockData' => true, 'endpoint' => $endpoint];
            }

            public function post(string $endpoint, array $params = []): array
            {
                $this->lastCall = ['method' => 'post', 'endpoint' => $endpoint, 'params' => $params];
                return ['orderId' => '12345', 'status' => 'FILLED'];
            }

            public function delete(string $endpoint, array $params = []): array
            {
                $this->lastCall = ['method' => 'delete', 'endpoint' => $endpoint, 'params' => $params];
                return ['status' => 'CANCELED'];
            }
        };
    }

    public function testCreateOrderWithMockSuccess(): void
    {
        $mock = $this->createMockClient();
        $controller = new TradingController($mock);

        $response = $controller->createOrder([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'type' => 'LIMIT',
            'quantity' => '0.001',
            'price' => '10000',
            'timeInForce' => 'GTC'
        ]);

        $this->assertTrue($response['success']);
        $this->assertSame('12345', $response['data']['orderId']);
        $this->assertSame('FILLED', $response['data']['status']);
    }

    public function testCancelOrderWithMockSuccess(): void
    {
        $mock = $this->createMockClient();
        $controller = new TradingController($mock);

        $response = $controller->cancelOrder([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT',
            'orderId' => '12345'
        ]);

        $this->assertTrue($response['success']);
        $this->assertSame('CANCELED', $response['data']['status']);
    }

    public function testTestOrderWithMockSuccess(): void
    {
        $mock = $this->createMockClient();
        $controller = new TradingController($mock);

        $response = $controller->testOrder([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'type' => 'LIMIT',
            'quantity' => '0.001',
            'price' => '10000'
        ]);

        $this->assertTrue($response['success']);
    }

    public function testQueryOrderWithMockSuccess(): void
    {
        $mock = $this->createMockClient();
        $controller = new TradingController($mock);

        $response = $controller->queryOrder([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT',
            'orderId' => '12345'
        ]);

        $this->assertTrue($response['success']);
    }

    public function testCancelOpenOrdersWithMockSuccess(): void
    {
        $mock = $this->createMockClient();
        $controller = new TradingController($mock);

        $response = $controller->cancelOpenOrders([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT'
        ]);

        $this->assertTrue($response['success']);
    }

    public function testCreateOcoWithMockSuccess(): void
    {
        $mock = $this->createMockClient();
        $controller = new TradingController($mock);

        $response = $controller->createOco([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT',
            'side' => 'SELL',
            'quantity' => '0.001',
            'price' => '11000',
            'stopPrice' => '9000'
        ]);

        $this->assertTrue($response['success']);
    }

    public function testListOcoWithMockSuccess(): void
    {
        $mock = $this->createMockClient();
        $controller = new TradingController($mock);

        $response = $controller->listOco([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret'
        ]);

        $this->assertTrue($response['success']);
    }

    public function testCancelOcoWithMockSuccess(): void
    {
        $mock = $this->createMockClient();
        $controller = new TradingController($mock);

        $response = $controller->cancelOco([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'orderListId' => '12345'
        ]);

        $this->assertTrue($response['success']);
    }

    public function testOrderRateLimitWithMockSuccess(): void
    {
        $mock = $this->createMockClient();
        $controller = new TradingController($mock);

        $response = $controller->orderRateLimit([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret'
        ]);

        $this->assertTrue($response['success']);
    }

    public function testCommissionRateWithMockSuccess(): void
    {
        $mock = $this->createMockClient();
        $controller = new TradingController($mock);

        $response = $controller->commissionRate([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT'
        ]);

        $this->assertTrue($response['success']);
    }

    public function testCancelReplaceWithMockSuccess(): void
    {
        $mock = $this->createMockClient();
        $controller = new TradingController($mock);

        $response = $controller->cancelReplace([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'type' => 'LIMIT',
            'quantity' => '0.001',
            'price' => '10000',
            'cancelReplaceMode' => 'STOP_ON_FAILURE',
            'cancelOrderId' => '12345'
        ]);

        $this->assertTrue($response['success']);
    }

    public function testCreateOrderWithMockException(): void
    {
        $mock = new class implements ClientInterface {
            public function get(string $endpoint, array $params = []): array
            {
                throw new \Exception('Mock connection error');
            }

            public function post(string $endpoint, array $params = []): array
            {
                throw new \Exception('Mock connection error');
            }

            public function delete(string $endpoint, array $params = []): array
            {
                throw new \Exception('Mock connection error');
            }
        };

        $controller = new TradingController($mock);

        $response = $controller->createOrder([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'type' => 'LIMIT',
            'quantity' => '0.001',
            'price' => '10000'
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Mock connection error', $response['error']);
    }

    public function testCancelOrderWithMockException(): void
    {
        $mock = new class implements ClientInterface {
            public function get(string $endpoint, array $params = []): array
            {
                throw new \Exception('Cancel error');
            }

            public function post(string $endpoint, array $params = []): array
            {
                throw new \Exception('Cancel error');
            }

            public function delete(string $endpoint, array $params = []): array
            {
                throw new \Exception('Cancel error');
            }
        };

        $controller = new TradingController($mock);

        $response = $controller->cancelOrder([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT',
            'orderId' => '12345'
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Cancel error', $response['error']);
    }

    public function testTestOrderWithMockException(): void
    {
        $mock = new class implements ClientInterface {
            public function get(string $endpoint, array $params = []): array
            {
                throw new \Exception('Test error');
            }

            public function post(string $endpoint, array $params = []): array
            {
                throw new \Exception('Test error');
            }

            public function delete(string $endpoint, array $params = []): array
            {
                throw new \Exception('Test error');
            }
        };

        $controller = new TradingController($mock);

        $response = $controller->testOrder([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'type' => 'MARKET',
            'quantity' => '0.001'
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Test error', $response['error']);
    }

    public function testQueryOrderWithMockException(): void
    {
        $mock = new class implements ClientInterface {
            public function get(string $endpoint, array $params = []): array
            {
                throw new \Exception('Query error');
            }

            public function post(string $endpoint, array $params = []): array
            {
                throw new \Exception('Query error');
            }

            public function delete(string $endpoint, array $params = []): array
            {
                throw new \Exception('Query error');
            }
        };

        $controller = new TradingController($mock);

        $response = $controller->queryOrder([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT',
            'orderId' => '12345'
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Query error', $response['error']);
    }

    public function testCancelOpenOrdersWithMockException(): void
    {
        $mock = new class implements ClientInterface {
            public function get(string $endpoint, array $params = []): array
            {
                throw new \Exception('Cancel open error');
            }

            public function post(string $endpoint, array $params = []): array
            {
                throw new \Exception('Cancel open error');
            }

            public function delete(string $endpoint, array $params = []): array
            {
                throw new \Exception('Cancel open error');
            }
        };

        $controller = new TradingController($mock);

        $response = $controller->cancelOpenOrders([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT'
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Cancel open error', $response['error']);
    }

    public function testCreateOcoWithMockException(): void
    {
        $mock = new class implements ClientInterface {
            public function get(string $endpoint, array $params = []): array
            {
                throw new \Exception('OCO error');
            }

            public function post(string $endpoint, array $params = []): array
            {
                throw new \Exception('OCO error');
            }

            public function delete(string $endpoint, array $params = []): array
            {
                throw new \Exception('OCO error');
            }
        };

        $controller = new TradingController($mock);

        $response = $controller->createOco([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT',
            'side' => 'SELL',
            'quantity' => '0.001',
            'price' => '11000',
            'stopPrice' => '9000'
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('OCO error', $response['error']);
    }

    public function testListOcoWithMockException(): void
    {
        $mock = new class implements ClientInterface {
            public function get(string $endpoint, array $params = []): array
            {
                throw new \Exception('List OCO error');
            }

            public function post(string $endpoint, array $params = []): array
            {
                throw new \Exception('List OCO error');
            }

            public function delete(string $endpoint, array $params = []): array
            {
                throw new \Exception('List OCO error');
            }
        };

        $controller = new TradingController($mock);

        $response = $controller->listOco([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret'
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('List OCO error', $response['error']);
    }

    public function testCancelOcoWithMockException(): void
    {
        $mock = new class implements ClientInterface {
            public function get(string $endpoint, array $params = []): array
            {
                throw new \Exception('Cancel OCO error');
            }

            public function post(string $endpoint, array $params = []): array
            {
                throw new \Exception('Cancel OCO error');
            }

            public function delete(string $endpoint, array $params = []): array
            {
                throw new \Exception('Cancel OCO error');
            }
        };

        $controller = new TradingController($mock);

        $response = $controller->cancelOco([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'orderListId' => '12345'
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Cancel OCO error', $response['error']);
    }

    public function testOrderRateLimitWithMockException(): void
    {
        $mock = new class implements ClientInterface {
            public function get(string $endpoint, array $params = []): array
            {
                throw new \Exception('Rate limit error');
            }

            public function post(string $endpoint, array $params = []): array
            {
                throw new \Exception('Rate limit error');
            }

            public function delete(string $endpoint, array $params = []): array
            {
                throw new \Exception('Rate limit error');
            }
        };

        $controller = new TradingController($mock);

        $response = $controller->orderRateLimit([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret'
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Rate limit error', $response['error']);
    }

    public function testCommissionRateWithMockException(): void
    {
        $mock = new class implements ClientInterface {
            public function get(string $endpoint, array $params = []): array
            {
                throw new \Exception('Commission error');
            }

            public function post(string $endpoint, array $params = []): array
            {
                throw new \Exception('Commission error');
            }

            public function delete(string $endpoint, array $params = []): array
            {
                throw new \Exception('Commission error');
            }
        };

        $controller = new TradingController($mock);

        $response = $controller->commissionRate([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT'
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Commission error', $response['error']);
    }

    public function testCancelReplaceWithMockException(): void
    {
        $mock = new class implements ClientInterface {
            public function get(string $endpoint, array $params = []): array
            {
                throw new \Exception('Cancel replace error');
            }

            public function post(string $endpoint, array $params = []): array
            {
                throw new \Exception('Cancel replace error');
            }

            public function delete(string $endpoint, array $params = []): array
            {
                throw new \Exception('Cancel replace error');
            }
        };

        $controller = new TradingController($mock);

        $response = $controller->cancelReplace([
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'type' => 'LIMIT',
            'quantity' => '0.001',
            'price' => '10000',
            'cancelReplaceMode' => 'STOP_ON_FAILURE',
            'cancelOrderId' => '12345'
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Cancel replace error', $response['error']);
    }

    public function testCreateOcoInvalidSide(): void
    {
        $response = $this->controller->createOco([
            'api_key' => 'k',
            'secret_key' => 's',
            'symbol' => 'BTCUSDT',
            'side' => 'INVALID',
            'quantity' => '0.1',
            'price' => '1.0',
            'stopPrice' => '0.9',
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('side', $response['error']);
    }

    public function testCancelReplaceInvalidMode(): void
    {
        $response = $this->controller->cancelReplace([
            'api_key' => 'k',
            'secret_key' => 's',
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'type' => 'LIMIT',
            'quantity' => '0.001',
            'price' => '10',
            'cancelReplaceMode' => 'INVALID_MODE',
            'cancelOrderId' => '12345'
        ]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('cancelReplaceMode', $response['error']);
    }
}
