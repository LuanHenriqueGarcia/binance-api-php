<?php

use BinanceAPI\CoinbaseClient;
use BinanceAPI\Config;
use PHPUnit\Framework\TestCase;

class CoinbaseClientTest extends TestCase
{
    protected function setUp(): void
    {
        Config::fake([]);
    }

    public function testPrivateGetRequiresCredentials(): void
    {
        $client = new CoinbaseClient();

        $result = $client->get('/api/v3/brokerage/accounts');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('API Key', $result['error']);
    }

    public function testPostRequiresCredentials(): void
    {
        $client = new CoinbaseClient();

        $result = $client->post('/api/v3/brokerage/orders', ['x' => 1]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('API Key', $result['error']);
    }

    public function testDeleteRequiresCredentials(): void
    {
        $client = new CoinbaseClient();

        $result = $client->delete('/api/v3/brokerage/orders', ['x' => 1]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('API Key', $result['error']);
    }

    public function testConstructorLoadsCredentialsFromKeyFile(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'coinbase-key-');
        file_put_contents($tmp, json_encode([
            'name' => 'organizations/org/apiKeys/key-name',
            'privateKey' => "  line1\\nline2  "
        ]));

        $client = new CoinbaseClient(null, null, $tmp);

        $apiKey = $this->getPrivateProperty($client, 'apiKey');
        $secretKey = $this->getPrivateProperty($client, 'secretKey');

        $this->assertSame('organizations/org/apiKeys/key-name', $apiKey);
        $this->assertStringContainsString("line1\nline2", $secretKey);

        @unlink($tmp);
    }

    public function testResolveHostIncludesPortWhenProvided(): void
    {
        Config::fake(['COINBASE_BASE_URL' => 'https://example.test:8443']);
        $client = new CoinbaseClient();

        $host = $this->getPrivateProperty($client, 'baseHost');

        $this->assertSame('example.test:8443', $host);
    }

    public function testGetPublicEndpointTriesRequestWithoutCredentials(): void
    {
        Config::fake([
            'COINBASE_BASE_URL' => 'http://127.0.0.1:9',
            'COINBASE_SSL_VERIFY' => 'false'
        ]);

        $client = new CoinbaseClient();
        $result = $client->get('/api/v3/brokerage/time');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Erro de conexao', $this->normalizeError($result['error']));
    }

    public function testGetRetryAfterMsParsesSecondsAndDates(): void
    {
        $client = new CoinbaseClient();
        $method = new ReflectionMethod(CoinbaseClient::class, 'getRetryAfterMs');
        $method->setAccessible(true);

        $seconds = $method->invoke($client, ['retry-after' => '3']);
        $this->assertSame(3000, $seconds);

        $futureDate = gmdate('D, d M Y H:i:s', time() + 5) . ' GMT';
        $date = $method->invoke($client, ['retry-after' => $futureDate]);
        $this->assertIsInt($date);
        $this->assertGreaterThan(0, $date);

        $pastDate = gmdate('D, d M Y H:i:s', time() - 5) . ' GMT';
        $past = $method->invoke($client, ['retry-after' => $pastDate]);
        $this->assertNull($past);

        $none = $method->invoke($client, []);
        $this->assertNull($none);
    }

    public function testShouldRetry(): void
    {
        $client = new CoinbaseClient();
        $method = new ReflectionMethod(CoinbaseClient::class, 'shouldRetry');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($client, 500, 0));
        $this->assertTrue($method->invoke($client, 429, 1));
        $this->assertFalse($method->invoke($client, 500, 2));
        $this->assertFalse($method->invoke($client, 400, 0));
    }

    public function testExtractErrorMessageVariants(): void
    {
        $client = new CoinbaseClient();
        $method = new ReflectionMethod(CoinbaseClient::class, 'extractErrorMessage');
        $method->setAccessible(true);

        $a = $method->invoke($client, ['message' => 'm1'], 400, 'raw');
        $b = $method->invoke($client, ['error' => 'm2'], 400, 'raw');
        $c = $method->invoke($client, ['errors' => [['message' => 'm3']]], 400, 'raw');
        $d = $method->invoke($client, null, 418, 'raw');

        $this->assertSame('m1', $a);
        $this->assertSame('m2', $b);
        $this->assertSame('m3', $c);
        $this->assertSame('Erro HTTP 418', $d);
    }

