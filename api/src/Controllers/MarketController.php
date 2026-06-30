<?php

namespace TradersApi\Controllers;

use TradersApi\BinanceClient;
use TradersApi\Contracts\ClientInterface;
use TradersApi\Validation;

class MarketController
{
    private ?ClientInterface $client;

    public function __construct(?ClientInterface $client = null)
    {
        $this->client = $client;
    }

    private function getClient(?string $apiKey = null, ?string $secretKey = null): ClientInterface
    {
        if ($this->client !== null) {
            return $this->client;
        }
        return new BinanceClient($apiKey, $secretKey);
    }

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

            $response = $this->getClient()->get('/api/v3/ticker/24hr', [
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

            $limit = $params['limit'] ?? 100;

            $response = $this->getClient()->get('/api/v3/depth', [
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

            $limit = $params['limit'] ?? 500;

            $response = $this->getClient()->get('/api/v3/trades', [
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
     * Lista trades históricos (requer API Key)
     * GET /api/market/historical-trades?symbol=BTCUSDT&limit=100
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function historicalTrades(array $params): array
    {
        try {
            if ($error = Validation::requireFields($params, ['symbol'])) {
                return ['success' => false, 'error' => $error];
            }

            $apiKey = $params['api_key'] ?? null;
            $client = $this->getClient($apiKey, null);
            $limit = $params['limit'] ?? 500;

            $response = $client->get('/api/v3/historicalTrades', [
                'symbol' => $params['symbol'],
                'limit' => $limit,
                'fromId' => $params['fromId'] ?? null,
            ]);

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao obter historical trades: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Preço médio ponderado (5m)
     * GET /api/market/avg-price?symbol=BTCUSDT
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function avgPrice(array $params): array
    {
        try {
            if ($error = Validation::requireFields($params, ['symbol'])) {
                return ['success' => false, 'error' => $error];
            }

            $response = $this->getClient()->get('/api/v3/avgPrice', [
                'symbol' => $params['symbol']
            ]);

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao obter preço médio: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Book ticker (um ou todos)
     * GET /api/market/book-ticker?symbol=BTCUSDT
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function bookTicker(array $params): array
    {
        try {
            $options = [];
            if (!empty($params['symbol'])) {
                $options['symbol'] = $params['symbol'];
            }

            $response = $this->getClient()->get('/api/v3/ticker/bookTicker', $options);
            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao obter book ticker: ' . $e->getMessage()
            ];
        }
    }

    /**
     * AggTrades
     * GET /api/market/agg-trades?symbol=BTCUSDT&limit=500
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function aggTrades(array $params): array
    {
        try {
            if ($error = Validation::requireFields($params, ['symbol'])) {
                return ['success' => false, 'error' => $error];
            }

            $limit = $params['limit'] ?? 500;

            $response = $this->getClient()->get('/api/v3/aggTrades', [
                'symbol' => $params['symbol'],
                'limit' => $limit,
                'startTime' => $params['startTime'] ?? null,
                'endTime' => $params['endTime'] ?? null,
                'fromId' => $params['fromId'] ?? null,
            ]);

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao obter aggTrades: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Klines
     * GET /api/market/klines?symbol=BTCUSDT&interval=1h&limit=500
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function klines(array $params): array
    {
        try {
            if ($error = Validation::requireFields($params, ['symbol', 'interval'])) {
                return ['success' => false, 'error' => $error];
            }

            $limit = $params['limit'] ?? 500;

            $response = $this->getClient()->get('/api/v3/klines', [
                'symbol' => $params['symbol'],
                'interval' => $params['interval'],
                'limit' => $limit,
                'startTime' => $params['startTime'] ?? null,
                'endTime' => $params['endTime'] ?? null,
            ]);

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao obter klines: ' . $e->getMessage()
            ];
        }
    }

    /**
     * UI Klines (interface)
     * GET /api/market/ui-klines?symbol=BTCUSDT&interval=1h&limit=500
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function uiKlines(array $params): array
    {
        try {
            if ($error = Validation::requireFields($params, ['symbol', 'interval'])) {
                return ['success' => false, 'error' => $error];
            }

            $limit = $params['limit'] ?? 500;

            $response = $this->getClient()->get('/api/v3/uiKlines', [
                'symbol' => $params['symbol'],
                'interval' => $params['interval'],
                'limit' => $limit,
                'startTime' => $params['startTime'] ?? null,
                'endTime' => $params['endTime'] ?? null,
            ]);

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao obter uiKlines: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Rolling window ticker (min/max/média no período)
     * GET /api/market/rolling-window-ticker?symbol=BTCUSDT&windowSize=1d
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function rollingWindowTicker(array $params): array
    {
        try {
            // Validar que pelo menos symbol ou symbols foi fornecido
            if (empty($params['symbol']) && empty($params['symbols'])) {
                return [
                    'success' => false,
                    'error' => 'Parâmetro obrigatório: symbol ou symbols'
                ];
            }

            $window = $params['windowSize'] ?? '1d';

            $options = [
                'windowSize' => $window,
            ];

            // type aceita apenas FULL ou MINI (default: FULL)
            if (!empty($params['type']) && in_array($params['type'], ['FULL', 'MINI'])) {
                $options['type'] = $params['type'];
            }

            if (!empty($params['symbol'])) {
                $options['symbol'] = $params['symbol'];
            }
            if (!empty($params['symbols'])) {
                $options['symbols'] = $params['symbols'];
            }

            $response = $this->getClient()->get('/api/v3/ticker', $options);
            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao obter rolling window ticker: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Ticker price (um ou todos)
     * GET /api/market/ticker-price
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function tickerPrice(array $params): array
    {
        try {
            $options = [];
            if (!empty($params['symbol'])) {
                $options['symbol'] = $params['symbol'];
            }

            $response = $this->getClient()->get('/api/v3/ticker/price', $options);
            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao obter ticker price: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 24h ticker (um ou todos)
     * GET /api/market/ticker-24h
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function ticker24h(array $params): array
    {
        try {
            $options = [];
            if (!empty($params['symbol'])) {
                $options['symbol'] = $params['symbol'];
            }

            $response = $this->getClient()->get('/api/v3/ticker/24hr', $options);
            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao obter ticker 24h: ' . $e->getMessage()
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
