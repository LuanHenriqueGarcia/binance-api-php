<?php

namespace TradersApi\Controllers;

use TradersApi\CoinbaseClient;
use TradersApi\Config;
use TradersApi\Contracts\ClientInterface;
use TradersApi\Validation;

class CoinbaseAccountController
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
     * Lista contas do usuário
     * GET /api/coinbase/account/accounts
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function accounts(array $params): array
    {
        try {
            [$apiKey, $secretKey, $keyFile] = $this->resolveCredentials($params);
            if ($error = $this->validateCredentials($apiKey, $secretKey, $keyFile)) {
                return ['success' => false, 'error' => $error];
            }

            $response = $this->getClient($apiKey, $secretKey, $keyFile)->get('/api/v3/brokerage/accounts', [
                'limit' => $params['limit'] ?? null,
                'cursor' => $params['cursor'] ?? null,
                'retail_portfolio_id' => $params['retail_portfolio_id'] ?? null,
            ]);

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao listar contas: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Detalha uma conta por UUID
     * GET /api/coinbase/account/account?account_uuid=...
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function account(array $params): array
    {
        try {
            [$apiKey, $secretKey, $keyFile] = $this->resolveCredentials($params);
            if ($error = $this->validateCredentials($apiKey, $secretKey, $keyFile)) {
                return ['success' => false, 'error' => $error];
            }

            $accountId = $params['account_uuid'] ?? $params['account_id'] ?? null;
            if ($error = Validation::requireFields(['account_uuid' => $accountId], ['account_uuid'])) {
                return ['success' => false, 'error' => $error];
            }

            $response = $this->getClient($apiKey, $secretKey, $keyFile)->get('/api/v3/brokerage/accounts/' . rawurlencode((string) $accountId));

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Falha ao obter conta: ' . $e->getMessage()
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
}