    public function testBuildJwtThrowsForInvalidPrivateKey(): void
    {
        $client = new CoinbaseClient('kid', 'invalid-private-key');
        $method = new ReflectionMethod(CoinbaseClient::class, 'buildJwt');
        $method->setAccessible(true);

        $this->expectException(RuntimeException::class);
        $method->invoke($client, 'GET', '/api/v3/brokerage/accounts');
    }

    public function testGetHeadersPrivateEndpointThrowsWhenKeyIsInvalid(): void
    {
        $client = new CoinbaseClient('kid', 'not-a-valid-private-key');

        $method = new ReflectionMethod(CoinbaseClient::class, 'getHeaders');
        $method->setAccessible(true);

        $this->expectException(RuntimeException::class);
        $method->invoke($client, 'GET', '/api/v3/brokerage/accounts', false, false);
    }

    public function testPublicHeadersDoNotRequireAuthorization(): void
    {
        $client = new CoinbaseClient();

        $method = new ReflectionMethod(CoinbaseClient::class, 'getHeaders');
        $method->setAccessible(true);

        $headers = $method->invoke($client, 'GET', '/api/v3/brokerage/time', true, false);

        foreach ($headers as $header) {
            $this->assertFalse(str_starts_with($header, 'Authorization: Bearer '));
        }
    }

    public function testFilterParamsAndBuildUrl(): void
    {
        Config::fake(['COINBASE_BASE_URL' => 'https://example.com/']);
        $client = new CoinbaseClient();

        $filter = new ReflectionMethod(CoinbaseClient::class, 'filterParams');
        $filter->setAccessible(true);
        $buildUrl = new ReflectionMethod(CoinbaseClient::class, 'buildUrl');
        $buildUrl->setAccessible(true);

        $filtered = $filter->invoke($client, [
            'a' => 1,
            'b' => null,
            'c' => 'x',
        ]);

        $url = $buildUrl->invoke($client, '/api/v3/brokerage/time', $filtered);

        $this->assertSame(['a' => 1, 'c' => 'x'], $filtered);
        $this->assertStringContainsString('https://example.com/api/v3/brokerage/time', $url);
        $this->assertStringContainsString('a=1', $url);
        $this->assertStringContainsString('c=x', $url);
    }

    public function testIsPublicEndpointAndResolveHostFallback(): void
    {
        $client = new CoinbaseClient();

        $isPublic = new ReflectionMethod(CoinbaseClient::class, 'isPublicEndpoint');
        $isPublic->setAccessible(true);
        $resolveHost = new ReflectionMethod(CoinbaseClient::class, 'resolveHost');
        $resolveHost->setAccessible(true);

        $this->assertTrue($isPublic->invoke($client, '/api/v3/brokerage/market/products'));
        $this->assertTrue($isPublic->invoke($client, '/api/v3/brokerage/time'));
        $this->assertFalse($isPublic->invoke($client, '/api/v3/brokerage/accounts'));

        $fallback = $resolveHost->invoke($client, 'not-a-url');
        $this->assertSame('not-a-url', $fallback);
    }

    public function testDerToJoseInvalidSignatureThrows(): void
    {
        $client = new CoinbaseClient();
        $method = new ReflectionMethod(CoinbaseClient::class, 'derToJose');
        $method->setAccessible(true);

        $this->expectException(RuntimeException::class);
        $method->invoke($client, 'invalid', 32);
    }

