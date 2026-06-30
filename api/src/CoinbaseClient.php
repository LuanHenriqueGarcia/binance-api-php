<?php

namespace TradersApi;

use TradersApi\Contracts\ClientInterface;

class CoinbaseClient implements ClientInterface
{
    private const DEFAULT_BASE_URL = 'https://api.coinbase.com';
    private const TIMEOUT = 10;
    private const MAX_RETRIES = 2;
    private const RETRY_DELAY_MS = 200;
    private const MAX_BACKOFF_MS = 2000;
    private const JWT_TTL = 120;

    /** @var array<int,string> */
    private const PUBLIC_PREFIXES = [
        '/api/v3/brokerage/market/',
        '/api/v3/brokerage/time',
    ];

    private ?string $apiKey = null;
    private ?string $secretKey = null;
    private string $baseUrl;
    private string $baseHost;
    private bool $verifySsl;
    private ?string $caBundle = null;
    /** @var array<string,string> */
    private array $responseHeaders = [];

    /**
     * @param string|null $apiKey Coinbase API Key (name)
     * @param string|null $secretKey Coinbase API Secret (private key PEM)
     * @param string|null $keyFile Caminho opcional para JSON de credenciais
     */
    public function __construct(?string $apiKey = null, ?string $secretKey = null, ?string $keyFile = null)
    {
        $apiKey = $apiKey ?? Config::getCoinbaseApiKey();
        $secretKey = $secretKey ?? Config::getCoinbaseApiSecret();
        $keyFile = $keyFile ?? Config::getCoinbaseKeyFile();

        if ((!$apiKey || !$secretKey) && $keyFile) {
            [$fileKey, $fileSecret] = $this->loadKeyFile($keyFile);
            $apiKey = $apiKey ?? $fileKey;
            $secretKey = $secretKey ?? $fileSecret;
        }

        $this->apiKey = $apiKey ?: null;
        $this->secretKey = $secretKey ? $this->normalizeSecret($secretKey) : null;
        $this->baseUrl = rtrim(Config::getCoinbaseBaseUrl() ?: self::DEFAULT_BASE_URL, '/');
        $this->baseHost = $this->resolveHost($this->baseUrl);
        $this->verifySsl = Config::shouldVerifyCoinbaseSsl();
        $this->caBundle = Config::getCoinbaseCaBundle();
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function get(string $endpoint, array $params = []): array
    {
        $isPublic = $this->isPublicEndpoint($endpoint);
        if (!$isPublic && !$this->hasCredentials()) {
            return [
                'success' => false,
                'error' => 'API Key e Secret Key são obrigatórios'
            ];
        }

        $url = $this->buildUrl($endpoint, $this->filterParams($params));

        return $this->request('GET', $url, null, $endpoint, $isPublic);
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function post(string $endpoint, array $params = []): array
    {
        if (!$this->hasCredentials()) {
            return [
                'success' => false,
                'error' => 'API Key e Secret Key são obrigatórios'
            ];
        }

        $payload = $this->filterParams($params);
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $url = $this->buildUrl($endpoint, []);

        return $this->request('POST', $url, $body, $endpoint, false);
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function delete(string $endpoint, array $params = []): array
    {
        if (!$this->hasCredentials()) {
            return [
                'success' => false,
                'error' => 'API Key e Secret Key são obrigatórios'
            ];
        }

        $payload = $this->filterParams($params);
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $url = $this->buildUrl($endpoint, []);

        return $this->request('DELETE', $url, $body, $endpoint, false);
    }

    /**
     * @return array<string,mixed>
     */
    private function request(string $method, string $url, ?string $body, string $endpoint, bool $isPublic): array
    {
        $start = microtime(true);
        $attempt = 0;
        while (true) {
            $ch = curl_init();

            $this->responseHeaders = [];
            $options = [
                CURLOPT_URL => $url,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => self::TIMEOUT,
                CURLOPT_HTTPHEADER => $this->getHeaders($method, $endpoint, $isPublic, $body !== null),
                CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
                CURLOPT_SSL_VERIFYHOST => $this->verifySsl ? 2 : 0,
                CURLOPT_HEADERFUNCTION => [$this, 'captureHeader'],
            ];

            if ($this->caBundle && file_exists($this->caBundle)) {
                $options[CURLOPT_CAINFO] = $this->caBundle;
            }

            if ($body !== null) {
                $options[CURLOPT_POSTFIELDS] = $body;
            }

            curl_setopt_array($ch, $options);

            [$response, $httpCode, $error] = $this->execCurl($ch);
            $responseHeaders = $this->responseHeaders;

            if ($error || $response === false) {
                $this->logRequest($method, $url, $httpCode, $attempt, $start, $responseHeaders, $error ?: 'Resposta vazia');
                return [
                    'success' => false,
                    'error' => 'Erro de conexão: ' . ($error ?: 'Resposta vazia')
                ];
            }

            $retryAfterMs = $this->getRetryAfterMs($responseHeaders);
            if ($this->shouldRetry($httpCode, $attempt)) {
                $this->backoff($attempt, $retryAfterMs);
                $attempt++;
                continue;
            }

            if ($httpCode >= 400) {
                $decoded = json_decode($response, true);
                $message = $this->extractErrorMessage($decoded, $httpCode, $response);
                $this->logRequest($method, $url, $httpCode, $attempt, $start, $responseHeaders, $message);
                return [
                    'success' => false,
                    'error' => $message,
                    'code' => $httpCode
                ];
            }

            $decoded = json_decode($response, true);
            if ($decoded === null && $response !== '') {
                $this->logRequest($method, $url, $httpCode, $attempt, $start, $responseHeaders, 'Resposta inválida');
                return [
                    'success' => false,
                    'error' => 'Resposta inválida: ' . $response
                ];
            }

            $this->logRequest($method, $url, $httpCode, $attempt, $start, $responseHeaders, null);
            return $decoded ?? [];
        }
    }

    /**
     * Executa o curl e retorna [response|false, httpCode, errorMsg]
     *
     * @param \CurlHandle $ch
     * @return array{0:string|false,1:int,2:string}
     */
    protected function execCurl($ch): array
    {
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        return [$response, $httpCode, $error];
    }

    /**
     * Callback de CURLOPT_HEADERFUNCTION: captura os cabeçalhos da resposta.
     * Método nomeado (em vez de closure inline) para permitir teste
     * determinístico do parsing, sem depender de uma chamada de rede real.
     *
     * @param mixed $ch handle do cURL (não utilizado; exigido pela assinatura)
     */
    public function captureHeader($ch, string $header): int
    {
        $parts = explode(':', $header, 2);
        if (count($parts) === 2) {
            $this->responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
        }
        return strlen($header);
    }

    /**
     * @return array<int,string>
     */
    private function getHeaders(string $method, string $endpoint, bool $isPublic, bool $hasBody): array
    {
        return $this->buildHeaders($method, $endpoint, $isPublic, $hasBody);
    }

    /**
     * @return array<int,string>
     */
    protected function buildHeaders(string $method, string $endpoint, bool $isPublic, bool $hasBody): array
    {
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'User-Agent: binance-api-php'
        ];

        if (!$isPublic && $this->hasCredentials()) {
            $headers[] = 'Authorization: Bearer ' . $this->buildJwt($method, $endpoint);
        }

        return $headers;
    }

    private function hasCredentials(): bool
    {
        return (bool) ($this->apiKey && $this->secretKey);
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    private function filterParams(array $params): array
    {
        return array_filter($params, static function ($value) {
            return $value !== null;
        });
    }

    /**
     * @param array<string, mixed> $params
     */
    private function buildUrl(string $endpoint, array $params): string
    {
        $url = $this->baseUrl . $endpoint;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        return $url;
    }

    private function isPublicEndpoint(string $endpoint): bool
    {
        foreach (self::PUBLIC_PREFIXES as $prefix) {
            if (str_starts_with($endpoint, $prefix)) {
                return true;
            }
        }
        return false;
    }

    private function buildJwt(string $method, string $endpoint): string
    {
        if (!$this->apiKey || !$this->secretKey) {
            throw new \RuntimeException('Credenciais Coinbase ausentes para gerar JWT');
        }

        $now = time();
        $payload = [
            'sub' => $this->apiKey,
            'iss' => 'cdp',
            'nbf' => $now,
            'exp' => $now + self::JWT_TTL,
            'uri' => sprintf('%s %s%s', strtoupper($method), $this->baseHost, $endpoint),
        ];

        $header = [
            'alg' => 'ES256',
            'typ' => 'JWT',
            'kid' => $this->apiKey,
            'nonce' => bin2hex(random_bytes(16)),
        ];

        $encodedHeader = $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $signingInput = $encodedHeader . '.' . $encodedPayload;

        $privateKey = openssl_pkey_get_private($this->secretKey);
        if ($privateKey === false) {
            throw new \RuntimeException('Chave privada inválida para assinar JWT');
        }

        $signature = '';
        $signed = $this->signData($signingInput, $signature, $privateKey);
        if (!$signed) {
            throw new \RuntimeException('Falha ao assinar JWT');
        }

        $joseSignature = $this->derToJose($signature, 32);
        return $signingInput . '.' . $this->base64UrlEncode($joseSignature);
    }

    /**
     * Assina os dados com a chave privada (ES256).
     * Isolado em método próprio para permitir simular falha em testes.
     *
     * @param \OpenSSLAsymmetricKey $privateKey
     */
    protected function signData(string $data, string &$signature, $privateKey): bool
    {
        return openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function derToJose(string $derSignature, int $partLength): string
    {
        $offset = 0;
        if (ord($derSignature[$offset]) !== 0x30) {
            throw new \RuntimeException('Assinatura DER inválida');
        }
        $offset++;
        $length = ord($derSignature[$offset++]);
        if ($length & 0x80) {
            $lengthBytes = $length & 0x7f;
            $length = 0;
            for ($i = 0; $i < $lengthBytes; $i++) {
                $length = ($length << 8) | ord($derSignature[$offset++]);
            }
        }

        if (ord($derSignature[$offset]) !== 0x02) {
            throw new \RuntimeException('Assinatura DER inválida');
        }
        $offset++;
        $rLength = ord($derSignature[$offset++]);
        $r = substr($derSignature, $offset, $rLength);
        $offset += $rLength;

        if (ord($derSignature[$offset]) !== 0x02) {
            throw new \RuntimeException('Assinatura DER inválida');
        }
        $offset++;
        $sLength = ord($derSignature[$offset++]);
        $s = substr($derSignature, $offset, $sLength);

        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");
        $r = str_pad($r, $partLength, "\x00", STR_PAD_LEFT);
        $s = str_pad($s, $partLength, "\x00", STR_PAD_LEFT);

        return $r . $s;
    }

    /**
     * @param array<string,string> $headers
     */
    private function getRetryAfterMs(array $headers): ?int
    {
        if (!isset($headers['retry-after'])) {
            return null;
        }

        $value = $headers['retry-after'];
        if (is_numeric($value)) {
            return (int) $value * 1000;
        }

        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            $delta = ($timestamp - time()) * 1000;
            return $delta > 0 ? $delta : null;
        }

        return null;
    }

    private function shouldRetry(int $httpCode, int $attempt): bool
    {
        if ($attempt >= self::MAX_RETRIES) {
            return false;
        }

        if ($httpCode === 429) {
            return true;
        }

        return $httpCode >= 500 && $httpCode < 600;
    }

    private function backoff(int $attempt, ?int $retryAfterMs = null): void
    {
        $base = $retryAfterMs ?? (self::RETRY_DELAY_MS * (2 ** $attempt));
        $base = min($base, self::MAX_BACKOFF_MS);
        $delayMs = (int) ($base * (random_int(50, 150) / 100));
        usleep($delayMs * 1000);
    }

    /**
     * @param array<string,mixed>|null $decoded
     */
    private function extractErrorMessage(?array $decoded, int $httpCode, string $raw): string
    {
        if (is_array($decoded)) {
            if (isset($decoded['message']) && is_string($decoded['message'])) {
                return $decoded['message'];
            }
            if (isset($decoded['error']) && is_string($decoded['error'])) {
                return $decoded['error'];
            }
            if (isset($decoded['errors'][0]['message']) && is_string($decoded['errors'][0]['message'])) {
                return $decoded['errors'][0]['message'];
            }
        }

        return 'Erro HTTP ' . $httpCode;
    }

    /**
     * @param array<string,string> $headers
     */
    private function logRequest(string $method, string $url, int $status, int $attempt, float $start, array $headers, ?string $error): void
    {
        if (!Config::isDebug() && !Config::get('APP_LOG_FILE')) {
            return;
        }

        $durationMs = (int) ((microtime(true) - $start) * 1000);
        $rate = array_filter([
            'limit' => $headers['x-ratelimit-limit'] ?? null,
            'remaining' => $headers['x-ratelimit-remaining'] ?? null,
            'reset' => $headers['x-ratelimit-reset'] ?? null,
        ]);

        $message = [
            'method' => $method,
            'url' => $url,
            'status' => $status,
            'attempt' => $attempt,
            'duration_ms' => $durationMs,
            'rate' => $rate,
            'error' => $error,
            'request_id' => Config::getRequestId(),
            'user_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ];

        Logger::info($message);
    }

    /**
     * @return array{0:?string,1:?string}
     */
    private function loadKeyFile(string $path): array
    {
        if (!is_file($path)) {
            return [null, null];
        }

        $contents = $this->readFileContents($path);
        if ($contents === false) {
            return [null, null];
        }

        $decoded = json_decode($contents, true);
        if (!is_array($decoded)) {
            return [null, null];
        }

        return [
            isset($decoded['name']) ? (string) $decoded['name'] : null,
            isset($decoded['privateKey']) ? (string) $decoded['privateKey'] : null,
        ];
    }

    /**
     * Lê o conteúdo de um arquivo.
     * Isolado em método próprio para permitir simular falha de leitura em testes.
     *
     * @return string|false
     */
    protected function readFileContents(string $path)
    {
        return file_get_contents($path);
    }

    private function normalizeSecret(string $secret): string
    {
        $secret = str_replace(['\\r', '\\n'], ["\r", "\n"], $secret);
        return trim($secret);
    }

    private function resolveHost(string $baseUrl): string
    {
        $host = parse_url($baseUrl, PHP_URL_HOST);
        if ($host) {
            $port = parse_url($baseUrl, PHP_URL_PORT);
            return $port ? $host . ':' . $port : $host;
        }

        return preg_replace('#^https?://#', '', $baseUrl) ?: 'api.coinbase.com';
    }
}
