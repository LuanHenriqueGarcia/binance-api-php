<?php

namespace BinanceAPI;

use BinanceAPI\Config;
use BinanceAPI\Contracts\ClientInterface;

class BinanceClient implements ClientInterface
{
    private const BASE_URL = 'https://api.binance.com';
    private const TIMEOUT = 10;
    private const MAX_RETRIES = 2;
    private const RETRY_DELAY_MS = 200;
    private const MAX_BACKOFF_MS = 2000;

    private ?string $apiKey = null;
    private ?string $secretKey = null;
    private string $baseUrl;
    private bool $verifySsl;
    private ?string $caBundle = null;

    /**
     * Construtor
     *
     * @param string|null $apiKey Chave de API Binance
     * @param string|null $secretKey Chave secreta Binance
     */
    public function __construct(?string $apiKey = null, ?string $secretKey = null)
    {
        $this->apiKey = $apiKey;
        $this->secretKey = $secretKey;
        $this->baseUrl = Config::getBinanceBaseUrl() ?: self::BASE_URL;
        $this->verifySsl = Config::shouldVerifySsl();
        $this->caBundle = Config::getCaBundle();
    }

    /**
     * Requisição GET pública ou autenticada
     *
     * @param string $endpoint Endpoint da API (ex: /api/v3/ping)
     * @param array<string,mixed> $params Parâmetros da requisição
     * @return array<string,mixed> Resposta decodificada
     */
    public function get(string $endpoint, array $params = []): array
    {
        // Se tiver API Key, é uma requisição autenticada
        if ($this->apiKey && $this->secretKey) {
            $params['timestamp'] = (int)(microtime(true) * 1000);
            $queryString = http_build_query($params);
            $signature = hash_hmac('sha256', $queryString, $this->secretKey);
            $url = $this->baseUrl . $endpoint . '?' . $queryString . '&signature=' . $signature;
        } else {
            // Requisição pública
            $url = $this->baseUrl . $endpoint;
            if (!empty($params)) {
                $url .= '?' . http_build_query($params);
            }
        }

        return $this->request('GET', $url);
    }

    /**
     * Requisição POST autenticada
     *
     * @param string $endpoint Endpoint da API
     * @param array<string,mixed> $params Parâmetros da requisição
     * @return array<string,mixed> Resposta decodificada
     */
    public function post(string $endpoint, array $params = []): array
    {
        if (!$this->apiKey || !$this->secretKey) {
            return [
                'success' => false,
                'error' => 'API Key e Secret Key são obrigatórios'
            ];
        }

        $params['recvWindow'] = Config::getRecvWindow();
        $params['timestamp'] = (int)(microtime(true) * 1000);
        $queryString = http_build_query($params);
        $signature = hash_hmac('sha256', $queryString, $this->secretKey);

        $payload = $queryString . '&signature=' . $signature;
        $url = $this->baseUrl . $endpoint;

        return $this->request('POST', $url, $payload);
    }

    /**
     * Requisição DELETE autenticada
     *
     * @param string $endpoint Endpoint da API
     * @param array<string,mixed> $params Parâmetros da requisição
     * @return array<string,mixed> Resposta decodificada
     */
    public function delete(string $endpoint, array $params = []): array
    {
        if (!$this->apiKey || !$this->secretKey) {
            return [
                'success' => false,
                'error' => 'API Key e Secret Key são obrigatórios'
            ];
        }

        $params['recvWindow'] = Config::getRecvWindow();
        $params['timestamp'] = (int)(microtime(true) * 1000);
        $queryString = http_build_query($params);
        $signature = hash_hmac('sha256', $queryString, $this->secretKey);

        $payload = $queryString . '&signature=' . $signature;
        $url = $this->baseUrl . $endpoint;

        return $this->request('DELETE', $url, $payload);
    }

    /**
     * Executar requisição HTTP com cURL
     *
     * @param string $method Método HTTP (GET, POST, DELETE, etc)
     * @param string $url URL completa
     * @param string|null $body Payload form-urlencoded (para assinadas)
     * @return array<string,mixed> Resposta decodificada ou erro
     */
    private function request(string $method, string $url, ?string $body = null): array
    {
        $start = microtime(true);
        $attempt = 0;
        while (true) {
            $ch = curl_init();

            /** @var array<string,string> $responseHeaders */
            $responseHeaders = [];
            $options = [
                CURLOPT_URL => $url,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => self::TIMEOUT,
                CURLOPT_HTTPHEADER => $this->getHeaders($body !== null),
                CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
                CURLOPT_SSL_VERIFYHOST => $this->verifySsl ? 2 : 0,
                CURLOPT_HEADERFUNCTION => function ($ch, $header) use (&$responseHeaders) {
                    $len = strlen($header);
                    $parts = explode(':', $header, 2);
                    if (count($parts) === 2) {
                        $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                    }
                    return $len;
                },
            ];

            if ($this->caBundle && file_exists($this->caBundle)) {
                $options[CURLOPT_CAINFO] = $this->caBundle;
            }

            if ($body !== null) {
                $options[CURLOPT_POSTFIELDS] = $body;
            }

            curl_setopt_array($ch, $options);

            [$response, $httpCode, $error] = $this->execCurl($ch);

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
                $this->logRequest($method, $url, $httpCode, $attempt, $start, $responseHeaders, $decoded['msg'] ?? 'Erro HTTP ' . $httpCode);
                return [
                    'success' => false,
                    'error' => $decoded['msg'] ?? 'Erro HTTP ' . $httpCode,
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
     * Executa o cURL e retorna [response|false, httpCode, errorMsg].
     * Isolado em método próprio para permitir simulação em testes.
     *
     * @param \CurlHandle $ch
     * @return array{0:string|false,1:int,2:string}
     */
    protected function execCurl($ch): array
    {
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        return [$response, $httpCode, $error];
    }

    /**
     * Obter headers padrão
     *
     * @return array<int,string> Headers HTTP
     */
    private function getHeaders(bool $hasBody = false): array
    {
        $headers = [
            'Accept: application/json',
        ];

        if ($hasBody) {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }

        if ($this->apiKey) {
            $headers[] = 'X-MBX-APIKEY: ' . $this->apiKey;
        }

        return $headers;
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
        // jitter 50%-150%
        $delayMs = (int) ($base * (random_int(50, 150) / 100));
        usleep($delayMs * 1000);
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
            return (int)$value * 1000;
        }

        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            $delta = ($timestamp - time()) * 1000;
            return $delta > 0 ? $delta : null;
        }

        return null;
    }

    /**
     * @param array<string,string> $headers
     */
    private function logRequest(string $method, string $url, int $status, int $attempt, float $start, array $headers, ?string $error): void
    {
        if (!Config::isDebug() && !Config::get('APP_LOG_FILE')) {
            return;
        }

        $durationMs = (int)((microtime(true) - $start) * 1000);
        $rate = array_filter([
            'weight' => $headers['x-mbx-used-weight-1m'] ?? null,
            'orders' => $headers['x-mbx-order-count-1m'] ?? null,
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
}
