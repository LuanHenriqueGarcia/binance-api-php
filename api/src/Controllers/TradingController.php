<?php

namespace TradersApi\Controllers;

use TradersApi\BinanceClient;
use TradersApi\Contracts\ClientInterface;
use TradersApi\Validation;

class TradingController
{
    private ?ClientInterface $client;

    public function __construct(?ClientInterface $client = null)
    {
        $this->client = $client;
    }

    /**
     * Retorna o cliente (injetado ou novo)
     */
    private function getClient(?string $apiKey = null, ?string $secretKey = null): ClientInterface
    {
        if ($this->client !== null) {
            return $this->client;
        }

        return new BinanceClient($apiKey, $secretKey);
    }

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
            $validated = $this->buildOrderParams($params);
            if (isset($validated['error'])) {
                return $validated;
            }

            $client = $this->getClient($params['api_key'], $params['secret_key']);

            $response = $client->post('/api/v3/order', $validated['orderParams']);

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

            $client = $this->getClient($params['api_key'], $params['secret_key']);

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
     * Testa criação de ordem sem executar
     * POST /api/trading/test-order
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function testOrder(array $params): array
    {
        try {
            $validated = $this->buildOrderParams($params);
            if (isset($validated['error'])) {
                return $validated;
            }

            $client = $this->getClient($params['api_key'], $params['secret_key']);
            $response = $client->post('/api/v3/order/test', $validated['orderParams']);

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao testar ordem: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Consulta ordem
     * GET /api/trading/query-order
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function queryOrder(array $params): array
    {
        try {
            if ($error = Validation::requireFields($params, ['api_key', 'secret_key'])) {
                return ['success' => false, 'error' => $error];
            }

            if ($error = Validation::requireFields($params, ['symbol'])) {
                return ['success' => false, 'error' => $error];
            }

            if (empty($params['orderId']) && empty($params['origClientOrderId'])) {
                return [
                    'success' => false,
                    'error' => 'Informe "orderId" ou "origClientOrderId" para consultar a ordem'
                ];
            }

            $client = $this->getClient($params['api_key'], $params['secret_key']);
            $response = $client->get('/api/v3/order', [
                'symbol' => $params['symbol'],
                'orderId' => $params['orderId'] ?? null,
                'origClientOrderId' => $params['origClientOrderId'] ?? null,
            ]);

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao consultar ordem: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Cancela todas as ordens abertas de um símbolo
     * DELETE /api/trading/cancel-open-orders
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function cancelOpenOrders(array $params): array
    {
        try {
            if ($error = Validation::requireFields($params, ['api_key', 'secret_key'])) {
                return ['success' => false, 'error' => $error];
            }

            if ($error = Validation::requireFields($params, ['symbol'])) {
                return ['success' => false, 'error' => $error];
            }

            $client = $this->getClient($params['api_key'], $params['secret_key']);
            $response = $client->delete('/api/v3/openOrders', [
                'symbol' => $params['symbol'],
            ]);

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao cancelar ordens abertas: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Cria ordem OCO
     * POST /api/trading/create-oco
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function createOco(array $params): array
    {
        try {
            if ($error = Validation::requireFields($params, ['api_key', 'secret_key'])) {
                return ['success' => false, 'error' => $error];
            }

            if ($error = Validation::requireFields($params, ['symbol', 'side', 'quantity', 'price', 'stopPrice'])) {
                return ['success' => false, 'error' => $error];
            }

            $side = strtoupper($params['side']);
            if (!in_array($side, ['BUY', 'SELL'], true)) {
                return [
                    'success' => false,
                    'error' => 'Parâmetro "side" deve ser BUY ou SELL'
                ];
            }

            $payload = [
                'symbol' => strtoupper($params['symbol']),
                'side' => $side,
                'quantity' => $params['quantity'],
                'price' => $params['price'],
                'stopPrice' => $params['stopPrice'],
                'stopLimitPrice' => $params['stopLimitPrice'] ?? null,
                'stopLimitTimeInForce' => strtoupper($params['stopLimitTimeInForce'] ?? 'GTC'),
                'limitClientOrderId' => $params['limitClientOrderId'] ?? null,
                'stopClientOrderId' => $params['stopClientOrderId'] ?? null,
                'listClientOrderId' => $params['listClientOrderId'] ?? null,
                'limitIcebergQty' => $params['limitIcebergQty'] ?? null,
                'stopIcebergQty' => $params['stopIcebergQty'] ?? null,
            ];

            $client = $this->getClient($params['api_key'], $params['secret_key']);
            $response = $client->post('/api/v3/order/oco', $payload);

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao criar OCO: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Lista OCOs
     * GET /api/trading/list-oco
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function listOco(array $params): array
    {
        try {
            if ($error = Validation::requireFields($params, ['api_key', 'secret_key'])) {
                return ['success' => false, 'error' => $error];
            }

            $client = $this->getClient($params['api_key'], $params['secret_key']);
            $response = $client->get('/api/v3/allOrderList', [
                'fromId' => $params['fromId'] ?? null,
                'startTime' => $params['startTime'] ?? null,
                'endTime' => $params['endTime'] ?? null,
                'limit' => $params['limit'] ?? 50,
            ]);

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao listar OCOs: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Cancela OCO
     * DELETE /api/trading/cancel-oco
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function cancelOco(array $params): array
    {
        try {
            if ($error = Validation::requireFields($params, ['api_key', 'secret_key'])) {
                return ['success' => false, 'error' => $error];
            }

            if (empty($params['orderListId']) && empty($params['listClientOrderId'])) {
                return [
                    'success' => false,
                    'error' => 'Informe "orderListId" ou "listClientOrderId" para cancelar OCO'
                ];
            }

            $client = $this->getClient($params['api_key'], $params['secret_key']);
            $response = $client->delete('/api/v3/orderList', [
                'orderListId' => $params['orderListId'] ?? null,
                'listClientOrderId' => $params['listClientOrderId'] ?? null,
                'newClientOrderId' => $params['newClientOrderId'] ?? null,
                'symbol' => $params['symbol'] ?? null,
            ]);

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao cancelar OCO: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Consulta limites de ordem (rate limit)
     * GET /api/trading/order-rate-limit
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function orderRateLimit(array $params): array
    {
        try {
            if ($error = Validation::requireFields($params, ['api_key', 'secret_key'])) {
                return ['success' => false, 'error' => $error];
            }

            $client = $this->getClient($params['api_key'], $params['secret_key']);
            $response = $client->get('/sapi/v1/rateLimit/order');

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao obter limites de ordem: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Consulta comissão da conta para um símbolo
     * GET /api/trading/commission-rate?symbol=BTCUSDT
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function commissionRate(array $params): array
    {
        try {
            if ($error = Validation::requireFields($params, ['api_key', 'secret_key'])) {
                return ['success' => false, 'error' => $error];
            }

            if ($error = Validation::requireFields($params, ['symbol'])) {
                return ['success' => false, 'error' => $error];
            }

            $client = $this->getClient($params['api_key'], $params['secret_key']);
            $response = $client->get('/sapi/v1/account/commission', [
                'symbol' => $params['symbol']
            ]);

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao obter comissão: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Cancelamento + recriação de ordem (SOR)
     * POST /api/trading/cancel-replace
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function cancelReplace(array $params): array
    {
        try {
            $validated = $this->buildOrderParams($params);
            if (isset($validated['error'])) {
                return $validated;
            }

            if (empty($params['cancelReplaceMode'])) {
                return [
                    'success' => false,
                    'error' => 'Parâmetro "cancelReplaceMode" é obrigatório (ex: STOP_ON_FAILURE)'
                ];
            }

            if (empty($params['cancelOrderId']) && empty($params['cancelOrigClientOrderId'])) {
                return [
                    'success' => false,
                    'error' => 'Informe "cancelOrderId" ou "cancelOrigClientOrderId"'
                ];
            }

            $mode = strtoupper((string)$params['cancelReplaceMode']);
            $allowedModes = ['STOP_ON_FAILURE', 'ALLOW_FAILURE'];
            if (!in_array($mode, $allowedModes, true)) {
                return [
                    'success' => false,
                    'error' => 'Parâmetro "cancelReplaceMode" inválido (use STOP_ON_FAILURE ou ALLOW_FAILURE)'
                ];
            }

            $payload = array_merge($validated['orderParams'], [
                'cancelReplaceMode' => $mode,
                'cancelOrderId' => $params['cancelOrderId'] ?? null,
                'cancelOrigClientOrderId' => $params['cancelOrigClientOrderId'] ?? null,
                'newClientOrderId' => $params['newClientOrderId'] ?? null,
                'cancelNewClientOrderId' => $params['cancelNewClientOrderId'] ?? null,
                'newOrderRespType' => $params['newOrderRespType'] ?? null,
            ]);

            $client = $this->getClient($params['api_key'], $params['secret_key']);
            $response = $client->post('/api/v3/order/cancelReplace', $payload);

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao cancelar/recriar ordem: ' . $e->getMessage()
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

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    private function buildOrderParams(array $params): array
    {
        if ($error = Validation::requireFields($params, ['api_key', 'secret_key'])) {
            return ['success' => false, 'error' => $error];
        }

        if ($error = Validation::requireFields($params, ['symbol', 'side', 'type'])) {
            return ['success' => false, 'error' => $error];
        }

        $side = strtoupper($params['side']);
        $type = strtoupper($params['type']);
        $symbol = strtoupper($params['symbol']);

        if (!preg_match('/^[A-Z0-9]{4,20}$/', $symbol)) {
            return [
                'success' => false,
                'error' => 'Parâmetro "symbol" inválido'
            ];
        }

        $allowedTypes = [
            'LIMIT',
            'MARKET',
            'STOP_LOSS',
            'STOP_LOSS_LIMIT',
            'TAKE_PROFIT',
            'TAKE_PROFIT_LIMIT',
            'LIMIT_MAKER'
        ];

        if (!in_array($side, ['BUY', 'SELL'], true)) {
            return [
                'success' => false,
                'error' => 'Parâmetro "side" deve ser BUY ou SELL'
            ];
        }

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

        $requiresPrice = in_array($type, ['LIMIT', 'STOP_LOSS_LIMIT', 'TAKE_PROFIT_LIMIT', 'LIMIT_MAKER'], true);
        if ($requiresPrice) {
            if (empty($params['price']) || !is_numeric($params['price']) || (float)$params['price'] <= 0) {
                return [
                    'success' => false,
                    'error' => 'Parâmetro "price" é obrigatório e deve ser numérico/positivo para este tipo de ordem'
                ];
            }

            $orderParams['price'] = $params['price'];

            if ($type !== 'LIMIT_MAKER') {
                $timeInForce = strtoupper($params['timeInForce'] ?? 'GTC');
                $allowedTif = ['GTC', 'IOC', 'FOK'];
                if (!in_array($timeInForce, $allowedTif, true)) {
                    return [
                        'success' => false,
                        'error' => 'Parâmetro "timeInForce" inválido (use GTC, IOC ou FOK)'
                    ];
                }
                $orderParams['timeInForce'] = $timeInForce;
            }
        }

        $requiresStop = in_array($type, ['STOP_LOSS', 'STOP_LOSS_LIMIT', 'TAKE_PROFIT', 'TAKE_PROFIT_LIMIT'], true);
        if ($requiresStop) {
            if (empty($params['stopPrice']) || !is_numeric($params['stopPrice']) || (float)$params['stopPrice'] <= 0) {
                return [
                    'success' => false,
                    'error' => 'Parâmetro "stopPrice" é obrigatório e deve ser numérico/positivo para este tipo de ordem'
                ];
            }
            $orderParams['stopPrice'] = $params['stopPrice'];
        }

        return [
            'success' => true,
            'orderParams' => $orderParams
        ];
    }
}
