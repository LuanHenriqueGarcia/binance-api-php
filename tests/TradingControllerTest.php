<?php

use BinanceAPI\Controllers\TradingController;
use PHPUnit\Framework\TestCase;

class TradingControllerTest extends TestCase
{
    private TradingController $controller;

    protected function setUp(): void
    {
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
}
