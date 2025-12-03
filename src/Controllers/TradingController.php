<?php

namespace BinanceAPI\Controllers;

use BinanceAPI\BinanceClient;
use BinanceAPI\Validation;

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
     * @param array<string,mixed> $params Parâmetros da requisição
     * @return array<string,mixed> Resposta da API
     */
    public function createOrder(array $params): array
    {
        try {
            if ($error = Validation::requireFields($params, ['api_key', 'secret_key'])) {
                return ['success' => false, 'error' => $error];
            }

            if ($error = Validation::requireFields($params, ['symbol', 'side', 'type'])) {
                return ['success' => false, 'error' => $error];
            }

            $side = strtoupper($params['side']);
            $type = strtoupper($params['type']);
            $symbol = strtoupper($params['symbol']);

            $allowedTypes = [
                'LIMIT',
                'MARKET',
                'STOP_LOSS',
                'STOP_LOSS_LIMIT',
                'TAKE_PROFIT',
                'TAKE_PROFIT_LIMIT',
                'LIMIT_MAKER'
            ];

            if (!in_array($type, $allowedTypes, true)) {
                return [
                    'success' => false,
                    'error' => 'Parâmetro "type" inválido'
                ];
            }

            $orderParams = [
                'symbol' => $symbol,
                'side' => $side,
                'type' => $type,
            ];

            // Quantidade / cotação
            $hasQuantity = isset($params['quantity']) && is_numeric($params['quantity']) && (float)$params['quantity'] > 0;
            $hasQuoteQty = isset($params['quoteOrderQty']) && is_numeric($params['quoteOrderQty']) && (float)$params['quoteOrderQty'] > 0;

            if ($type === 'MARKET') {
                if ($hasQuantity) {
                    $orderParams['quantity'] = $params['quantity'];
                } elseif ($hasQuoteQty) {
                    $orderParams['quoteOrderQty'] = $params['quoteOrderQty'];
                } else {
                    return [
                        'success' => false,
                        'error' => 'Informe "quantity" ou "quoteOrderQty" para ordens MARKET'
                    ];
                }
            } else {
                if (!$hasQuantity) {
                    return [
                        'success' => false,
                        'error' => 'Parâmetro "quantity" é obrigatório'
                    ];
                }
                $orderParams['quantity'] = $params['quantity'];
            }

            // Regras de preço e timeInForce para LIMIT
            $requiresPrice = in_array($type, ['LIMIT', 'STOP_LOSS_LIMIT', 'TAKE_PROFIT_LIMIT', 'LIMIT_MAKER'], true);
            if ($requiresPrice) {
                if (empty($params['price']) || !is_numeric($params['price'])) {
                    return [
                        'success' => false,
                        'error' => 'Parâmetro "price" é obrigatório e deve ser numérico para este tipo de ordem'
                    ];
                }

                $orderParams['price'] = $params['price'];

                if ($type !== 'LIMIT_MAKER') {
                    $orderParams['timeInForce'] = strtoupper($params['timeInForce'] ?? 'GTC');
                }
            }

            // STOP/TAKE sem LIMIT exigem stopPrice
            $requiresStop = in_array($type, ['STOP_LOSS', 'STOP_LOSS_LIMIT', 'TAKE_PROFIT', 'TAKE_PROFIT_LIMIT'], true);
            if ($requiresStop) {
                if (empty($params['stopPrice']) || !is_numeric($params['stopPrice'])) {
                    return [
                        'success' => false,
                        'error' => 'Parâmetro "stopPrice" é obrigatório e deve ser numérico para este tipo de ordem'
                    ];
                }
                $orderParams['stopPrice'] = $params['stopPrice'];
            }

            $client = new BinanceClient($params['api_key'], $params['secret_key']);

            $response = $client->post('/api/v3/order', $orderParams);

            return $this->formatResponse($response);
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
     * @param array<string,mixed> $params Parâmetros da requisição
     * @return array<string,mixed> Resposta da API
     */
    public function cancelOrder(array $params): array
    {
        try {
            if ($error = Validation::requireFields($params, ['api_key', 'secret_key'])) {
                return ['success' => false, 'error' => $error];
            }

            if ($error = Validation::requireFields($params, ['symbol', 'orderId'])) {
                return ['success' => false, 'error' => $error];
            }

            $client = new BinanceClient($params['api_key'], $params['secret_key']);

            $response = $client->delete('/api/v3/order', [
                'symbol' => $params['symbol'],
                'orderId' => $params['orderId']
            ]);

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao cancelar ordem: ' . $e->getMessage()
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
