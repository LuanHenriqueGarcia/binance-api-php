<?php

use BinanceAPI\Controllers\AccountController;
use BinanceAPI\Config;
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
}
