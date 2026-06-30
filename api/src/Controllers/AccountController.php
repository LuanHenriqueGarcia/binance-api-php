<?php

namespace TradersApi\Controllers;

use TradersApi\BinanceClient;
use TradersApi\Contracts\ClientInterface;
use TradersApi\Config;
use TradersApi\Validation;

class AccountController
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
     * Obtém informações da conta Binance
     * GET /api/account/info?api_key=xxx&secret_key=yyy
     *
     * @param array<string,mixed> $params Parâmetros da requisição
     * @return array<string,mixed> Resposta da API
     */
    public function getAccountInfo(array $params): array
    {
        try {
            $apiKey = $params['api_key'] ?? Config::getBinanceApiKey();
            $secretKey = $params['secret_key'] ?? Config::getBinanceSecretKey();

            if ($error = Validation::requireFields(['api_key' => $apiKey, 'secret_key' => $secretKey], ['api_key', 'secret_key'])) {
                return [
                    'success' => false,
                    'error' => 'Chaves de API não fornecidas. Configure no .env ou passe como parâmetros.'
                ];
            }

            $response = $this->getClient($apiKey, $secretKey)->get('/api/v3/account');

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao obter informações da conta: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtém ordens abertas
     * GET /api/account/open-orders?api_key=xxx&secret_key=yyy&symbol=BTCUSDT
     *
     * @param array<string,mixed> $params Parâmetros da requisição
     * @return array<string,mixed> Resposta da API
     */
    public function getOpenOrders(array $params): array
    {
        try {
            $apiKey = $params['api_key'] ?? Config::getBinanceApiKey();
            $secretKey = $params['secret_key'] ?? Config::getBinanceSecretKey();

            if ($error = Validation::requireFields(['api_key' => $apiKey, 'secret_key' => $secretKey], ['api_key', 'secret_key'])) {
                return [
                    'success' => false,
                    'error' => 'Chaves de API não fornecidas. Configure no .env ou passe como parâmetros.'
                ];
            }

            $options = [];
            if (!empty($params['symbol'])) {
                $options['symbol'] = $params['symbol'];
            }

            $response = $this->getClient($apiKey, $secretKey)->get('/api/v3/openOrders', $options);

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao obter ordens abertas: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtém histórico de ordens
     * GET /api/account/order-history?api_key=xxx&secret_key=yyy&symbol=BTCUSDT&limit=500
     *
     * @param array<string,mixed> $params Parâmetros da requisição
     * @return array<string,mixed> Resposta da API
     */
    public function getOrderHistory(array $params): array
    {
        try {
            $apiKey = $params['api_key'] ?? Config::getBinanceApiKey();
            $secretKey = $params['secret_key'] ?? Config::getBinanceSecretKey();

            if ($error = Validation::requireFields(['api_key' => $apiKey, 'secret_key' => $secretKey], ['api_key', 'secret_key'])) {
                return [
                    'success' => false,
                    'error' => 'Chaves de API não fornecidas. Configure no .env ou passe como parâmetros.'
                ];
            }

            if ($error = Validation::requireFields($params, ['symbol'])) {
                return ['success' => false, 'error' => $error];
            }

            $limit = $params['limit'] ?? 500;

            $response = $this->getClient($apiKey, $secretKey)->get('/api/v3/allOrders', [
                'symbol' => $params['symbol'],
                'limit' => $limit
            ]);

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao obter histórico de ordens: ' . $e->getMessage()
            ];
        }
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function getAssetBalance(array $params): array
    {
        try {
            $apiKey = $params['api_key'] ?? Config::getBinanceApiKey();
            $secretKey = $params['secret_key'] ?? Config::getBinanceSecretKey();

            if ($error = Validation::requireFields(['api_key' => $apiKey, 'secret_key' => $secretKey], ['api_key', 'secret_key'])) {
                return [
                    'success' => false,
                    'error' => 'Chaves de API não fornecidas'
                ];
            }

            if ($error = Validation::requireFields($params, ['asset'])) {
                return ['success' => false, 'error' => $error . ' (ex: ETH, BTC, USDT)'];
            }

            $response = $this->getClient($apiKey, $secretKey)->get('/api/v3/account');

            if (isset($response['success']) && $response['success'] === false) {
                return $response;
            }

            $asset = strtoupper($params['asset']);
            foreach ($response['balances'] as $balance) {
                if ($balance['asset'] === $asset) {
                    return [
                        'success' => true,
                        'data' => [
                            'asset' => $balance['asset'],
                            'free' => $balance['free'],
                            'locked' => $balance['locked'],
                            'total' => (float)$balance['free'] + (float)$balance['locked']
                        ]
                    ];
                }
            }

            return [
                'success' => false,
                'error' => "Ativo \"$asset\" não encontrado"
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao obter saldo: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Lista trades da conta para um símbolo
     * GET /api/account/my-trades?symbol=BTCUSDT
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function getMyTrades(array $params): array
    {
        try {
            $apiKey = $params['api_key'] ?? Config::getBinanceApiKey();
            $secretKey = $params['secret_key'] ?? Config::getBinanceSecretKey();

            if ($error = Validation::requireFields(['api_key' => $apiKey, 'secret_key' => $secretKey], ['api_key', 'secret_key'])) {
                return [
                    'success' => false,
                    'error' => 'Chaves de API não fornecidas. Configure no .env ou passe como parâmetros.'
                ];
            }

            if ($error = Validation::requireFields($params, ['symbol'])) {
                return ['success' => false, 'error' => $error];
            }

            $response = $this->getClient($apiKey, $secretKey)->get('/api/v3/myTrades', [
                'symbol' => $params['symbol'],
                'limit' => $params['limit'] ?? 500,
                'fromId' => $params['fromId'] ?? null,
                'startTime' => $params['startTime'] ?? null,
                'endTime' => $params['endTime'] ?? null,
            ]);

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao obter trades da conta: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Status da conta
     * GET /api/account/account-status
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function getAccountStatus(array $params): array
    {
        try {
            $apiKey = $params['api_key'] ?? Config::getBinanceApiKey();
            $secretKey = $params['secret_key'] ?? Config::getBinanceSecretKey();

            if ($error = Validation::requireFields(['api_key' => $apiKey, 'secret_key' => $secretKey], ['api_key', 'secret_key'])) {
                return [
                    'success' => false,
                    'error' => 'Chaves de API não fornecidas. Configure no .env ou passe como parâmetros.'
                ];
            }

            $response = $this->getClient($apiKey, $secretKey)->get('/sapi/v1/account/status');

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao obter status da conta: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Status de trading da conta
     * GET /api/account/api-trading-status
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function getApiTradingStatus(array $params): array
    {
        try {
            $apiKey = $params['api_key'] ?? Config::getBinanceApiKey();
            $secretKey = $params['secret_key'] ?? Config::getBinanceSecretKey();

            if ($error = Validation::requireFields(['api_key' => $apiKey, 'secret_key' => $secretKey], ['api_key', 'secret_key'])) {
                return [
                    'success' => false,
                    'error' => 'Chaves de API não fornecidas. Configure no .env ou passe como parâmetros.'
                ];
            }

            $response = $this->getClient($apiKey, $secretKey)->get('/sapi/v1/account/apiTradingStatus');

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao obter status de trading: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Configurações de capital (saldos detalhados)
     * GET /api/account/capital-config
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function getCapitalConfig(array $params): array
    {
        try {
            $apiKey = $params['api_key'] ?? Config::getBinanceApiKey();
            $secretKey = $params['secret_key'] ?? Config::getBinanceSecretKey();

            if ($error = Validation::requireFields(['api_key' => $apiKey, 'secret_key' => $secretKey], ['api_key', 'secret_key'])) {
                return [
                    'success' => false,
                    'error' => 'Chaves de API não fornecidas. Configure no .env ou passe como parâmetros.'
                ];
            }

            $response = $this->getClient($apiKey, $secretKey)->get('/sapi/v1/capital/config/getall');

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao obter configurações de capital: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Converte dust em BNB
     * POST /api/account/dust-transfer
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function dustTransfer(array $params): array
    {
        try {
            $apiKey = $params['api_key'] ?? Config::getBinanceApiKey();
            $secretKey = $params['secret_key'] ?? Config::getBinanceSecretKey();

            if ($error = Validation::requireFields(['api_key' => $apiKey, 'secret_key' => $secretKey], ['api_key', 'secret_key'])) {
                return [
                    'success' => false,
                    'error' => 'Chaves de API não fornecidas. Configure no .env ou passe como parâmetros.'
                ];
            }

            $assetsParam = $params['assets'] ?? $params['asset'] ?? null;
            if (empty($assetsParam)) {
                return [
                    'success' => false,
                    'error' => 'Parâmetro "assets" é obrigatório (ex: ["USDT","ETH"])'
                ];
            }

            $assets = is_array($assetsParam)
                ? array_filter(array_map('strtoupper', $assetsParam))
                : array_filter(array_map('strtoupper', explode(',', (string)$assetsParam)));

            if (count($assets) === 0) {
                return [
                    'success' => false,
                    'error' => 'Informe ao menos um asset em "assets"'
                ];
            }

            $response = $this->getClient($apiKey, $secretKey)->post('/sapi/v1/asset/dust', [
                'assets' => json_encode(array_values($assets))
            ]);

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao converter dust: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Consulta dividendos de ativos
     * GET /api/account/asset-dividend
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function assetDividend(array $params): array
    {
        try {
            $apiKey = $params['api_key'] ?? Config::getBinanceApiKey();
            $secretKey = $params['secret_key'] ?? Config::getBinanceSecretKey();

            if ($error = Validation::requireFields(['api_key' => $apiKey, 'secret_key' => $secretKey], ['api_key', 'secret_key'])) {
                return [
                    'success' => false,
                    'error' => 'Chaves de API não fornecidas. Configure no .env ou passe como parâmetros.'
                ];
            }

            $response = $this->getClient($apiKey, $secretKey)->get('/sapi/v1/asset/assetDividend', [
                'asset' => $params['asset'] ?? null,
                'startTime' => $params['startTime'] ?? null,
                'endTime' => $params['endTime'] ?? null,
                'limit' => $params['limit'] ?? 20,
            ]);

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao obter dividendos: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Consulta se um ativo é transferível para Convert
     * GET /api/account/convert-transferable?fromAsset=BTC&toAsset=USDT
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function convertTransferable(array $params): array
    {
        try {
            $apiKey = $params['api_key'] ?? Config::getBinanceApiKey();
            $secretKey = $params['secret_key'] ?? Config::getBinanceSecretKey();

            if ($error = Validation::requireFields(['api_key' => $apiKey, 'secret_key' => $secretKey], ['api_key', 'secret_key'])) {
                return [
                    'success' => false,
                    'error' => 'Chaves de API não fornecidas. Configure no .env ou passe como parâmetros.'
                ];
            }

            if ($error = Validation::requireFields($params, ['fromAsset', 'toAsset'])) {
                return ['success' => false, 'error' => $error];
            }

            $response = $this->getClient($apiKey, $secretKey)->get('/sapi/v1/convert/transferable', [
                'fromAsset' => $params['fromAsset'],
                'toAsset' => $params['toAsset'],
            ]);

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao verificar convert: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Histórico de ordens P2P (C2C)
     * GET /api/account/p2p-orders
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function p2pOrders(array $params): array
    {
        try {
            $apiKey = $params['api_key'] ?? Config::getBinanceApiKey();
            $secretKey = $params['secret_key'] ?? Config::getBinanceSecretKey();

            if ($error = Validation::requireFields(['api_key' => $apiKey, 'secret_key' => $secretKey], ['api_key', 'secret_key'])) {
                return [
                    'success' => false,
                    'error' => 'Chaves de API não fornecidas. Configure no .env ou passe como parâmetros.'
                ];
            }

            $response = $this->getClient($apiKey, $secretKey)->get('/sapi/v1/c2c/orderMatch/listUserOrderHistory', [
                'fiatSymbol' => $params['fiatSymbol'] ?? null,
                'tradeType' => $params['tradeType'] ?? null,
                'startTimestamp' => $params['startTimestamp'] ?? null,
                'endTimestamp' => $params['endTimestamp'] ?? null,
                'page' => $params['page'] ?? 1,
                'rows' => $params['rows'] ?? 20,
            ]);

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao obter ordens P2P: ' . $e->getMessage()
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
