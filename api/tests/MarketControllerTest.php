<?php

use TradersApi\Controllers\MarketController;
use TradersApi\Contracts\ClientInterface;
use TradersApi\Config;
use PHPUnit\Framework\TestCase;

class MarketControllerTest extends TestCase
{
    private MarketController $controller;

    protected function setUp(): void
    {
        Config::fake([]);
        $this->controller = new MarketController();
    }

    public function testTickerRequiresSymbol(): void
    {
        $response = $this->controller->ticker([]);
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('symbol', $response['error']);
    }

    public function testOrderBookRequiresSymbol(): void
    {
        $response = $this->controller->orderBook([]);
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('symbol', $response['error']);
    }

    public function testTradesRequireSymbol(): void
    {
        $response = $this->controller->trades([]);
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('symbol', $response['error']);
    }

    public function testAvgPriceRequiresSymbol(): void
    {
        $response = $this->controller->avgPrice([]);
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('symbol', $response['error']);
    }

    public function testAggTradesRequiresSymbol(): void
    {
        $response = $this->controller->aggTrades([]);
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('symbol', $response['error']);
    }

    public function testKlinesRequireSymbolAndInterval(): void
    {
        $response = $this->controller->klines(['symbol' => 'BTCUSDT']);
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('interval', $response['error']);
    }

    public function testKlinesRequiresSymbol(): void
    {
        $response = $this->controller->klines(['interval' => '1h']);
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('symbol', $response['error']);
    }

    public function testUiKlinesRequireSymbolAndInterval(): void
    {
        $response = $this->controller->uiKlines(['symbol' => 'BTCUSDT']);
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('interval', $response['error']);
    }

    public function testUiKlinesRequiresSymbol(): void
    {
        $response = $this->controller->uiKlines(['interval' => '1h']);
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('symbol', $response['error']);
    }

    public function testHistoricalTradesRequireSymbol(): void
    {
        $response = $this->controller->historicalTrades([]);
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('symbol', $response['error']);
    }

    public function testFormatResponseSuccess(): void
    {
        $method = new ReflectionMethod(MarketController::class, 'formatResponse');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, ['symbol' => 'BTCUSDT', 'price' => '50000']);

