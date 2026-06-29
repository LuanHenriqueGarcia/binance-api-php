<?php

namespace BinanceAPI\Controllers;

use BinanceAPI\CoinbaseClient;
use BinanceAPI\Contracts\ClientInterface;
use BinanceAPI\Validation;

class CoinbaseMarketController
{
    private ?ClientInterface $client;

    public function __construct(?ClientInterface $client = null)
    {
        $this->client = $client;
    }

    private function getClient(): ClientInterface
    {
        return $this->client ?? new CoinbaseClient();
    }

    /**
     * Lista produtos disponíveis (público)
     * GET /api/coinbase/market/products
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function products(array $params): array
    {
        try {
            $response = $this->getClient()->get('/api/v3/brokerage/market/products', [
                'limit' => $params['limit'] ?? null,
                'offset' => $params['offset'] ?? null,
                'product_type' => $params['product_type'] ?? null,
                'product_ids' => $this->normalizeListParam($params['product_ids'] ?? null),
                'contract_expiry_type' => $params['contract_expiry_type'] ?? null,
                'expiring_contract_status' => $params['expiring_contract_status'] ?? null,
                'get_all_products' => $params['get_all_products'] ?? null,
            ]);

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao listar produtos: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Detalhes de um produto (público)
     * GET /api/coinbase/market/product?product_id=BTC-USD
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function product(array $params): array
    {
        try {
            $productId = $this->resolveProductId($params);
            if ($error = Validation::requireFields(['product_id' => $productId], ['product_id'])) {
                return ['success' => false, 'error' => $error];
            }

            $response = $this->getClient()->get('/api/v3/brokerage/market/products/' . rawurlencode((string) $productId));

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao obter produto: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Livro de ofertas (público)
     * GET /api/coinbase/market/product-book?product_id=BTC-USD
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function productBook(array $params): array
    {
        try {
            $productId = $this->resolveProductId($params);
            if ($error = Validation::requireFields(['product_id' => $productId], ['product_id'])) {
                return ['success' => false, 'error' => $error];
            }

            $response = $this->getClient()->get('/api/v3/brokerage/market/product_book', [
                'product_id' => $productId,
                'limit' => $params['limit'] ?? null,
                'aggregation_price_increment' => $params['aggregation_price_increment'] ?? null,
            ]);

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao obter livro de ofertas: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Ticker de produto (público)
     * GET /api/coinbase/market/ticker?product_id=BTC-USD
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function ticker(array $params): array
    {
        try {
            $productId = $this->resolveProductId($params);
            if ($error = Validation::requireFields(['product_id' => $productId], ['product_id'])) {
                return ['success' => false, 'error' => $error];
            }

            $response = $this->getClient()->get('/api/v3/brokerage/market/products/' . rawurlencode((string) $productId) . '/ticker', [
                'limit' => $params['limit'] ?? null,
                'start' => $params['start'] ?? null,
                'end' => $params['end'] ?? null,
            ]);

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao obter ticker: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Candles (público)
     * GET /api/coinbase/market/candles?product_id=BTC-USD&start=...&end=...&granularity=ONE_MINUTE
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function candles(array $params): array
    {
        try {
            $productId = $this->resolveProductId($params);
            if ($error = Validation::requireFields([
                'product_id' => $productId,
                'start' => $params['start'] ?? null,
                'end' => $params['end'] ?? null,
                'granularity' => $params['granularity'] ?? null,
            ], ['product_id', 'start', 'end', 'granularity'])) {
                return ['success' => false, 'error' => $error];
            }

            $response = $this->getClient()->get('/api/v3/brokerage/market/products/' . rawurlencode((string) $productId) . '/candles', [
                'start' => $params['start'],
                'end' => $params['end'],
                'granularity' => $params['granularity'],
                'limit' => $params['limit'] ?? null,
            ]);

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao obter candles: ' . $e->getMessage()
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
}
