<?php

use BinanceAPI\BinanceClient;
use BinanceAPI\Config;
use PHPUnit\Framework\TestCase;

/**
 * Cobre get/post/delete/request do BinanceClient sem acessar a rede,
 * sobrescrevendo execCurl() (mesmo padrão usado no CoinbaseClientTest).
 */
class CoverageBinanceClientTest extends TestCase
{
    protected function setUp(): void
    {
        Config::fake([]);
    }

    /**
     * @param array<int,array{0:string|false,1:int,2:string}> $responses
     */
    private function clientWithCurlStub(array $responses, ?string $apiKey = null, ?string $secretKey = null): BinanceClient
    {
        $client = new class ($apiKey, $secretKey) extends BinanceClient {
            /** @var array<int,array{0:string|false,1:int,2:string}> */
            public array $stubResponses = [];
            private int $i = 0;

            protected function execCurl($ch): array
            {
                $r = $this->stubResponses[$this->i] ?? ['{}', 200, ''];
                $this->i++;
                return $r;
            }
        };
        $client->stubResponses = $responses;
        return $client;
    }

    // ---------- get() ----------

    public function testGetPublicWithoutParams(): void
    {
        $client = $this->clientWithCurlStub([['{"pong":true}', 200, '']]);

        $result = $client->get('/api/v3/ping');

        $this->assertSame(['pong' => true], $result);
    }

    public function testGetPublicWithParams(): void
    {
        $client = $this->clientWithCurlStub([['{"ok":1}', 200, '']]);

        $result = $client->get('/api/v3/ticker/price', ['symbol' => 'BTCUSDT']);

        $this->assertSame(1, $result['ok']);
    }

    public function testGetAuthenticatedSignsRequest(): void
    {
        $client = $this->clientWithCurlStub([['{"balances":[]}', 200, '']], 'key', 'secret');

        $result = $client->get('/api/v3/account');

        $this->assertArrayHasKey('balances', $result);
    }

    // ---------- post() / delete() (caminho autenticado completo) ----------

    public function testPostAuthenticatedSuccess(): void
    {
        $client = $this->clientWithCurlStub([['{"orderId":123}', 200, '']], 'key', 'secret');

        $result = $client->post('/api/v3/order', ['symbol' => 'BTCUSDT']);

        $this->assertSame(123, $result['orderId']);
    }

    public function testDeleteAuthenticatedSuccess(): void
    {
        $client = $this->clientWithCurlStub([['{"status":"CANCELED"}', 200, '']], 'key', 'secret');

        $result = $client->delete('/api/v3/order', ['symbol' => 'BTCUSDT', 'orderId' => 1]);

        $this->assertSame('CANCELED', $result['status']);
    }

    // ---------- request(): ramos de erro/sucesso ----------

    public function testRequestConnectionError(): void
    {
        $client = $this->clientWithCurlStub([[false, 0, 'Connection refused']]);

        $result = $client->get('/api/v3/ping');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Erro de conex', $result['error']);
    }

    public function testRequestHttpErrorWithMessage(): void
    {
        $client = $this->clientWithCurlStub([['{"msg":"Invalid symbol","code":-1121}', 400, '']]);

        $result = $client->get('/api/v3/ticker/price', ['symbol' => 'XXX']);

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid symbol', $result['error']);
        $this->assertSame(400, $result['code']);
    }

    public function testRequestHttpErrorWithoutMessageFallsBack(): void
    {
        $client = $this->clientWithCurlStub([['no-json-body', 404, '']]);

        $result = $client->get('/api/v3/ping');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Erro HTTP 404', $result['error']);
    }

    public function testRequestInvalidJson(): void
    {
        $client = $this->clientWithCurlStub([['not-valid-json!!!', 200, '']]);

        $result = $client->get('/api/v3/ping');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Resposta inválida', $result['error']);
    }

    public function testRequestEmptyBodyReturnsEmptyArray(): void
    {
        $client = $this->clientWithCurlStub([['', 200, '']]);

        $result = $client->get('/api/v3/ping');

        $this->assertSame([], $result);
    }

    public function testRequestRetriesOn500ThenSucceeds(): void
    {
        $client = $this->clientWithCurlStub([
            ['{"msg":"server error"}', 500, ''],
            ['{"ok":1}', 200, ''],
        ]);

        $result = $client->get('/api/v3/ping');

        $this->assertSame(1, $result['ok']);
    }

    public function testRequestExhaustsRetriesReturnsLastError(): void
    {
        // attempts 0 e 1 reentram; attempt 2 (== MAX_RETRIES) cai no ramo de erro
        $client = $this->clientWithCurlStub([
            ['{"msg":"down","code":-1}', 500, ''],
            ['{"msg":"down","code":-1}', 500, ''],
            ['{"msg":"down","code":-1}', 500, ''],
        ]);

        $result = $client->get('/api/v3/ping');

        $this->assertFalse($result['success']);
        $this->assertSame('down', $result['error']);
    }

    public function testRequestWithCaBundle(): void
    {
        $tmpCa = tempnam(sys_get_temp_dir(), 'bnb-ca-');
        file_put_contents($tmpCa, 'fake-ca-bundle');
        Config::fake(['BINANCE_CA_BUNDLE' => $tmpCa]);

        $client = $this->clientWithCurlStub([['{"ok":1}', 200, '']]);

        $result = $client->get('/api/v3/ping');
        @unlink($tmpCa);

        $this->assertSame(1, $result['ok']);
    }
}
