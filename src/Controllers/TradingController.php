<?php

namespace BinanceAPI\Controllers;

use BinanceAPI\BinanceClient;

class TradingController
{
    /**
     * Cria uma nova ordem
     * POST /api/trading/create-order
     * Body: {
     *   "api_key": "xxx",
     *   "secret_key": "yyy",
     *   "symbol": "BTCUSDT",
     *   "side": "BUY",
     *   "type": "LIMIT",
     *   "quantity": "1.0",
     *   "price": "42000.00"
     * }
     *
     * @param array $params Parâmetros da requisição
     * @return array Resposta da API
     */
    public function createOrder(array $params): array
    {
        try {
            if (empty($params['api_key']) || empty($params['secret_key'])) {
                return [
                    'success' => false,
                    'error' => 'Parâmetros "api_key" e "secret_key" são obrigatórios'
                ];
            }

            $required = ['symbol', 'side', 'type', 'quantity'];
            foreach ($required as $field) {
                if (empty($params[$field])) {
                    return [
                        'success' => false,
                        'error' => "Parâmetro \"$field\" é obrigatório"
                    ];
                }
            }

            $client = new BinanceClient($params['api_key'], $params['secret_key']);

            $orderParams = [
                'symbol' => $params['symbol'],
                'side' => $params['side'],
                'type' => $params['type'],
                'quantity' => $params['quantity']
            ];

            if (!empty($params['price'])) {
                $orderParams['price'] = $params['price'];
            }

            $response = $client->post('/api/v3/order', $orderParams);

            return [
                'success' => true,
                'data' => $response
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao criar ordem: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Cancela uma ordem existente
     * DELETE /api/trading/cancel-order
     * Body: {
     *   "api_key": "xxx",
     *   "secret_key": "yyy",
     *   "symbol": "BTCUSDT",
     *   "orderId": "12345678"
     * }
     *
     * @param array $params Parâmetros da requisição
     * @return array Resposta da API
     */
    public function cancelOrder(array $params): array
    {
        try {
            if (empty($params['api_key']) || empty($params['secret_key'])) {
                return [
                    'success' => false,
                    'error' => 'Parâmetros "api_key" e "secret_key" são obrigatórios'
                ];
            }

            if (empty($params['symbol']) || empty($params['orderId'])) {
                return [
                    'success' => false,
                    'error' => 'Parâmetros "symbol" e "orderId" são obrigatórios'
                ];
            }

            $client = new BinanceClient($params['api_key'], $params['secret_key']);

            $response = $client->delete('/api/v3/order', [
                'symbol' => $params['symbol'],
                'orderId' => $params['orderId']
            ]);

            return [
                'success' => true,
                'data' => $response
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao cancelar ordem: ' . $e->getMessage()
            ];
        }
    }
}