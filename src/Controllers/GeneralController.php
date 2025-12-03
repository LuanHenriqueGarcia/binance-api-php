<?php

namespace BinanceAPI\Controllers;

use BinanceAPI\BinanceClient;
use BinanceAPI\Validation;

class GeneralController
{
    /**
     * Testa conectividade com a API Binance
     * GET /api/general/ping
     *
     * @return array<string,mixed> Resposta da API
     */
    public function ping(): array
    {
        try {
            $client = new BinanceClient();
            $response = $client->get('/api/v3/ping');

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
            $client = new BinanceClient();
            $response = $client->get('/api/v3/time');

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
            $client = new BinanceClient();

            $options = [];
            $symbol = $params['symbol'] ?? null;
            $symbols = $params['symbols'] ?? null;
            if ($symbol) {
                $options['symbol'] = $symbol;
            } elseif ($symbols) {
                $options['symbols'] = $symbols;
            }

            $response = $client->get('/api/v3/exchangeInfo', $options);

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
