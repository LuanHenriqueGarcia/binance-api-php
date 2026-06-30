<?php

use TradersApi\Controllers\CoinbaseAccountController;
use TradersApi\Contracts\ClientInterface;
use TradersApi\Config;
use PHPUnit\Framework\TestCase;

class CoinbaseAccountControllerTest extends TestCase
{
    private CoinbaseAccountController $controller;

    protected function setUp(): void
    {
        Config::fake([
            'COINBASE_API_KEY' => 'test-key',
            'COINBASE_API_SECRET' => 'test-secret',
        ]);
        $this->controller = new CoinbaseAccountController($this->createMockClient());
    }

    public function testAccountsWithMockClient(): void
    {
        $response = $this->controller->accounts([]);

        $this->assertTrue($response['success']);
        $this->assertIsArray($response['data']);
    }

    public function testAccountRequiresAccountUuid(): void
    {
        $response = $this->controller->account([]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('account_uuid', $response['error']);
    }

    public function testAccountAcceptsAccountIdAlias(): void
    {
        $mock = new class implements ClientInterface {
            public string $endpoint = '';

            public function get(string $endpoint, array $params = []): array
            {
                $this->endpoint = $endpoint;
                return ['id' => 'ok'];
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

        $controller = new CoinbaseAccountController($mock);
        $response = $controller->account(['account_id' => 'abc-123']);

        $this->assertTrue($response['success']);
        $this->assertSame('/api/v3/brokerage/accounts/abc-123', $mock->endpoint);
    }

    public function testAccountsFailsWithoutCredentialsWhenNoConfig(): void
    {
        Config::fake([]);
        $controller = new CoinbaseAccountController($this->createMockClient());

        $response = $controller->accounts([]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Chaves de API', $response['error']);
    }

    public function testAccountsAllowsKeyFileWithoutApiSecret(): void
    {
        Config::fake([]);
        $controller = new CoinbaseAccountController($this->createMockClient());

        $response = $controller->accounts(['key_file' => '/tmp/key.json']);

        $this->assertTrue($response['success']);
    }

    public function testAccountsHandlesExceptionFromClient(): void
    {
        Config::fake([
            'COINBASE_API_KEY' => 'test-key',
            'COINBASE_API_SECRET' => 'test-secret',
        ]);

        $failingClient = new class implements ClientInterface {
            public function get(string $endpoint, array $params = []): array
            {
                throw new RuntimeException('client down');
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

        $controller = new CoinbaseAccountController($failingClient);
        $response = $controller->accounts([]);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Falha ao listar contas', $response['error']);
        $this->assertStringContainsString('client down', $response['error']);
    }

    public function testPrivateHelpersViaReflection(): void
    {
        $resolveCredentials = new ReflectionMethod(CoinbaseAccountController::class, 'resolveCredentials');
        $resolveCredentials->setAccessible(true);
        $validateCredentials = new ReflectionMethod(CoinbaseAccountController::class, 'validateCredentials');
        $validateCredentials->setAccessible(true);
        $formatResponse = new ReflectionMethod(CoinbaseAccountController::class, 'formatResponse');
        $formatResponse->setAccessible(true);

        $creds = $resolveCredentials->invoke($this->controller, [
            'api_key' => 'k1',
            'api_secret' => 's1',
            'key_file' => '/tmp/key.json',
        ]);

        $this->assertSame(['k1', 's1', '/tmp/key.json'], $creds);
        $this->assertNull($validateCredentials->invoke($this->controller, 'k', 's', null));
        $this->assertNull($validateCredentials->invoke($this->controller, null, null, '/tmp/key.json'));
        $this->assertStringContainsString('Chaves de API', $validateCredentials->invoke($this->controller, null, null, null));

        $error = $formatResponse->invoke($this->controller, ['success' => false, 'error' => 'x']);
        $ok = $formatResponse->invoke($this->controller, ['foo' => 'bar']);

        $this->assertFalse($error['success']);
        $this->assertTrue($ok['success']);
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