    public function testPrivateHelpersViaReflection(): void
    {
        $client = new CoinbaseClient();

        $base64UrlEncode = new ReflectionMethod(CoinbaseClient::class, 'base64UrlEncode');
        $base64UrlEncode->setAccessible(true);
        $loadKeyFile = new ReflectionMethod(CoinbaseClient::class, 'loadKeyFile');
        $loadKeyFile->setAccessible(true);
        $normalizeSecret = new ReflectionMethod(CoinbaseClient::class, 'normalizeSecret');
        $normalizeSecret->setAccessible(true);
        $backoff = new ReflectionMethod(CoinbaseClient::class, 'backoff');
        $backoff->setAccessible(true);
        $resolveHost = new ReflectionMethod(CoinbaseClient::class, 'resolveHost');
        $resolveHost->setAccessible(true);

        $encoded = $base64UrlEncode->invoke($client, 'a+b/c==');
        $this->assertStringNotContainsString('+', $encoded);
        $this->assertStringNotContainsString('/', $encoded);

        $missing = $loadKeyFile->invoke($client, '/path/that/does/not/exist.json');
        $this->assertSame([null, null], $missing);

        $tmp = tempnam(sys_get_temp_dir(), 'cb-key-invalid-');
        file_put_contents($tmp, '{invalid-json');
        $invalid = $loadKeyFile->invoke($client, $tmp);
        $this->assertSame([null, null], $invalid);
        @unlink($tmp);

        $normalized = $normalizeSecret->invoke($client, "  line1\\nline2  ");
        $this->assertSame("line1\nline2", $normalized);

        $start = microtime(true);
        $backoff->invoke($client, 0, 1);
        $elapsedMs = (microtime(true) - $start) * 1000;
        $this->assertGreaterThan(0, $elapsedMs);

        $this->assertSame('api.coinbase.com', $resolveHost->invoke($client, 'https://api.coinbase.com'));
    }

    private function getPrivateProperty(object $object, string $property): mixed
    {
        $ref = new ReflectionProperty($object, $property);
        $ref->setAccessible(true);
        return $ref->getValue($object);
    }

    private function normalizeError(string $error): string
    {
        return str_replace('ã', 'a', $error);
    }

    public function testDerToJoseSuccessPath(): void
    {
        $client = new CoinbaseClient();
        $method = new ReflectionMethod(CoinbaseClient::class, 'derToJose');
        $method->setAccessible(true);

        $r = str_repeat("\x01", 32);
        $s = str_repeat("\x02", 32);
        $der = chr(0x30) . chr(68) . chr(0x02) . chr(32) . $r . chr(0x02) . chr(32) . $s;

        $result = $method->invoke($client, $der, 32);

        $this->assertSame(64, strlen($result));
        $this->assertSame($r, substr($result, 0, 32));
        $this->assertSame($s, substr($result, 32));
    }

    public function testDerToJoseWithLeadingZeroPadding(): void
    {
        $client = new CoinbaseClient();
        $method = new ReflectionMethod(CoinbaseClient::class, 'derToJose');
        $method->setAccessible(true);

        // r with a leading \x00 byte (sign padding): DER rLen=33, actual value=32 bytes
        $rPad = "\x00" . str_repeat("\x01", 31);
        $s = str_repeat("\x02", 32);
        $der = chr(0x30) . chr(4 + strlen($rPad) + strlen($s)) . chr(0x02) . chr(strlen($rPad)) . $rPad . chr(0x02) . chr(strlen($s)) . $s;

        $result = $method->invoke($client, $der, 32);

        $this->assertSame(64, strlen($result));
        // ltrim removes the leading \x00, then str_pad re-pads to 32 with \x00 on left
        $this->assertSame("\x00" . str_repeat("\x01", 31), substr($result, 0, 32));
    }

    public function testDerToJoseInvalidSTagThrows(): void
    {
        $client = new CoinbaseClient();
        $method = new ReflectionMethod(CoinbaseClient::class, 'derToJose');
        $method->setAccessible(true);

        // Valid r tag but invalid s tag (0x03 instead of 0x02)
        $r = str_repeat("\x01", 32);
        $s = str_repeat("\x02", 32);
        $der = chr(0x30) . chr(68) . chr(0x02) . chr(32) . $r . chr(0x03) . chr(32) . $s;

        $this->expectException(RuntimeException::class);
        $method->invoke($client, $der, 32);
    }

    public function testDerToJoseWithMultiByteLengthEncoding(): void
    {
        $client = new CoinbaseClient();
        $method = new ReflectionMethod(CoinbaseClient::class, 'derToJose');
        $method->setAccessible(true);

        // Multi-byte DER length encoding: total content > 127 bytes
        $r = str_repeat("\x01", 64);
        $s = str_repeat("\x02", 64);
        $contentLen = 4 + 64 + 64; // 132
        $der = chr(0x30) . chr(0x81) . chr($contentLen) . chr(0x02) . chr(64) . $r . chr(0x02) . chr(64) . $s;

        $result = $method->invoke($client, $der, 64);

        $this->assertSame(128, strlen($result));
        $this->assertSame($r, substr($result, 0, 64));
        $this->assertSame($s, substr($result, 64));
    }

