<?php

namespace BinanceAPI\Controllers;

use BinanceAPI\BinanceClient;
use BinanceAPI\Config;
use BinanceAPI\Validation;

class AccountController
{
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
            // Tentar usar chaves do .env primeiro, depois dos parâmetros
            $apiKey = $params['api_key'] ?? Config::getBinanceApiKey();
            $secretKey = $params['secret_key'] ?? Config::getBinanceSecretKey();

            if ($error = Validation::requireFields(['api_key' => $apiKey, 'secret_key' => $secretKey], ['api_key', 'secret_key'])) {
                return [
                    'success' => false,
                    'error' => 'Chaves de API não fornecidas. Configure no .env ou passe como parâmetros.'
                ];
            }

            $client = new BinanceClient($apiKey, $secretKey);
            $response = $client->get('/api/v3/account');

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
            // Tentar usar chaves do .env primeiro, depois dos parâmetros
            $apiKey = $params['api_key'] ?? Config::getBinanceApiKey();
            $secretKey = $params['secret_key'] ?? Config::getBinanceSecretKey();

            if ($error = Validation::requireFields(['api_key' => $apiKey, 'secret_key' => $secretKey], ['api_key', 'secret_key'])) {
                return [
                    'success' => false,
                    'error' => 'Chaves de API não fornecidas. Configure no .env ou passe como parâmetros.'
                ];
            }

            $client = new BinanceClient($apiKey, $secretKey);

            $options = [];
            if (!empty($params['symbol'])) {
                $options['symbol'] = $params['symbol'];
            }

            $response = $client->get('/api/v3/openOrders', $options);

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
            // Tentar usar chaves do .env primeiro, depois dos parâmetros
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

            $client = new BinanceClient($apiKey, $secretKey);
            $limit = $params['limit'] ?? 500;

            $response = $client->get('/api/v3/allOrders', [
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

            $client = new BinanceClient($apiKey, $secretKey);
            $response = $client->get('/api/v3/account');

            if (isset($response['success']) && $response['success'] === false) {
                return $response;
            }

            // Procurar o ativo
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
