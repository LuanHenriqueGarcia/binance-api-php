<?php

use BinanceAPI\Router;
use BinanceAPI\Config;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    protected function setUp(): void
    {
        Config::fake([]);
    }

    public function testSendResponseSuccessSets200(): void
    {
        $router = new Router('GET', '/test', []);
        $output = $this->invokeSendResponse($router, ['success' => true, 'data' => []]);

        $this->assertSame(200, http_response_code());
        $this->assertStringContainsString('"success": true', $output);
    }

    public function testSendResponseErrorSets400(): void
    {
        $router = new Router('GET', '/test', []);
        $output = $this->invokeSendResponse($router, ['success' => false, 'error' => 'fail']);

        $this->assertSame(400, http_response_code());
        $this->assertStringContainsString('"success": false', $output);
    }

    public function testSendResponseUsesCustomCode(): void
    {
        $router = new Router('GET', '/test', []);
        $output = $this->invokeSendResponse($router, ['success' => false, 'error' => 'rate', 'code' => 429]);

        $this->assertSame(429, http_response_code());
        $this->assertStringContainsString('"code": 429', $output);
    }

    public function testAuthRequired(): void
    {
        Config::fake([
            'BASIC_AUTH_USER' => 'u',
            'BASIC_AUTH_PASSWORD' => 'p'
        ]);

        $_SERVER['PHP_AUTH_USER'] = null;
        $_SERVER['PHP_AUTH_PW'] = null;

        $router = new Router('GET', '/api/general/ping', []);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $this->assertSame(401, http_response_code());
        $decoded = json_decode($output, true);
        $this->assertFalse($decoded['success']);
        $this->assertSame('Não autorizado', $decoded['error']);
    }

    public function testAuthPasses(): void
    {
        Config::fake([
            'BASIC_AUTH_USER' => 'u',
            'BASIC_AUTH_PASSWORD' => 'p'
        ]);

        $_SERVER['PHP_AUTH_USER'] = 'u';
        $_SERVER['PHP_AUTH_PW'] = 'p';

        $router = new Router('GET', '/', []);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $this->assertSame(200, http_response_code());
        $this->assertStringContainsString('Binance API REST', $output);
    }

    public function testDispatchRootReturnsMessage(): void
    {
        Config::fake([]);
        $router = new Router('GET', '/', []);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $this->assertSame(200, http_response_code());
        $this->assertStringContainsString('Binance API REST', $output);
    }

    public function testDispatchUnknownReturns404(): void
    {
        Config::fake([]);
        $router = new Router('GET', '/api/unknown', []);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $this->assertSame(404, http_response_code());
        $decoded = json_decode($output, true);
        $this->assertFalse($decoded['success']);
        $this->assertSame('Endpoint não encontrado', $decoded['error']);
    }

    /**
     * @param array<string,mixed> $data
     */
    private function invokeSendResponse(Router $router, array $data): string
    {
        $method = new ReflectionMethod(Router::class, 'sendResponse');
        $method->setAccessible(true);

        ob_start();
        $method->invoke($router, $data);
        return (string)ob_get_clean();
    }

    public function testDispatchHealthEndpoint(): void
    {
        Config::fake([]);
        $router = new Router('GET', '/api/health', []);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertArrayHasKey('success', $decoded);
        $this->assertArrayHasKey('storage_writable', $decoded);
    }

    public function testDispatchMetricsDisabled(): void
    {
        Config::fake(['METRICS_ENABLED' => false]);
        $router = new Router('GET', '/api/metrics', []);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $this->assertSame(404, http_response_code());
        $decoded = json_decode($output, true);
        $this->assertFalse($decoded['success']);
        $this->assertStringContainsString('disabled', $decoded['error']);
    }

    public function testDispatchMetricsEnabled(): void
    {
        Config::fake(['METRICS_ENABLED' => true]);
        $router = new Router('GET', '/api/metrics', []);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $this->assertSame(200, http_response_code());
        $decoded = json_decode($output, true);
        $this->assertTrue($decoded['success']);
        $this->assertArrayHasKey('data', $decoded);
    }

    public function testNormalizeUppercasesSymbolViaParseParams(): void
    {
        // Test normalize with actual parseParams path
        $_GET = ['symbol' => 'btcusdt'];
        $router = new Router('GET', '/', null);

        $paramsProperty = new ReflectionProperty(Router::class, 'params');
        $paramsProperty->setAccessible(true);
        $params = $paramsProperty->getValue($router);

        $this->assertSame('BTCUSDT', $params['symbol']);
        $_GET = [];
    }

    public function testSendErrorMethod(): void
    {
        $router = new Router('GET', '/test', []);
        $method = new ReflectionMethod(Router::class, 'sendError');
        $method->setAccessible(true);

        ob_start();
        $method->invoke($router, 'Test error', 400);
        $output = (string)ob_get_clean();

        $this->assertSame(400, http_response_code());
        $decoded = json_decode($output, true);
        $this->assertFalse($decoded['success']);
        $this->assertSame('Test error', $decoded['error']);
    }

    public function testDispatchGeneralUnknownAction(): void
    {
        Config::fake([]);
        $router = new Router('GET', '/api/general/unknown', []);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $this->assertSame(404, http_response_code());
        $decoded = json_decode($output, true);
        $this->assertFalse($decoded['success']);
    }

    public function testDispatchMarketUnknownAction(): void
    {
        Config::fake([]);
        $router = new Router('GET', '/api/market/unknown', []);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $this->assertSame(404, http_response_code());
    }

    public function testDispatchAccountUnknownAction(): void
    {
        Config::fake([]);
        $router = new Router('GET', '/api/account/unknown', []);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $this->assertSame(404, http_response_code());
    }

    public function testDispatchTradingUnknownAction(): void
    {
        Config::fake([]);
        $router = new Router('GET', '/api/trading/unknown', []);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $this->assertSame(404, http_response_code());
    }

    public function testRateLimitNotEnabledByDefault(): void
    {
        Config::fake([]);
        $router = new Router('GET', '/api/account/info', []);

        $method = new ReflectionMethod(Router::class, 'isRateLimited');
        $method->setAccessible(true);

        $result = $method->invoke($router, 'account');

        $this->assertFalse($result);
    }

    public function testRateLimitNotAppliedToGeneralEndpoint(): void
    {
        Config::fake(['RATE_LIMIT_ENABLED' => 'true']);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $router = new Router('GET', '/api/general/ping', []);

        $method = new ReflectionMethod(Router::class, 'isRateLimited');
        $method->setAccessible(true);

        $result = $method->invoke($router, 'general');

        $this->assertFalse($result);
    }

    public function testParseParamsGet(): void
    {
        $_GET = ['test' => 'value'];
        $router = new Router('GET', '/test', null);

        $paramsProperty = new ReflectionProperty(Router::class, 'params');
        $paramsProperty->setAccessible(true);
        $params = $paramsProperty->getValue($router);

        $this->assertSame('value', $params['test']);
        $_GET = [];
    }

    public function testCorrelationIdSetFromHeader(): void
    {
        $_SERVER['HTTP_X_CORRELATION_ID'] = 'test-correlation-id';
        Config::fake([]);
        new Router('GET', '/', []);

        $this->assertSame('test-correlation-id', Config::getRequestId());

        unset($_SERVER['HTTP_X_CORRELATION_ID']);
    }

    public function testDispatchGeneralPing(): void
    {
        Config::fake([]);
        $router = new Router('GET', '/api/general/ping', []);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $this->assertIsString($output);
        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
    }

    public function testDispatchGeneralTime(): void
    {
        Config::fake([]);
        $router = new Router('GET', '/api/general/time', []);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
    }

    public function testDispatchGeneralExchangeInfo(): void
    {
        Config::fake([]);
        $router = new Router('GET', '/api/general/exchange-info', ['symbol' => 'BTCUSDT']);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
    }

    public function testDispatchMarketTicker(): void
    {
        Config::fake([]);
        $router = new Router('GET', '/api/market/ticker', ['symbol' => 'BTCUSDT']);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
    }

    public function testDispatchMarketOrderBook(): void
    {
        Config::fake([]);
        $router = new Router('GET', '/api/market/order-book', ['symbol' => 'BTCUSDT']);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
    }

    public function testDispatchMarketTrades(): void
    {
        Config::fake([]);
        $router = new Router('GET', '/api/market/trades', ['symbol' => 'BTCUSDT']);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
    }

    public function testDispatchMarketAvgPrice(): void
    {
        Config::fake([]);
        $router = new Router('GET', '/api/market/avg-price', ['symbol' => 'BTCUSDT']);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
    }

    public function testDispatchMarketBookTicker(): void
    {
        Config::fake([]);
        $router = new Router('GET', '/api/market/book-ticker', []);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
    }

    public function testDispatchMarketAggTrades(): void
    {
        Config::fake([]);
        $router = new Router('GET', '/api/market/agg-trades', ['symbol' => 'BTCUSDT']);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
    }

    public function testDispatchMarketKlines(): void
    {
        Config::fake([]);
        $router = new Router('GET', '/api/market/klines', ['symbol' => 'BTCUSDT', 'interval' => '1h']);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
    }

    public function testDispatchMarketUiKlines(): void
    {
        Config::fake([]);
        $router = new Router('GET', '/api/market/ui-klines', ['symbol' => 'BTCUSDT', 'interval' => '1h']);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
    }

    public function testDispatchMarketHistoricalTrades(): void
    {
        Config::fake([]);
        $router = new Router('GET', '/api/market/historical-trades', ['symbol' => 'BTCUSDT']);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
    }

    public function testDispatchMarketRollingWindowTicker(): void
    {
        Config::fake([]);
        $router = new Router('GET', '/api/market/rolling-window-ticker', ['symbol' => 'BTCUSDT']);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
    }

    public function testDispatchMarketTickerPrice(): void
    {
        Config::fake([]);
        $router = new Router('GET', '/api/market/ticker-price', []);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
    }

    public function testDispatchMarketTicker24h(): void
    {
        Config::fake([]);
        $router = new Router('GET', '/api/market/ticker-24h', []);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
    }

    public function testDispatchAccountInfo(): void
    {
        Config::fake([]);
        $router = new Router('GET', '/api/account/info', []);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
    }

    public function testDispatchAccountOpenOrders(): void
    {
        Config::fake([]);
        $router = new Router('GET', '/api/account/open-orders', []);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
    }

    public function testDispatchAccountBalance(): void
    {
        Config::fake([]);
        $router = new Router('GET', '/api/account/balance', ['asset' => 'BTC']);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
    }

    public function testDispatchTradingCreateOrder(): void
    {
        Config::fake([]);
        $router = new Router('POST', '/api/trading/create-order', []);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        $this->assertFalse($decoded['success']);
    }

    public function testDispatchTradingCancelOrder(): void
    {
        Config::fake([]);
        $router = new Router('DELETE', '/api/trading/cancel-order', []);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
    }

    public function testDispatchTradingTestOrder(): void
    {
        Config::fake([]);
        $router = new Router('POST', '/api/trading/test-order', []);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
    }

    public function testDispatchTradingQueryOrder(): void
    {
        Config::fake([]);
        $router = new Router('GET', '/api/trading/query-order', ['symbol' => 'BTCUSDT']);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
    }

    public function testDispatchTradingCancelOpenOrders(): void
    {
        Config::fake([]);
        $router = new Router('DELETE', '/api/trading/cancel-open-orders', ['symbol' => 'BTCUSDT']);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
    }

    public function testDispatchTradingCreateOco(): void
    {
        Config::fake([]);
        $router = new Router('POST', '/api/trading/create-oco', []);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
    }

    public function testDispatchTradingListOco(): void
    {
        Config::fake([]);
        $router = new Router('GET', '/api/trading/list-oco', []);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
    }

    public function testDispatchTradingCancelOco(): void
    {
        Config::fake([]);
        $router = new Router('DELETE', '/api/trading/cancel-oco', []);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
    }

    public function testDispatchTradingOrderRateLimit(): void
    {
        Config::fake([]);
        $router = new Router('GET', '/api/trading/order-rate-limit', []);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
    }

    public function testDispatchTradingCommissionRate(): void
    {
        Config::fake([]);
        $router = new Router('GET', '/api/trading/commission-rate', ['symbol' => 'BTCUSDT']);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
    }

    public function testDispatchTradingCancelReplace(): void
    {
        Config::fake([]);
        $router = new Router('POST', '/api/trading/cancel-replace', []);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
    }

    public function testParseParamsPost(): void
    {
        $router = new Router('POST', '/test', ['posted' => 'data']);

        $paramsProperty = new ReflectionProperty(Router::class, 'params');
        $paramsProperty->setAccessible(true);
        $params = $paramsProperty->getValue($router);

        $this->assertSame('data', $params['posted']);
    }

    public function testParseParamsDelete(): void
    {
        $router = new Router('DELETE', '/test', ['deleted' => 'item']);

        $paramsProperty = new ReflectionProperty(Router::class, 'params');
        $paramsProperty->setAccessible(true);
        $params = $paramsProperty->getValue($router);

        $this->assertSame('item', $params['deleted']);
    }

    public function testRecordMetricsWhenEnabled(): void
    {
        Config::fake(['METRICS_ENABLED' => 'true']);
        $router = new Router('GET', '/api/health', []);

        ob_start();
        $router->dispatch();
        ob_get_clean();

        $this->assertSame(200, http_response_code());
    }

    public function testRecordMetricsWhenDisabled(): void
    {
        Config::fake(['METRICS_ENABLED' => 'false']);
        $router = new Router('GET', '/api/health', []);

        $method = new ReflectionMethod(Router::class, 'recordMetrics');
        $method->setAccessible(true);

        // Should not throw when disabled
        $method->invoke($router, 200);
        $this->assertTrue(true);
    }

    public function testIsRateLimitedForTradingEndpoint(): void
    {
        Config::fake(['RATE_LIMIT_ENABLED' => 'true']);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $router = new Router('GET', '/api/trading/query-order', []);

        $method = new ReflectionMethod(Router::class, 'isRateLimited');
        $method->setAccessible(true);

        // First hit should pass
        $result = $method->invoke($router, 'trading');
        $this->assertFalse($result);
    }

    public function testHandleHealthWithWritableStorage(): void
    {
        Config::fake(['STORAGE_PATH' => sys_get_temp_dir()]);
        $router = new Router('GET', '/api/health', []);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertTrue($decoded['success']);
        $this->assertTrue($decoded['storage_writable']);
    }

    public function testDispatchApiRoot(): void
    {
        Config::fake([]);
        $router = new Router('GET', '/api', []);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        // /api without anything else returns the root message
        $this->assertSame(200, http_response_code());
        $this->assertStringContainsString('Binance API REST', $output);
    }

    public function testDispatchEmptyPath(): void
    {
        Config::fake([]);
        $router = new Router('GET', '', []);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        // Empty path behaves like root
        $this->assertIsString($output);
    }

    public function testNormalizePreservesOtherParams(): void
    {
        $_GET = ['symbol' => 'btcusdt', 'limit' => '100', 'side' => 'BUY'];
        $router = new Router('GET', '/', null);

        $paramsProperty = new ReflectionProperty(Router::class, 'params');
        $paramsProperty->setAccessible(true);
        $params = $paramsProperty->getValue($router);

        $this->assertSame('BTCUSDT', $params['symbol']);
        $this->assertSame('100', $params['limit']);
        $this->assertSame('BUY', $params['side']);

        $_GET = [];
    }

    public function testRateLimitExceededReturns429(): void
    {
        Config::fake([
            'RATE_LIMIT_ENABLED' => 'true',
            'RATE_LIMIT_MAX' => '1',
            'RATE_LIMIT_WINDOW' => '60',
            'STORAGE_PATH' => sys_get_temp_dir()
        ]);
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';

        // First request should pass
        $router1 = new Router('GET', '/api/account/info', []);
        ob_start();
        $router1->dispatch();
        ob_get_clean();

        // Second request with same IP should be rate limited
        $router2 = new Router('GET', '/api/account/info', []);
        ob_start();
        $router2->dispatch();
        $output = (string)ob_get_clean();

        $this->assertSame(429, http_response_code());
        $this->assertStringContainsString('Rate limit', $output);

        unset($_SERVER['REMOTE_ADDR']);
    }

    public function testDispatchAccountOrderHistory(): void
    {
        Config::fake([]);
        $router = new Router('GET', '/api/account/order-history', ['symbol' => 'BTCUSDT']);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
    }

    public function testDispatchAccountMyTrades(): void
    {
        Config::fake([]);
        $router = new Router('GET', '/api/account/my-trades', ['symbol' => 'BTCUSDT']);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
    }

    public function testDispatchAccountStatus(): void
    {
        Config::fake([]);
        $router = new Router('GET', '/api/account/account-status', []);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
    }

    public function testDispatchAccountApiTradingStatus(): void
    {
        Config::fake([]);
        $router = new Router('GET', '/api/account/api-trading-status', []);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
    }

    public function testDispatchAccountCapitalConfig(): void
    {
        Config::fake([]);
        $router = new Router('GET', '/api/account/capital-config', []);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
    }

    public function testDispatchAccountDustTransfer(): void
    {
        Config::fake([]);
        $router = new Router('POST', '/api/account/dust-transfer', []);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
    }

    public function testDispatchAccountAssetDividend(): void
    {
        Config::fake([]);
        $router = new Router('GET', '/api/account/asset-dividend', []);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
    }

    public function testDispatchAccountConvertTransferable(): void
    {
        Config::fake([]);
        $router = new Router('GET', '/api/account/convert-transferable', []);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
    }

    public function testDispatchAccountP2pOrders(): void
    {
        Config::fake([]);
        $router = new Router('GET', '/api/account/p2p-orders', []);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
    }

    public function testSendResponseWithExplicitCode(): void
    {
        $router = new Router('GET', '/test', []);
        $method = new ReflectionMethod(Router::class, 'sendResponse');
        $method->setAccessible(true);

        ob_start();
        $method->invoke($router, ['success' => true, 'data' => []], 201);
        $output = (string)ob_get_clean();

        $this->assertSame(201, http_response_code());
        $this->assertStringContainsString('"success": true', $output);
    }

    public function testSendResponseWithoutSuccessKey(): void
    {
        $router = new Router('GET', '/test', []);
        $method = new ReflectionMethod(Router::class, 'sendResponse');
        $method->setAccessible(true);

        ob_start();
        $method->invoke($router, ['data' => 'test']);
        $output = (string)ob_get_clean();

        // Without 'success' key, should default to 200
        $this->assertSame(200, http_response_code());
    }

    public function testParseParamsUnknownMethod(): void
    {
        $router = new Router('OPTIONS', '/test', null);

        $paramsProperty = new ReflectionProperty(Router::class, 'params');
        $paramsProperty->setAccessible(true);
        $params = $paramsProperty->getValue($router);

        $this->assertSame([], $params);
    }

    public function testNormalizeWithNonStringSymbol(): void
    {
        $_GET = ['symbol' => ['array']];
        $router = new Router('GET', '/', null);

        $paramsProperty = new ReflectionProperty(Router::class, 'params');
        $paramsProperty->setAccessible(true);
        $params = $paramsProperty->getValue($router);

        // Non-string symbol should not be uppercased
        $this->assertSame(['array'], $params['symbol']);

        $_GET = [];
    }

    public function testDispatchCoinbaseUnknownSection(): void
    {
        Config::fake([]);
        $router = new Router('GET', '/api/coinbase/unknown/action', []);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertSame(404, http_response_code());
        $this->assertSame('Ação não encontrada', $decoded['error']);
    }

    public function testDispatchCoinbaseMarketValidationPath(): void
    {
        Config::fake([]);
        $router = new Router('GET', '/api/coinbase/market/product', []);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertSame(400, http_response_code());
        $this->assertFalse($decoded['success']);
    }

    public function testDispatchCoinbaseAccountValidationPath(): void
    {
        Config::fake([]);
        $router = new Router('GET', '/api/coinbase/account/account', []);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertSame(400, http_response_code());
        $this->assertFalse($decoded['success']);
    }

    public function testDispatchCoinbaseTradingValidationPath(): void
    {
        Config::fake([]);
        $router = new Router('POST', '/api/coinbase/trading/create-order', []);

        ob_start();
        $router->dispatch();
        $output = (string)ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertSame(400, http_response_code());
        $this->assertFalse($decoded['success']);
    }

    public function testHandleCoinbasePrivateMethodsUnknownActionByReflection(): void
    {
        $router = new Router('GET', '/api/coinbase/general/unknown', []);

        $methods = [
            'handleCoinbaseGeneral',
            'handleCoinbaseMarket',
            'handleCoinbaseAccount',
            'handleCoinbaseTrading',
        ];

        foreach ($methods as $name) {
            $method = new ReflectionMethod(Router::class, $name);
            $method->setAccessible(true);

            ob_start();
            $method->invoke($router, 'unknown-action');
            $output = (string)ob_get_clean();

            $decoded = json_decode($output, true);
            $this->assertSame('Ação não encontrada', $decoded['error']);
            $this->assertSame(404, http_response_code());
        }
    }
}
