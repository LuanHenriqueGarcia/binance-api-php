<?php

namespace BinanceAPI;

use BinanceAPI\Controllers\GeneralController;
use BinanceAPI\Controllers\MarketController;
use BinanceAPI\Controllers\AccountController;
use BinanceAPI\Controllers\TradingController;

class Router
{
    private string $method;
    private string $path;
    private array $params;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $this->params = $this->parseParams();
    }

    /**
     * Parse de parâmetros GET/POST
     *
     * @return array Parâmetros extraídos
     */
    private function parseParams(): array
    {
        $params = [];

        if ($this->method === 'GET') {
            $params = $_GET;
        } elseif ($this->method === 'POST' || $this->method === 'DELETE') {
            $input = file_get_contents('php://input');
            $decoded = json_decode($input, true);
            $params = is_array($decoded) ? $decoded : [];
        }

        return $params;
    }

    /**
     * Dispatch da requisição para o controller apropriado
     */
    public function dispatch(): void
    {
        $pathParts = array_filter(explode('/', $this->path));
        $pathParts = array_values($pathParts);

        // Remover 'api' do início se existir
        if (!empty($pathParts) && $pathParts[0] === 'api') {
            array_shift($pathParts);
        }

        if (empty($pathParts)) {
            $this->sendResponse(['message' => 'Binance API REST - PHP']);
            return;
        }

        $endpoint = $pathParts[0] ?? null;
        $action = $pathParts[1] ?? null;

        match ($endpoint) {
            'general' => $this->handleGeneral($action),
            'market' => $this->handleMarket($action),
            'account' => $this->handleAccount($action),
            'trading' => $this->handleTrading($action),
            default => $this->sendError('Endpoint não encontrado', 404)
        };
    }

    /**
     * Manipular endpoints gerais
     *
     * @param string|null $action Ação a executar
     */
    private function handleGeneral(?string $action): void
    {
        $controller = new GeneralController();

        match ($action) {
            'ping' => $this->sendResponse($controller->ping()),
            'time' => $this->sendResponse($controller->time()),
            'exchange-info' => $this->sendResponse($controller->exchangeInfo($this->params)),
            default => $this->sendError('Ação não encontrada', 404)
        };
    }

    /**
     * Manipular endpoints de market data
     *
     * @param string|null $action Ação a executar
     */
    private function handleMarket(?string $action): void
    {
        $controller = new MarketController();

        match ($action) {
            'ticker' => $this->sendResponse($controller->ticker($this->params)),
            'order-book' => $this->sendResponse($controller->orderBook($this->params)),
            'trades' => $this->sendResponse($controller->trades($this->params)),
            default => $this->sendError('Ação não encontrada', 404)
        };
    }

    /**
     * Manipular endpoints de conta
     *
     * @param string|null $action Ação a executar
     */
    private function handleAccount(?string $action): void
    {
        $controller = new AccountController();

        match ($action) {
            'info' => $this->sendResponse($controller->getAccountInfo($this->params)),
            'open-orders' => $this->sendResponse($controller->getOpenOrders($this->params)),
            'order-history' => $this->sendResponse($controller->getOrderHistory($this->params)),
            'balance' => $this->sendResponse($controller->getAssetBalance($this->params)),
            default => $this->sendError('Ação não encontrada', 404)
        };
    }

    /**
     * Manipular endpoints de trading
     *
     * @param string|null $action Ação a executar
     */
    private function handleTrading(?string $action): void
    {
        $controller = new TradingController();

        match ($action) {
            'create-order' => $this->sendResponse($controller->createOrder($this->params)),
            'cancel-order' => $this->sendResponse($controller->cancelOrder($this->params)),
            default => $this->sendError('Ação não encontrada', 404)
        };
    }

    /**
     * Enviar resposta de sucesso
     *
     * @param array $data Dados a enviar
     */
    private function sendResponse(array $data): void
    {
        http_response_code(200);
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Enviar resposta de erro
     *
     * @param string $message Mensagem de erro
     * @param int $code Código HTTP
     */
    private function sendError(string $message, int $code = 400): void
    {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error' => $message
        ], JSON_PRETTY_PRINT);
    }
}