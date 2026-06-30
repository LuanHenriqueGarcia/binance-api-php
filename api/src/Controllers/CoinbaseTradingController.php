<?php

namespace TradersApi\Controllers;

use TradersApi\CoinbaseClient;
use TradersApi\Config;
use TradersApi\Contracts\ClientInterface;
use TradersApi\Validation;

class CoinbaseTradingController
{
    private ?ClientInterface $client;

    public function __construct(?ClientInterface $client = null)
    {
        $this->client = $client;
    }

    private function getClient(?string $apiKey = null, ?string $secretKey = null, ?string $keyFile = null): ClientInterface
    {
        if ($this->client !== null) {
            return $this->client;
        }

        return new CoinbaseClient($apiKey, $secretKey, $keyFile);
    }

    /**
     * Cria uma nova ordem
     * POST /api/coinbase/trading/create-order
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function createOrder(array $params): array
    {
        try {
            [$apiKey, $secretKey, $keyFile] = $this->resolveCredentials($params);
            if ($error = $this->validateCredentials($apiKey, $secretKey, $keyFile)) {
                return ['success' => false, 'error' => $error];
            }

            $productId = $this->resolveProductId($params);
            $side = strtoupper((string) ($params['side'] ?? ''));
            $type = strtoupper((string) ($params['type'] ?? ''));

            if ($error = Validation::requireFields([
                'product_id' => $productId,
                'side' => $side,
                'type' => $type,
            ], ['product_id', 'side', 'type'])) {
                return ['success' => false, 'error' => $error];
            }

            if (!in_array($side, ['BUY', 'SELL'], true)) {
                return ['success' => false, 'error' => 'Parâmetro "side" deve ser BUY ou SELL'];
            }

            $orderConfiguration = $this->buildOrderConfiguration($params, $type);
            if (isset($orderConfiguration['success']) && $orderConfiguration['success'] === false) {
                return $orderConfiguration;
            }

            $payload = [
                'client_order_id' => $params['client_order_id'] ?? $this->generateClientOrderId(),
                'product_id' => $productId,
                'side' => $side,
                'order_configuration' => $orderConfiguration,
                'self_trade_prevention_id' => $params['self_trade_prevention_id'] ?? null,
                'leverage' => $params['leverage'] ?? null,
                'margin_type' => $params['margin_type'] ?? null,
                'retail_portfolio_id' => $params['retail_portfolio_id'] ?? null,
            ];

            $response = $this->getClient($apiKey, $secretKey, $keyFile)->post('/api/v3/brokerage/orders', $payload);

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao criar ordem: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Cancela uma ou mais ordens
     * POST /api/coinbase/trading/cancel-order
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function cancelOrder(array $params): array
    {
        try {
            [$apiKey, $secretKey, $keyFile] = $this->resolveCredentials($params);
            if ($error = $this->validateCredentials($apiKey, $secretKey, $keyFile)) {
                return ['success' => false, 'error' => $error];
            }

            $orderIds = $this->normalizeListToArray($params['order_ids'] ?? $params['order_id'] ?? null);
            if ($error = Validation::requireFields(['order_ids' => $orderIds], ['order_ids'])) {
                return ['success' => false, 'error' => 'Informe "order_id" ou "order_ids"'];
            }

            $response = $this->getClient($apiKey, $secretKey, $keyFile)->post('/api/v3/brokerage/orders/batch_cancel', [
                'order_ids' => array_values($orderIds),
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
     * Consulta uma ordem
     * GET /api/coinbase/trading/get-order?order_id=...
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function getOrder(array $params): array
    {
        try {
            [$apiKey, $secretKey, $keyFile] = $this->resolveCredentials($params);
            if ($error = $this->validateCredentials($apiKey, $secretKey, $keyFile)) {
                return ['success' => false, 'error' => $error];
            }

            if ($error = Validation::requireFields($params, ['order_id'])) {
                return ['success' => false, 'error' => $error];
            }

            $response = $this->getClient($apiKey, $secretKey, $keyFile)->get('/api/v3/brokerage/orders/historical/' . rawurlencode((string) $params['order_id']));

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao consultar ordem: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Lista ordens históricas
     * GET /api/coinbase/trading/list-orders
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function listOrders(array $params): array
    {
        try {
            [$apiKey, $secretKey, $keyFile] = $this->resolveCredentials($params);
            if ($error = $this->validateCredentials($apiKey, $secretKey, $keyFile)) {
                return ['success' => false, 'error' => $error];
            }

            $options = [
                'order_ids' => $this->normalizeListParam($params['order_ids'] ?? null),
                'product_ids' => $this->normalizeListParam($params['product_ids'] ?? null),
                'order_status' => $this->normalizeListParam($params['order_status'] ?? null),
                'limit' => $params['limit'] ?? null,
                'start_date' => $params['start_date'] ?? null,
                'end_date' => $params['end_date'] ?? null,
                'order_types' => $params['order_types'] ?? null,
                'order_side' => $params['order_side'] ?? null,
                'cursor' => $params['cursor'] ?? null,
                'product_type' => $params['product_type'] ?? null,
                'order_placement_source' => $params['order_placement_source'] ?? null,
                'contract_expiry_type' => $params['contract_expiry_type'] ?? null,
                'asset_filters' => $this->normalizeListParam($params['asset_filters'] ?? null),
                'retail_portfolio_id' => $params['retail_portfolio_id'] ?? null,
                'time_in_forces' => $params['time_in_forces'] ?? null,
                'sort_by' => $params['sort_by'] ?? null,
            ];

            $response = $this->getClient($apiKey, $secretKey, $keyFile)->get('/api/v3/brokerage/orders/historical/batch', $options);

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao listar ordens: ' . $e->getMessage()
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
     * @return array{0:?string,1:?string,2:?string}
     */
    private function resolveCredentials(array $params): array
    {
        return [
            $params['api_key'] ?? Config::getCoinbaseApiKey(),
            $params['api_secret'] ?? $params['secret_key'] ?? Config::getCoinbaseApiSecret(),
            $params['key_file'] ?? Config::getCoinbaseKeyFile(),
        ];
    }

