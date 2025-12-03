<?php

use BinanceAPI\Controllers\MarketController;
use PHPUnit\Framework\TestCase;

class MarketControllerTest extends TestCase
{
    private MarketController $controller;

    protected function setUp(): void
    {
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
}
