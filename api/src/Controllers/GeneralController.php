<?php

namespace TradersApi\Controllers;

use TradersApi\BinanceClient;
use TradersApi\Contracts\ClientInterface;
use TradersApi\Validation;
use TradersApi\Cache;
use TradersApi\Config;

class GeneralController
{
    private ?ClientInterface $client;
    private ?Cache $cache;

    public function __construct(?ClientInterface $client = null, ?Cache $cache = null)
    {
        $this->client = $client;
        $this->cache = $cache;
    }

    private function getClient(): ClientInterface
    {
        return $this->client ?? new BinanceClient();
    }

    private function getCache(): Cache
    {
        return $this->cache ?? new Cache();
    }

    /**
     * Testa conectividade com a API Binance
     * GET /api/general/ping
     *
     * @return array<string,mixed> Resposta da API
     */
    public function ping(): array
    {
        try {
            $response = $this->getClient()->get('/api/v3/ping');

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao conectar com Binance: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtém a hora atual do servidor Binance
     * GET /api/general/time
     *
     * @return array<string,mixed> Resposta da API
     */
    public function time(): array
    {
        try {
            $response = $this->getClient()->get('/api/v3/time');

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao obter hora do servidor: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtém informações de câmbio e símbolos disponíveis
     * GET /api/general/exchange-info?symbol=BTCUSDT
     *
     * @param array<string,mixed> $params Parâmetros da requisição
     * @return array<string,mixed> Resposta da API
     */
    public function exchangeInfo(array $params): array
    {
        try {
            $cache = $this->getCache();
            $ttl = (int)Config::get('CACHE_EXCHANGEINFO_TTL', 30);
            $cacheKey = 'exchangeInfo:' . md5(json_encode($params));

            $noCache = isset($params['noCache']) && ($params['noCache'] === true || $params['noCache'] === 'true' || $params['noCache'] === 1 || $params['noCache'] === '1');
            if (!$noCache && ($cached = $cache->get($cacheKey, $ttl))) {
                return [
                    'success' => true,
                    'data' => $cached,
                    'cached' => true
                ];
            }

            $options = [];
            $symbol = $params['symbol'] ?? null;
            $symbols = $params['symbols'] ?? null;
            $permissions = $params['permissions'] ?? $params['market'] ?? null;
            if ($symbol) {
                $options['symbol'] = $symbol;
            } elseif ($symbols) {
                $options['symbols'] = $symbols;
            }

            if ($permissions) {
                $options['permissions'] = is_array($permissions)
                    ? array_map('strtoupper', $permissions)
                    : strtoupper((string)$permissions);
            }

            $response = $this->getClient()->get('/api/v3/exchangeInfo', $options);

            if (!isset($response['success']) || $response['success'] !== false) {
                $cache->set($cacheKey, $response);
            }

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao obter informações de câmbio: ' . $e->getMessage()
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
