<?php

use BinanceAPI\Controllers\CoinbaseGeneralController;
use BinanceAPI\Contracts\ClientInterface;
use PHPUnit\Framework\TestCase;

class CoinbaseGeneralControllerTest extends TestCase
{
    public function testPingDelegatesToTime(): void
    {
        $controller = new CoinbaseGeneralController($this->createClient(['epoch' => 123]));

        $response = $controller->ping();

        $this->assertTrue($response['success']);
        $this->assertSame(['epoch' => 123], $response['data']);
    }

    public function testTimeReturnsClientErrorAsIs(): void
    {
        $controller = new CoinbaseGeneralController($this->createClient([
            'success' => false,
            'error' => 'from-client'
        ]));

        $response = $controller->time();

        $this->assertFalse($response['success']);
        $this->assertSame('from-client', $response['error']);
    }

    public function testTimeHandlesException(): void
    {
        $client = new class implements ClientInterface {
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

        $controller = new CoinbaseGeneralController($client);
        $response = $controller->time();

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Falha ao obter hora do servidor Coinbase', $response['error']);
        $this->assertStringContainsString('boom', $response['error']);
    }

    private function createClient(array $payload): ClientInterface
    {
        return new class($payload) implements ClientInterface {
            public function __construct(private array $payload)
            {
            }

            public function get(string $endpoint, array $params = []): array
            {
                return $this->payload;
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
    }
}