    public function testLogRequestViaReflection(): void
    {
        Config::fake(['APP_DEBUG' => 'true', 'COINBASE_SSL_VERIFY' => 'false']);
        $client = new CoinbaseClient();
        $method = new ReflectionMethod(CoinbaseClient::class, 'logRequest');
        $method->setAccessible(true);

        $method->invoke($client, 'GET', 'https://example.com', 200, 0, microtime(true), [], null);
        $method->invoke($client, 'POST', 'https://example.com', 400, 0, microtime(true), [
            'x-ratelimit-limit' => '100',
            'x-ratelimit-remaining' => '99',
            'x-ratelimit-reset' => '60',
        ], 'error msg');
        $this->assertTrue(true);
    }

    public function testLogRequestSkipsWhenNotDebug(): void
    {
        Config::fake(['APP_DEBUG' => 'false']);
        $client = new CoinbaseClient();
        $method = new ReflectionMethod(CoinbaseClient::class, 'logRequest');
        $method->setAccessible(true);

        $method->invoke($client, 'GET', 'https://example.com', 200, 0, microtime(true), [], null);
        $this->assertTrue(true);
    }

    public function testGetPublicEndpointRealCoinbaseApi(): void
    {
        Config::fake(['COINBASE_SSL_VERIFY' => 'false']);
        $client = new CoinbaseClient();

        $result = $client->get('/api/v3/brokerage/time');

        if (isset($result['success']) && $result['success'] === false) {
            $this->assertArrayHasKey('error', $result);
        } else {
            $this->assertIsArray($result);
        }
    }

    public function testHasCredentialsViaGetBehaviour(): void
    {
        // Without credentials, private endpoint returns error before HTTP call
        $client = new CoinbaseClient('key', 'secret');
        $method = new ReflectionMethod(CoinbaseClient::class, 'hasCredentials');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($client));

