<?php

namespace BinanceAPI;

use BinanceAPI\Config;

class BinanceClient
{
    private const BASE_URL = 'https://api.binance.com';
    private const TIMEOUT = 10;
    private const MAX_RETRIES = 2;
    private const RETRY_DELAY_MS = 200;

    private ?string $apiKey = null;
    private ?string $secretKey = null;
    private string $baseUrl;
    private bool $verifySsl;

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
        $this->verifySsl = Config::get('BINANCE_SSL_VERIFY', 'true') === 'true';
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
        for ($attempt = 0; $attempt <= self::MAX_RETRIES; $attempt++) {
            $ch = curl_init();

            $options = [
                CURLOPT_URL => $url,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => self::TIMEOUT,
                CURLOPT_HTTPHEADER => $this->getHeaders($body !== null),
                CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
                CURLOPT_SSL_VERIFYHOST => $this->verifySsl ? 2 : 0,
            ];

            if ($body !== null) {
                $options[CURLOPT_POSTFIELDS] = $body;
            }

            curl_setopt_array($ch, $options);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);

            curl_close($ch);

            if ($error || $response === false) {
                return [
                    'success' => false,
                    'error' => 'Erro de conexão: ' . ($error ?: 'Resposta vazia')
                ];
            }

            if ($this->shouldRetry($httpCode, $attempt)) {
                $this->backoff($attempt);
                continue;
            }

            if ($httpCode >= 400) {
                $decoded = json_decode($response, true);
                return [
                    'success' => false,
                    'error' => $decoded['msg'] ?? 'Erro HTTP ' . $httpCode,
                    'code' => $httpCode
                ];
            }

            $decoded = json_decode($response, true);

            if ($decoded === null && $response !== '') {
                return [
                    'success' => false,
                    'error' => 'Resposta inválida: ' . $response
                ];
            }

            return $decoded ?? [];
        }

        return [
            'success' => false,
            'error' => 'Erro desconhecido ao processar requisição'
        ];
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

    private function backoff(int $attempt): void
    {
        $delayMs = self::RETRY_DELAY_MS * ($attempt + 1);
        usleep($delayMs * 1000);
    }
}
