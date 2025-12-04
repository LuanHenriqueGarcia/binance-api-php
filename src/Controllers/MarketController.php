<?php

namespace BinanceAPI\Controllers;
use BinanceAPI\BinanceClient;
use BinanceAPI\Validation;

class MarketController
{
    /**
     * Obtém o preço atual de um símbolo
     * GET /api/market/ticker?symbol=BTCUSDT
     *
     * @param array<string,mixed> $params Parâmetros da requisição
     * @return array<string,mixed> Resposta da API
     */
    public function ticker(array $params): array
    {
        try {
            if ($error = Validation::requireFields($params, ['symbol'])) {
                return ['success' => false, 'error' => $error];
            }

            $client = new BinanceClient();
            $response = $client->get('/api/v3/ticker/24hr', [
                'symbol' => $params['symbol']
            ]);

            if (!isset($response['success']) || $response['success'] !== false) {
                $response = [
                    'symbol' => $response['symbol'] ?? $params['symbol'],
                    'price' => $response['lastPrice'] ?? $response['price'] ?? '0',
                    'priceChangePercent' => $response['priceChangePercent'] ?? null,
                    'high' => $response['highPrice'] ?? null,
                    'low' => $response['lowPrice'] ?? null,
                    'volume' => $response['volume'] ?? null,
                ];
            }

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao obter ticker: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtém o livro de pedidos (depth) para um símbolo
     * GET /api/market/order-book?symbol=BTCUSDT&limit=100
     *
     * @param array<string,mixed> $params Parâmetros da requisição
     * @return array<string,mixed> Resposta da API
     */
    public function orderBook(array $params): array
    {
        try {
            if ($error = Validation::requireFields($params, ['symbol'])) {
                return ['success' => false, 'error' => $error];
            }

            $client = new BinanceClient();
            $limit = $params['limit'] ?? 100;

            $response = $client->get('/api/v3/depth', [
                'symbol' => $params['symbol'],
                'limit' => $limit
            ]);

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao obter livro de pedidos: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Lista os últimos trades executados para um símbolo
     * GET /api/market/trades?symbol=BTCUSDT&limit=500
     *
     * @param array<string,mixed> $params Parâmetros da requisição
     * @return array<string,mixed> Resposta da API
     */
    public function trades(array $params): array
    {
        try {
            if ($error = Validation::requireFields($params, ['symbol'])) {
                return ['success' => false, 'error' => $error];
            }

            $client = new BinanceClient();
            $limit = $params['limit'] ?? 500;

            $response = $client->get('/api/v3/trades', [
                'symbol' => $params['symbol'],
                'limit' => $limit
            ]);

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao obter trades: ' . $e->getMessage()
            ];
        }
    }

    /**
     * @param array<string,mixed> $response
     * @return array<string,mixed>
     */
    private function formatResponse(array $response): array
    {
        if (isset($response['success']) && $response['success'] === false) {
            return $response;
        }

        return [
            'success' => true,
            'data' => $response
        ];
    }
}
