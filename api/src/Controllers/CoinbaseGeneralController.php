<?php

namespace TradersApi\Controllers;

use TradersApi\CoinbaseClient;
use TradersApi\Contracts\ClientInterface;

class CoinbaseGeneralController
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
     * Testa conectividade com a API Coinbase
     * GET /api/coinbase/general/ping
     *
     * @return array<string,mixed>
     */
    public function ping(): array
    {
        return $this->time();
    }

    /**
     * Obtém a hora atual do servidor Coinbase
     * GET /api/coinbase/general/time
     *
     * @return array<string,mixed>
     */
    public function time(): array
    {
        try {
            $response = $this->getClient()->get('/api/v3/brokerage/time');

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao obter hora do servidor Coinbase: ' . $e->getMessage()
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