        $this->assertTrue($result['success']);
        $this->assertSame(['symbol' => 'BTCUSDT', 'price' => '50000'], $result['data']);
    }

    public function testFormatResponsePropagatesError(): void
    {
        $method = new ReflectionMethod(MarketController::class, 'formatResponse');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, ['success' => false, 'error' => 'test error']);

        $this->assertFalse($result['success']);
        $this->assertSame('test error', $result['error']);
    }

    public function testRollingWindowTickerRequiresSymbol(): void
    {
        $response = $this->controller->rollingWindowTicker([]);
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('symbol', $response['error']);
    }

    public function testTickerPriceNoParams(): void
    {
        $mock = $this->createMockClient();
        $controller = new MarketController($mock);

        // tickerPrice can be called without symbol (returns all)
        $response = $controller->tickerPrice([]);

        $this->assertTrue($response['success']);
        $this->assertIsArray($response['data']);
    }

    public function testTicker24hNoParams(): void
    {
        $mock = $this->createMockClient();
        $controller = new MarketController($mock);

        // ticker24h can be called without symbol (returns all)
        $response = $controller->ticker24h([]);

        $this->assertTrue($response['success']);
        $this->assertIsArray($response['data']);
    }

    public function testBookTickerNoParams(): void
    {
        $mock = $this->createMockClient();
        $controller = new MarketController($mock);

        // bookTicker can be called without symbol
        $response = $controller->bookTicker([]);

        $this->assertTrue($response['success']);
        $this->assertIsArray($response['data']);
    }

    // Test methods that make API calls with valid params
    public function testTickerWithSymbol(): void
    {
        $mock = $this->createMockClient();
        $controller = new MarketController($mock);

        $response = $controller->ticker(['symbol' => 'BTCUSDT']);

        $this->assertTrue($response['success']);
        $this->assertIsArray($response['data']);
    }

    public function testOrderBookWithSymbol(): void
    {
        $mock = $this->createMockClient();
        $controller = new MarketController($mock);

        $response = $controller->orderBook(['symbol' => 'BTCUSDT']);

        $this->assertTrue($response['success']);
        $this->assertIsArray($response['data']);
    }

    public function testOrderBookWithLimit(): void
    {
        $mock = $this->createMockClient();
        $controller = new MarketController($mock);

        $response = $controller->orderBook(['symbol' => 'BTCUSDT', 'limit' => 10]);

        $this->assertTrue($response['success']);
        $this->assertIsArray($response['data']);
    }

    public function testTradesWithSymbol(): void
    {
        $mock = $this->createMockClient();
        $controller = new MarketController($mock);

        $response = $controller->trades(['symbol' => 'BTCUSDT']);

        $this->assertTrue($response['success']);
        $this->assertIsArray($response['data']);
    }

    public function testTradesWithLimit(): void
    {
        $mock = $this->createMockClient();
        $controller = new MarketController($mock);

        $response = $controller->trades(['symbol' => 'BTCUSDT', 'limit' => 10]);

        $this->assertTrue($response['success']);
        $this->assertIsArray($response['data']);
    }

    public function testAvgPriceWithSymbol(): void
    {
        $mock = $this->createMockClient();
        $controller = new MarketController($mock);

        $response = $controller->avgPrice(['symbol' => 'BTCUSDT']);

        $this->assertTrue($response['success']);
        $this->assertIsArray($response['data']);
    }

    public function testBookTickerWithSymbol(): void
    {
        $mock = $this->createMockClient();
        $controller = new MarketController($mock);

        $response = $controller->bookTicker(['symbol' => 'BTCUSDT']);

        $this->assertTrue($response['success']);
        $this->assertIsArray($response['data']);
    }

    public function testAggTradesWithSymbol(): void
    {
        $mock = $this->createMockClient();
        $controller = new MarketController($mock);

        $response = $controller->aggTrades(['symbol' => 'BTCUSDT']);

        $this->assertTrue($response['success']);
        $this->assertIsArray($response['data']);
    }

    public function testAggTradesWithLimit(): void
    {
        $mock = $this->createMockClient();
        $controller = new MarketController($mock);

        $response = $controller->aggTrades(['symbol' => 'BTCUSDT', 'limit' => 10]);

        $this->assertTrue($response['success']);
        $this->assertIsArray($response['data']);
    }

    public function testKlinesWithValidParams(): void
    {
        $mock = $this->createMockClient();
        $controller = new MarketController($mock);

        $response = $controller->klines(['symbol' => 'BTCUSDT', 'interval' => '1h']);

        $this->assertTrue($response['success']);
        $this->assertIsArray($response['data']);
    }

    public function testKlinesWithAllParams(): void
    {
        $mock = $this->createMockClient();
        $controller = new MarketController($mock);

        $response = $controller->klines([
            'symbol' => 'BTCUSDT',
            'interval' => '1h',
            'startTime' => time() * 1000 - 86400000,
            'endTime' => time() * 1000,
            'limit' => 10
        ]);

        $this->assertTrue($response['success']);
        $this->assertIsArray($response['data']);
    }

    public function testUiKlinesWithValidParams(): void
    {
        $mock = $this->createMockClient();
        $controller = new MarketController($mock);

        $response = $controller->uiKlines(['symbol' => 'BTCUSDT', 'interval' => '1h']);

        $this->assertTrue($response['success']);
        $this->assertIsArray($response['data']);
    }

    public function testHistoricalTradesWithSymbol(): void
    {
        $mock = $this->createMockClient();
        $controller = new MarketController($mock);

        $response = $controller->historicalTrades(['symbol' => 'BTCUSDT']);

        $this->assertTrue($response['success']);
        $this->assertIsArray($response['data']);
    }

    public function testHistoricalTradesWithLimit(): void
    {
        $mock = $this->createMockClient();
        $controller = new MarketController($mock);

        $response = $controller->historicalTrades(['symbol' => 'BTCUSDT', 'limit' => 10]);

        $this->assertTrue($response['success']);
        $this->assertIsArray($response['data']);
    }

    public function testRollingWindowTickerWithSymbol(): void
    {
        $mock = $this->createMockClient();
        $controller = new MarketController($mock);

        $response = $controller->rollingWindowTicker(['symbol' => 'BTCUSDT']);

        $this->assertTrue($response['success']);
        $this->assertIsArray($response['data']);
    }

    public function testRollingWindowTickerWithWindowSize(): void
    {
        $mock = $this->createMockClient();
        $controller = new MarketController($mock);

        $response = $controller->rollingWindowTicker([
            'symbol' => 'BTCUSDT',
            'windowSize' => '1d'
        ]);

        $this->assertTrue($response['success']);
        $this->assertIsArray($response['data']);
    }

    public function testTickerPriceWithSymbol(): void
    {
        $mock = $this->createMockClient();
        $controller = new MarketController($mock);

        $response = $controller->tickerPrice(['symbol' => 'BTCUSDT']);

        $this->assertTrue($response['success']);
        $this->assertIsArray($response['data']);
    }

    public function testTicker24hWithSymbol(): void
    {
        $mock = $this->createMockClient();
        $controller = new MarketController($mock);

        $response = $controller->ticker24h(['symbol' => 'BTCUSDT']);

        $this->assertTrue($response['success']);
        $this->assertIsArray($response['data']);
    }

    public function testFormatResponseBinanceError(): void
    {
        $method = new ReflectionMethod(MarketController::class, 'formatResponse');
        $method->setAccessible(true);

        // MarketController formatResponse wraps everything as success
        $result = $method->invoke($this->controller, ['code' => -1121, 'msg' => 'Invalid symbol']);

        $this->assertTrue($result['success']);
        $this->assertSame(['code' => -1121, 'msg' => 'Invalid symbol'], $result['data']);
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

    public function testTickerWithMockSuccess(): void
    {
        $mock = $this->createMockClient();
        $controller = new MarketController($mock);

        $response = $controller->ticker(['symbol' => 'BTCUSDT']);

        $this->assertTrue($response['success']);
    }

    public function testOrderBookWithMockSuccess(): void
    {
        $mock = $this->createMockClient();
        $controller = new MarketController($mock);

        $response = $controller->orderBook(['symbol' => 'BTCUSDT']);

        $this->assertTrue($response['success']);
    }

    public function testTradesWithMockSuccess(): void
    {
        $mock = $this->createMockClient();
        $controller = new MarketController($mock);

        $response = $controller->trades(['symbol' => 'BTCUSDT']);

        $this->assertTrue($response['success']);
    }

    public function testAvgPriceWithMockSuccess(): void
    {
        $mock = $this->createMockClient();
        $controller = new MarketController($mock);

        $response = $controller->avgPrice(['symbol' => 'BTCUSDT']);

        $this->assertTrue($response['success']);
    }

    public function testBookTickerWithMockSuccess(): void
    {
        $mock = $this->createMockClient();
        $controller = new MarketController($mock);

        $response = $controller->bookTicker(['symbol' => 'BTCUSDT']);

        $this->assertTrue($response['success']);
    }

    public function testAggTradesWithMockSuccess(): void
    {
        $mock = $this->createMockClient();
        $controller = new MarketController($mock);

        $response = $controller->aggTrades(['symbol' => 'BTCUSDT']);

        $this->assertTrue($response['success']);
    }

    public function testKlinesWithMockSuccess(): void
    {
        $mock = $this->createMockClient();
        $controller = new MarketController($mock);

        $response = $controller->klines(['symbol' => 'BTCUSDT', 'interval' => '1h']);

        $this->assertTrue($response['success']);
    }

    public function testUiKlinesWithMockSuccess(): void
    {
        $mock = $this->createMockClient();
        $controller = new MarketController($mock);

        $response = $controller->uiKlines(['symbol' => 'BTCUSDT', 'interval' => '1h']);

        $this->assertTrue($response['success']);
    }

    public function testHistoricalTradesWithMockSuccess(): void
    {
        $mock = $this->createMockClient();
        $controller = new MarketController($mock);

        $response = $controller->historicalTrades(['symbol' => 'BTCUSDT']);

        $this->assertTrue($response['success']);
    }

    public function testRollingWindowTickerWithMockSuccess(): void
    {
        $mock = $this->createMockClient();
        $controller = new MarketController($mock);

        $response = $controller->rollingWindowTicker(['symbol' => 'BTCUSDT']);

        $this->assertTrue($response['success']);
    }

    public function testTickerPriceWithMockSuccess(): void
    {
        $mock = $this->createMockClient();
        $controller = new MarketController($mock);

        $response = $controller->tickerPrice(['symbol' => 'BTCUSDT']);

        $this->assertTrue($response['success']);
    }

    public function testTicker24hWithMockSuccess(): void
    {
        $mock = $this->createMockClient();
        $controller = new MarketController($mock);

        $response = $controller->ticker24h(['symbol' => 'BTCUSDT']);

        $this->assertTrue($response['success']);
    }

    public function testTickerWithMockException(): void
    {
        $mock = $this->createExceptionMock('Ticker error');
        $controller = new MarketController($mock);

        $response = $controller->ticker(['symbol' => 'BTCUSDT']);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Ticker error', $response['error']);
    }

    public function testOrderBookWithMockException(): void
    {
        $mock = $this->createExceptionMock('OrderBook error');
        $controller = new MarketController($mock);

        $response = $controller->orderBook(['symbol' => 'BTCUSDT']);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('OrderBook error', $response['error']);
    }

    public function testTradesWithMockException(): void
    {
        $mock = $this->createExceptionMock('Trades error');
        $controller = new MarketController($mock);

        $response = $controller->trades(['symbol' => 'BTCUSDT']);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Trades error', $response['error']);
    }

    public function testAvgPriceWithMockException(): void
    {
        $mock = $this->createExceptionMock('AvgPrice error');
        $controller = new MarketController($mock);

        $response = $controller->avgPrice(['symbol' => 'BTCUSDT']);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('AvgPrice error', $response['error']);
    }

    public function testBookTickerWithMockException(): void
    {
        $mock = $this->createExceptionMock('BookTicker error');
        $controller = new MarketController($mock);

        $response = $controller->bookTicker(['symbol' => 'BTCUSDT']);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('BookTicker error', $response['error']);
    }

    public function testAggTradesWithMockException(): void
    {
        $mock = $this->createExceptionMock('AggTrades error');
        $controller = new MarketController($mock);

        $response = $controller->aggTrades(['symbol' => 'BTCUSDT']);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('AggTrades error', $response['error']);
    }

    public function testKlinesWithMockException(): void
    {
        $mock = $this->createExceptionMock('Klines error');
        $controller = new MarketController($mock);

        $response = $controller->klines(['symbol' => 'BTCUSDT', 'interval' => '1h']);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Klines error', $response['error']);
    }

    public function testUiKlinesWithMockException(): void
    {
        $mock = $this->createExceptionMock('UiKlines error');
        $controller = new MarketController($mock);

        $response = $controller->uiKlines(['symbol' => 'BTCUSDT', 'interval' => '1h']);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('UiKlines error', $response['error']);
    }

    public function testHistoricalTradesWithMockException(): void
    {
        $mock = $this->createExceptionMock('Historical error');
        $controller = new MarketController($mock);

        $response = $controller->historicalTrades(['symbol' => 'BTCUSDT']);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Historical error', $response['error']);
    }

    public function testRollingWindowTickerWithMockException(): void
    {
        $mock = $this->createExceptionMock('Rolling error');
        $controller = new MarketController($mock);

        $response = $controller->rollingWindowTicker(['symbol' => 'BTCUSDT']);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Rolling error', $response['error']);
    }

    public function testTickerPriceWithMockException(): void
    {
        $mock = $this->createExceptionMock('TickerPrice error');
        $controller = new MarketController($mock);

        $response = $controller->tickerPrice(['symbol' => 'BTCUSDT']);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('TickerPrice error', $response['error']);
    }

    public function testTicker24hWithMockException(): void
    {
        $mock = $this->createExceptionMock('Ticker24h error');
        $controller = new MarketController($mock);

        $response = $controller->ticker24h(['symbol' => 'BTCUSDT']);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Ticker24h error', $response['error']);
    }

    public function testTickerPriceNoParamsWithMock(): void
    {
        $mock = $this->createMockClient();
        $controller = new MarketController($mock);

        $response = $controller->tickerPrice([]);

        $this->assertTrue($response['success']);
    }

    public function testTicker24hNoParamsWithMock(): void
    {
        $mock = $this->createMockClient();
        $controller = new MarketController($mock);

        $response = $controller->ticker24h([]);

        $this->assertTrue($response['success']);
    }

    public function testBookTickerNoParamsWithMock(): void
    {
        $mock = $this->createMockClient();
        $controller = new MarketController($mock);

        $response = $controller->bookTicker([]);

        $this->assertTrue($response['success']);
    }
}
