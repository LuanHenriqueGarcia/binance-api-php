<?php

namespace BinanceAPI\Controllers;

use BinanceAPI\BinanceClient;

class GeneralController
{
    /**
     * Testa conectividade com a API Binance
     * GET /api/general/ping
     *
     * @return array Resposta da API
     */
    public function ping(): array
    {
        try {
            $client = new BinanceClient();
            $response = $client->get('/api/v3/ping');

            return [
                'success' => true,
                'data' => $response
            ];
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
     * @return array Resposta da API
     */
    public function time(): array
    {
        try {
            $client = new BinanceClient();
            $response = $client->get('/api/v3/time');

            return [
                'success' => true,
                'data' => $response
            ];
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
     * @param array $params Parâmetros da requisição
     * @return array Resposta da API
     */
    public function exchangeInfo(array $params): array
    {
        try {
            $client = new BinanceClient();

            $options = [];
            if (!empty($params['symbol'])) {
                $options['symbol'] = $params['symbol'];
            } elseif (!empty($params['symbols'])) {
                $options['symbols'] = $params['symbols'];
            }

            $response = $client->get('/api/v3/exchangeInfo', $options);

            return [
                'success' => true,
                'data' => $response
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao obter informações de câmbio: ' . $e->getMessage()
            ];
        }
    }
}