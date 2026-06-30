<?php

namespace TradersApi\Contracts;

/**
 * Interface para o cliente HTTP da Binance
 */
interface ClientInterface
{
    /**
     * Executa uma requisição GET
     *
     * @param string $endpoint Endpoint da API
     * @param array<string,mixed> $params Parâmetros da requisição
     * @return array<string,mixed> Resposta decodificada
     */
    public function get(string $endpoint, array $params = []): array;

    /**
     * Executa uma requisição POST autenticada
     *
     * @param string $endpoint Endpoint da API
     * @param array<string,mixed> $params Parâmetros da requisição
     * @return array<string,mixed> Resposta decodificada
     */
    public function post(string $endpoint, array $params = []): array;

    /**
     * Executa uma requisição DELETE autenticada
     *
     * @param string $endpoint Endpoint da API
     * @param array<string,mixed> $params Parâmetros da requisição
     * @return array<string,mixed> Resposta decodificada
     */
    public function delete(string $endpoint, array $params = []): array;
}