    private function validateCredentials(?string $apiKey, ?string $secretKey, ?string $keyFile): ?string
    {
        if ($apiKey && $secretKey) {
            return null;
        }

        if ($keyFile) {
            return null;
        }

        return 'Chaves de API não fornecidas. Configure no .env ou passe como parâmetros.';
    }

    /**
     * @param mixed $value
     * @return array<int,string>
     */
    private function normalizeListToArray($value): array
    {
        if (is_array($value)) {
            $items = array_filter(array_map('trim', $value), static function ($item) {
                return $item !== '';
            });
            return array_values($items);
        }

        if (is_string($value)) {
            $parts = array_filter(array_map('trim', explode(',', $value)), static function ($item) {
                return $item !== '';
            });
            return array_values($parts);
        }

        return [];
    }

    /**
     * @param mixed $value
     */
    private function normalizeListParam($value): ?string
    {
        if (is_array($value)) {
            $items = array_filter(array_map('trim', $value), static function ($item) {
                return $item !== '';
            });
            return $items ? implode(',', $items) : null;
        }

        if (is_string($value)) {
            return trim($value) !== '' ? $value : null;
        }

        return null;
    }

    /**
     * @param array<string,mixed> $params
     */
    private function resolveProductId(array $params): ?string
    {
        if (!empty($params['product_id'])) {
            return (string) $params['product_id'];
        }
        if (!empty($params['symbol'])) {
            return (string) $params['symbol'];
        }
        return null;
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    private function buildOrderConfiguration(array $params, string $type): array
    {
        if ($type === 'MARKET') {
            $baseSize = $params['base_size'] ?? $params['quantity'] ?? null;
            $quoteSize = $params['quote_size'] ?? $params['quoteOrderQty'] ?? null;

            if ($baseSize === null && $quoteSize === null) {
                return [
                    'success' => false,
                    'error' => 'Informe "base_size" ou "quote_size" para ordens MARKET'
                ];
            }

            $marketConfig = array_filter([
                'base_size' => $baseSize,
                'quote_size' => $quoteSize,
            ], static function ($value) {
                return $value !== null;
            });

            return ['market_market_ioc' => $marketConfig];
        }

        if ($type === 'LIMIT') {
            $baseSize = $params['base_size'] ?? $params['quantity'] ?? null;
            $limitPrice = $params['limit_price'] ?? $params['price'] ?? null;

            if ($baseSize === null) {
                return [
                    'success' => false,
                    'error' => 'Parâmetro "base_size" é obrigatório'
                ];
            }

            if ($limitPrice === null) {
                return [
                    'success' => false,
                    'error' => 'Parâmetro "limit_price" é obrigatório'
                ];
            }

            $tif = strtoupper((string) ($params['time_in_force'] ?? $params['timeInForce'] ?? 'GTC'));
            if (!in_array($tif, ['GTC', 'IOC', 'FOK'], true)) {
                return [
                    'success' => false,
                    'error' => 'Parâmetro "time_in_force" inválido (use GTC, IOC ou FOK)'
                ];
            }

            if ($tif === 'IOC') {
                return ['sor_limit_ioc' => ['base_size' => $baseSize, 'limit_price' => $limitPrice]];
            }

            if ($tif === 'FOK') {
                return ['limit_limit_fok' => ['base_size' => $baseSize, 'limit_price' => $limitPrice]];
            }

            $postOnly = $this->normalizeBool($params['post_only'] ?? $params['postOnly'] ?? false);

            return [
                'limit_limit_gtc' => [
                    'base_size' => $baseSize,
                    'limit_price' => $limitPrice,
                    'post_only' => $postOnly,
                ]
            ];
        }

        return [
            'success' => false,
            'error' => 'Parâmetro "type" inválido (use MARKET ou LIMIT)'
        ];
    }

    /**
     * @param mixed $value
     */
    private function normalizeBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['true', '1', 'yes', 'on'], true);
        }

        return (bool) $value;
    }

    private function generateClientOrderId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
