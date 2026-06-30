<?php

use TradersApi\BinanceClient;
use TradersApi\CoinbaseClient;
use TradersApi\Config;
use PHPUnit\Framework\TestCase;

/**
 * Cobre o parsing de cabeçalhos de resposta (CURLOPT_HEADERFUNCTION) de forma
 * determinística, sem depender de chamadas de rede reais. Antes, essas linhas só
 * eram exercitadas quando uma requisição HTTP real retornava cabeçalhos.
 */
class HeaderCaptureTest extends TestCase
{
    protected function setUp(): void
    {
        Config::fake([]);
    }

    /**
     * @return array<string,string>
     */
    private function headersOf(object $client): array
    {
        $prop = new ReflectionProperty($client, 'responseHeaders');
        $prop->setAccessible(true);
        /** @var array<string,string> $headers */
        $headers = $prop->getValue($client);
        return $headers;
    }

    public function testBinanceCaptureHeaderParsesAndNormalizes(): void
    {
        $client = new BinanceClient();
        $line = "X-Mbx-Used-Weight-1m: 42\r\n";

        $len = $client->captureHeader(null, $line);

        $this->assertSame(strlen($line), $len);
        $this->assertSame('42', $this->headersOf($client)['x-mbx-used-weight-1m']);
    }

    public function testBinanceCaptureHeaderIgnoresLineWithoutColon(): void
    {
        $client = new BinanceClient();

        $client->captureHeader(null, "HTTP/1.1 200 OK\r\n");

        $this->assertSame([], $this->headersOf($client));
    }

    public function testCoinbaseCaptureHeaderParsesAndNormalizes(): void
    {
        $client = new CoinbaseClient();
        $line = "X-RateLimit-Remaining: 7\r\n";

        $len = $client->captureHeader(null, $line);

        $this->assertSame(strlen($line), $len);
        $this->assertSame('7', $this->headersOf($client)['x-ratelimit-remaining']);
    }

    public function testCoinbaseCaptureHeaderIgnoresLineWithoutColon(): void
    {
        $client = new CoinbaseClient();

        $client->captureHeader(null, "\r\n");

        $this->assertSame([], $this->headersOf($client));
    }
}
