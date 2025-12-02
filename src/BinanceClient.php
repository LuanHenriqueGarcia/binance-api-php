<?php

namespace BinanceAPI;

class BinanceClient
{
    private const BASE_URL = 'https://api.binance.com';
    private const TIMEOUT = 10;

    private ?string $apiKey = null;
    private ?string $secretKey = null;

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
    }

    /**
     * Requisição GET pública ou autenticada
     *
     * @param string $endpoint Endpoint da API (ex: /api/v3/ping)
     * @param array $params Parâmetros da requisição
     * @return array Resposta decodificada
     */
    public function get(string $endpoint, array $params = []): array
    {
        // Se tiver API Key, é uma requisição autenticada
        if ($this->apiKey && $this->secretKey) {
            $params['timestamp'] = (int)(microtime(true) * 1000);
            $queryString = http_build_query($params);
            $signature = hash_hmac('sha256', $queryString, $this->secretKey);
            $url = self::BASE_URL . $endpoint . '?' . $queryString . '&signature=' . $signature;
        } else {
            // Requisição pública
            $url = self::BASE_URL . $endpoint;
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
     * @param array $params Parâmetros da requisição
     * @return array Resposta decodificada
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

        $url = self::BASE_URL . $endpoint . '?' . $queryString . '&signature=' . $signature;

        return $this->request('POST', $url);
    }

    /**
     * Requisição DELETE autenticada
     *
     * @param string $endpoint Endpoint da API
     * @param array $params Parâmetros da requisição
     * @return array Resposta decodificada
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

        $url = self::BASE_URL . $endpoint . '?' . $queryString . '&signature=' . $signature;

        return $this->request('DELETE', $url);
    }

    /**
     * Executar requisição HTTP com cURL
     *
     * @param string $method Método HTTP (GET, POST, DELETE, etc)
     * @param string $url URL completa
     * @return array Resposta decodificada ou erro
     */
    private function request(string $method, string $url): array
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_HTTPHEADER => $this->getHeaders(),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'error' => 'Erro de conexão: ' . $error
            ];
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

    /**
     * Obter headers padrão
     *
     * @return array Headers HTTP
     */
    private function getHeaders(): array
    {
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if ($this->apiKey) {
            $headers[] = 'X-MBX-APIKEY: ' . $this->apiKey;
        }

        return $headers;
    }
}