<?php

namespace BinanceAPI\Controllers;
use BinanceAPI\BinanceClient;

class MarketController
{
    /**
     * Obtém o preço atual de um símbolo
     * GET /api/market/ticker?symbol=BTCUSDT
     *
     * @param array $params Parâmetros da requisição
     * @return array Resposta da API
     */
    public function ticker(array $params): array
    {
        try {
            if (empty($params['symbol'])) {
                return [
                    'success' => false,
                    'error' => 'Parâmetro "symbol" é obrigatório'
                ];
            }

            $client = new BinanceClient();
            $response = $client->get('/api/v3/ticker/price', [
                'symbol' => $params['symbol']
            ]);

            return [
                'success' => true,
                'data' => $response
            ];
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
     * @param array $params Parâmetros da requisição
     * @return array Resposta da API
     */
    public function orderBook(array $params): array
    {
        try {
            if (empty($params['symbol'])) {
                return [
                    'success' => false,
                    'error' => 'Parâmetro "symbol" é obrigatório'
                ];
            }

            $client = new BinanceClient();
            $limit = $params['limit'] ?? 100;

            $response = $client->get('/api/v3/depth', [
                'symbol' => $params['symbol'],
                'limit' => $limit
            ]);

            return [
                'success' => true,
                'data' => $response
            ];
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
     * @param array $params Parâmetros da requisição
     * @return array Resposta da API
     */
    public function trades(array $params): array
    {
        try {
            if (empty($params['symbol'])) {
                return [
                    'success' => false,
                    'error' => 'Parâmetro "symbol" é obrigatório'
                ];
            }

            $client = new BinanceClient();
            $limit = $params['limit'] ?? 500;

            $response = $client->get('/api/v3/trades', [
                'symbol' => $params['symbol'],
                'limit' => $limit
            ]);

            return [
                'success' => true,
                'data' => $response
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao obter trades: ' . $e->getMessage()
            ];
        }
    }
}