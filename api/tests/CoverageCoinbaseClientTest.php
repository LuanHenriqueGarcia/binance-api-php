<?php

use TradersApi\CoinbaseClient;
use TradersApi\Config;
use PHPUnit\Framework\TestCase;

/**
 * Cobre os ramos de buildJwt() no CoinbaseClient:
 *  - throw quando faltam credenciais
 *  - caminho de assinatura bem-sucedida com uma chave EC P-256 real
 *
 * A chave abaixo é uma chave de teste gerada exclusivamente para os testes
 * (não possui qualquer valor/uso em produção).
 */
class CoverageCoinbaseClientTest extends TestCase
{
    private const TEST_EC_KEY = "-----BEGIN PRIVATE KEY-----\n"
        . "MIGHAgEAMBMGByqGSM49AgEGCCqGSM49AwEHBG0wawIBAQQgsVqhcaUUQIkhwP51\n"
        . "RdNUfXDkWVciXf0dcI0aoaQ6ay2hRANCAATiXr11Wr8JKP6pXaski6hgAHP/voQm\n"
        . "/hnsUo3TIZDwQc1VpxFptC2CJ4i6cwU1JQaN/A6uV5lRov7yqpQoMrFq\n"
        . "-----END PRIVATE KEY-----\n";

    protected function setUp(): void
    {
        Config::fake([]);
    }

    public function testBuildJwtThrowsWhenCredentialsMissing(): void
    {
        $client = new CoinbaseClient();

        $method = new ReflectionMethod(CoinbaseClient::class, 'buildJwt');
        $method->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Credenciais Coinbase ausentes');
        $method->invoke($client, 'GET', '/api/v3/brokerage/accounts');
    }

    public function testBuildJwtSignsSuccessfullyWithRealEcKey(): void
    {
        $loaded = openssl_pkey_get_private(self::TEST_EC_KEY);
        if ($loaded === false) {
            $this->markTestSkipped('OpenSSL não conseguiu carregar a chave EC de teste neste ambiente');
        }

        $client = new CoinbaseClient('organizations/org/apiKeys/test-key', self::TEST_EC_KEY);

        $method = new ReflectionMethod(CoinbaseClient::class, 'buildJwt');
        $method->setAccessible(true);

        $jwt = $method->invoke($client, 'GET', '/api/v3/brokerage/accounts');

        $this->assertIsString($jwt);
        $parts = explode('.', $jwt);
        $this->assertCount(3, $parts, 'JWT deve ter header.payload.signature');
        // A assinatura JOSE para ES256 tem 64 bytes -> base64url sem padding tem 86 chars
        $this->assertSame(86, strlen($parts[2]));
    }

    public function testBuildJwtThrowsWhenSigningFails(): void
    {
        if (openssl_pkey_get_private(self::TEST_EC_KEY) === false) {
            $this->markTestSkipped('OpenSSL não conseguiu carregar a chave EC de teste neste ambiente');
        }

        // Subclasse força openssl_sign a falhar via override de signData()
        $client = new class ('organizations/org/apiKeys/test-key', self::TEST_EC_KEY) extends CoinbaseClient {
            protected function signData(string $data, string &$signature, $privateKey): bool
            {
                return false;
            }
        };

        $method = new ReflectionMethod(CoinbaseClient::class, 'buildJwt');
        $method->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Falha ao assinar JWT');
        $method->invoke($client, 'GET', '/api/v3/brokerage/accounts');
    }

    public function testLoadKeyFileReturnsNullsWhenReadFails(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cb-readfail-');
        file_put_contents($tmp, '{"name":"n","privateKey":"k"}');

        try {
            // is_file() é verdadeiro, mas readFileContents() é forçado a falhar
            $client = new class (null, null, $tmp) extends CoinbaseClient {
                protected function readFileContents(string $path)
                {
                    return false;
                }
            };

            // loadKeyFile devolveu [null, null] -> cliente fica sem credenciais
            $hasCredentials = new ReflectionMethod(CoinbaseClient::class, 'hasCredentials');
            $hasCredentials->setAccessible(true);
            $this->assertFalse($hasCredentials->invoke($client));
        } finally {
            @unlink($tmp);
        }
    }
}