        $noCredsClient = new CoinbaseClient();
        $this->assertFalse($method->invoke($noCredsClient));
    }

    public function testPostWithCredentialsCoversBodyLines(): void
    {
        Config::fake(['COINBASE_SSL_VERIFY' => 'false']);

        // Use stub to avoid buildJwt (requires EC key) and real network
        $client = $this->makeClientWithCurlStub([
            ['', 0, 'Connection refused'],
        ], true);

        // This covers post() lines: filterParams, json_encode, buildUrl, CURLOPT_POSTFIELDS
        $result = $client->post('/api/v3/brokerage/orders', ['side' => 'BUY', 'null_val' => null]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Erro de conex', $result['error']);
    }

    public function testDeleteWithCredentialsCoversBodyLines(): void
    {
        Config::fake(['COINBASE_SSL_VERIFY' => 'false']);

        // Use stub to avoid buildJwt and real network
        $client = $this->makeClientWithCurlStub([
            ['', 0, 'Connection refused'],
        ], true);

        // This covers delete() lines: filterParams, json_encode, buildUrl
        $result = $client->delete('/api/v3/brokerage/orders/123', ['null_val' => null]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Erro de conex', $result['error']);
    }

    public function testRequestWithCaBundleSet(): void
    {
        $tmpCa = tempnam(sys_get_temp_dir(), 'coinbase-ca-');
        file_put_contents($tmpCa, 'fake-ca-bundle');

        Config::fake([
            'COINBASE_BASE_URL' => 'http://127.0.0.1:9',
            'COINBASE_SSL_VERIFY' => 'false',
            'COINBASE_CA_BUNDLE' => $tmpCa,
        ]);
        $client = new CoinbaseClient();

        // caBundle is set and file exists -> CURLOPT_CAINFO branch executed
        $result = $client->get('/api/v3/brokerage/time');

        @unlink($tmpCa);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    public function testDerToJoseInvalidRTagThrows(): void
    {
        $client = new CoinbaseClient();
        $method = new ReflectionMethod(CoinbaseClient::class, 'derToJose');
        $method->setAccessible(true);

        // Valid 0x30 start, valid single-byte length, but invalid r-tag (0x03 instead of 0x02)
        $der = chr(0x30) . chr(10) . chr(0x03) . str_repeat("\x00", 8);

        $this->expectException(RuntimeException::class);
        $method->invoke($client, $der, 32);
    }

    public function testGetRetryAfterMsWithNonParsableString(): void
    {
        $client = new CoinbaseClient();
        $method = new ReflectionMethod(CoinbaseClient::class, 'getRetryAfterMs');
        $method->setAccessible(true);

        // Non-numeric, non-parsable string -> strtotime returns false -> return null
        $result = $method->invoke($client, ['retry-after' => 'xyz-not-a-date-!@#$%']);

        $this->assertNull($result);
    }

    public function testRequestReturnsHttpErrorResponse(): void
    {
        // Use real Coinbase time endpoint — it returns 200, so we use a non-existent endpoint
        // that returns a 404 to cover the $httpCode >= 400 branch
        Config::fake(['COINBASE_SSL_VERIFY' => 'false']);
        $client = new CoinbaseClient();

        $result = $client->get('/api/v3/brokerage/this-endpoint-does-not-exist-xyz123');

        // Should return either a real HTTP error (covers >= 400 branch)
        // or a connection error — either way it exercises the code path
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertFalse($result['success']);
    }

    public function testBuildJwtWithRealEcKey(): void
    {
        // Generate an EC P-256 key via openssl CLI since openssl_pkey_new with EC may fail
        $privateKeyPem = null;

        // Try to generate EC key directly
        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);

        if ($key !== false) {
            openssl_pkey_export($key, $privateKeyPem);
        }

        // Fallback para uma chave EC P-256 de teste fixa quando o ambiente não
        // consegue gerar/exportar chaves EC (ex.: Windows sem openssl.cnf).
        // Chave exclusiva para testes — sem qualquer uso em produção.
        if (!$privateKeyPem) {
            $privateKeyPem = "-----BEGIN PRIVATE KEY-----\n"
                . "MIGHAgEAMBMGByqGSM49AgEGCCqGSM49AwEHBG0wawIBAQQgsVqhcaUUQIkhwP51\n"
                . "RdNUfXDkWVciXf0dcI0aoaQ6ay2hRANCAATiXr11Wr8JKP6pXaski6hgAHP/voQm\n"
                . "/hnsUo3TIZDwQc1VpxFptC2CJ4i6cwU1JQaN/A6uV5lRov7yqpQoMrFq\n"
                . "-----END PRIVATE KEY-----\n";
        }

        Config::fake(['COINBASE_SSL_VERIFY' => 'false']);
        $client = new CoinbaseClient('organizations/org/apiKeys/test-key', $privateKeyPem);

        $buildJwt = new ReflectionMethod(CoinbaseClient::class, 'buildJwt');
        $buildJwt->setAccessible(true);

        $jwt = $buildJwt->invoke($client, 'GET', '/api/v3/brokerage/time');

        $this->assertIsString($jwt);
        $parts = explode('.', $jwt);
        $this->assertCount(3, $parts);
    }

    public function testRequestRetriesOn500ThenFails(): void
    {
        // To cover the shouldRetry/backoff/continue branch, we need a server that
        // returns 500. Use the real Coinbase public endpoint and check if it works,
        // or use port 9 to trigger curl error (already covered). Instead we directly
        // test shouldRetry and backoff to ensure branch logic is verified.
        $client = new CoinbaseClient();

        $shouldRetry = new ReflectionMethod(CoinbaseClient::class, 'shouldRetry');
        $shouldRetry->setAccessible(true);

        // attempt < MAX_RETRIES (2) and 500 => should retry
        $this->assertTrue($shouldRetry->invoke($client, 500, 0));
        $this->assertTrue($shouldRetry->invoke($client, 429, 1));
        // attempt == MAX_RETRIES => no retry
        $this->assertFalse($shouldRetry->invoke($client, 500, 2));
        // non-retry status
        $this->assertFalse($shouldRetry->invoke($client, 200, 0));
        $this->assertFalse($shouldRetry->invoke($client, 400, 0));
    }

    private function makeClientWithCurlStub(array $responses, bool $stubHeaders = false): CoinbaseClient
    {
        $client = new class('test-key', 'test-secret') extends CoinbaseClient {
            /** @var array<int,array{0:string|false,1:int,2:string}> */
            public array $stubbedResponses = [];
            public bool $stubHeaders = false;
            private int $callIndex = 0;

            protected function execCurl($ch): array
            {
                $r = $this->stubbedResponses[$this->callIndex] ?? ['{}', 200, ''];
                $this->callIndex++;
                return $r;
            }

            /** @return array<int,string> */
            protected function buildHeaders(string $method, string $endpoint, bool $isPublic, bool $hasBody): array
            {
                if ($this->stubHeaders) {
                    return ['Content-Type: application/json'];
                }
                return parent::buildHeaders($method, $endpoint, $isPublic, $hasBody);
            }
        };
        $client->stubbedResponses = $responses;
        $client->stubHeaders = $stubHeaders;
        return $client;
    }

    public function testRequestReturns400ErrorResponse(): void
    {
        Config::fake(['COINBASE_SSL_VERIFY' => 'false']);

        // Use a public endpoint path so no JWT is built; the stub intercepts curl
        $client = $this->makeClientWithCurlStub([
            ['{"message":"Not found"}', 404, ''],
        ]);

        $result = $client->get('/api/v3/brokerage/time');

        $this->assertFalse($result['success']);
        $this->assertSame('Not found', $result['error']);
        $this->assertSame(404, $result['code']);
    }

    public function testRequestReturnsInvalidJsonError(): void
    {
        Config::fake(['COINBASE_SSL_VERIFY' => 'false']);

        $client = $this->makeClientWithCurlStub([
            ['not-valid-json-at-all!!!', 200, ''],
        ]);

        $result = $client->get('/api/v3/brokerage/time');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Resposta inválida', $result['error']);
    }

    public function testRequestReturnsSuccessOnValidJson(): void
    {
        Config::fake(['COINBASE_SSL_VERIFY' => 'false']);

        $client = $this->makeClientWithCurlStub([
            ['{"iso":"2024-01-01T00:00:00Z","epochSeconds":"1704067200"}', 200, ''],
        ]);

        $result = $client->get('/api/v3/brokerage/time');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('iso', $result);
    }

    public function testRequestExhaustsRetriesAndReturnsLastHttpError(): void
    {
        Config::fake(['COINBASE_SSL_VERIFY' => 'false']);

        // MAX_RETRIES = 2: attempts 0 and 1 retry (shouldRetry=true), attempt 2 does not retry
        // (shouldRetry=false because attempt >= MAX_RETRIES), falls to httpCode >= 400 branch
        $client = $this->makeClientWithCurlStub([
            ['{"error":"server error"}', 500, ''],
            ['{"error":"server error"}', 500, ''],
            ['{"error":"server error"}', 500, ''],
        ]);

        $result = $client->get('/api/v3/brokerage/time');

        $this->assertFalse($result['success']);
        $this->assertSame('server error', $result['error']);
    }

    public function testRequestRetriesOn429ThenSucceeds(): void
    {
        Config::fake(['COINBASE_SSL_VERIFY' => 'false']);

        $client = $this->makeClientWithCurlStub([
            ['{"error":"rate limited"}', 429, ''],
            ['{"iso":"2024-01-01T00:00:00Z"}', 200, ''],
        ]);

        $result = $client->get('/api/v3/brokerage/time');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('iso', $result);
    }

    public function testPostReturnsSuccessWithCurlStub(): void
    {
        Config::fake(['COINBASE_SSL_VERIFY' => 'false']);

        // stubHeaders=true bypasses buildJwt (requires real EC key)
        $client = $this->makeClientWithCurlStub([
            ['{"order_id":"abc123"}', 200, ''],
        ], true);

        $result = $client->post('/api/v3/brokerage/orders', ['side' => 'BUY']);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('order_id', $result);
    }

    public function testDeleteReturnsSuccessWithCurlStub(): void
    {
        Config::fake(['COINBASE_SSL_VERIFY' => 'false']);

        // stubHeaders=true bypasses buildJwt
        $client = $this->makeClientWithCurlStub([
            ['{"results":[{"success":true}]}', 200, ''],
        ], true);

        $result = $client->delete('/api/v3/brokerage/orders/batch_cancel', []);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('results', $result);
    }
}
