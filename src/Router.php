<?php

namespace BinanceAPI;

use BinanceAPI\Controllers\GeneralController;
use BinanceAPI\Controllers\MarketController;
use BinanceAPI\Controllers\AccountController;
use BinanceAPI\Controllers\TradingController;
use BinanceAPI\Config;
use BinanceAPI\RateLimiter;
use BinanceAPI\Metrics;

class Router
{
    private string $method;
    private string $path;
    /** @var array<string,mixed> */
    private array $params;
    private RateLimiter $rateLimiter;

    /**
     * @param string|null $method Método HTTP (override para testes)
     * @param string|null $path Caminho (override para testes)
     * @param array<string,mixed>|null $params Parâmetros já parseados (override para testes)
     */
    public function __construct(?string $method = null, ?string $path = null, ?array $params = null)
    {
        $this->method = $method ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->path = $path ?? parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $this->params = $params ?? $this->parseParams();
        $this->rateLimiter = new RateLimiter();

        $correlation = $_SERVER['HTTP_X_CORRELATION_ID'] ?? null;
        if ($correlation) {
            Config::setRequestId($correlation);
        }
    }

    /**
     * Parse de parâmetros GET/POST
     *
     * @return array<string,mixed> Parâmetros extraídos
     */
    private function parseParams(): array
    {
        if ($this->method === 'GET') {
            return $this->normalize((array)($_GET ?? []));
        }

        if ($this->method === 'POST' || $this->method === 'DELETE') {
            $input = file_get_contents('php://input');
            $decoded = json_decode($input, true);
            $params = is_array($decoded) ? $decoded : [];
            return $this->normalize($params);
        }

        return [];
    }

    /**
     * Dispatch da requisição para o controller apropriado
     */
    public function dispatch(): void
    {
        if (!$this->checkAuth()) {
            return;
        }

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

        if ($endpoint === 'health') {
            $this->handleHealth();
            return;
        }

        if ($endpoint === 'metrics') {
            $this->handleMetrics();
            return;
        }

        if ($this->isRateLimited($endpoint)) {
            return;
        }

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
     * @param array<string,mixed> $data Dados a enviar
     * @param int|null $code Código HTTP opcional
     */
    private function sendResponse(array $data, ?int $code = null): void
    {
        $httpCode = $code ?? (($data['success'] ?? true) ? 200 : ($data['code'] ?? 400));
        http_response_code($httpCode);
        header('X-Request-Id: ' . Config::getRequestId());
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $this->recordMetrics($httpCode);
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
        header('X-Request-Id: ' . Config::getRequestId());
        echo json_encode([
            'success' => false,
            'error' => $message
        ], JSON_PRETTY_PRINT);
        $this->recordMetrics($code);
    }

    private function checkAuth(): bool
    {
        $user = Config::getAuthUser();
        $pass = Config::getAuthPassword();

        if (!$user || !$pass) {
            return true;
        }

        $inputUser = $_SERVER['PHP_AUTH_USER'] ?? null;
        $inputPass = $_SERVER['PHP_AUTH_PW'] ?? null;

        if ($inputUser === $user && $inputPass === $pass) {
            return true;
        }

        header('WWW-Authenticate: Basic realm="Restricted"');
        $this->sendError('Não autorizado', 401);
        return false;
    }

    private function isRateLimited(?string $endpoint): bool
    {
        $enabled = (bool)Config::get('RATE_LIMIT_ENABLED', false);
        if (!$enabled) {
            return false;
        }

        if (!in_array($endpoint, ['account', 'trading'], true)) {
            return false;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
        $routeKey = $endpoint . ':' . ($this->method ?? 'GET') . ':' . $ip;
        $hit = $this->rateLimiter->hit($routeKey);

        if (!$hit['allowed']) {
            $retry = $hit['retryAfter'] ?? 1;
            header('Retry-After: ' . $retry);
            $this->sendError('Rate limit excedido. Tente novamente em ' . $retry . 's', 429);
            return true;
        }

        return false;
    }

    private function handleHealth(): void
    {
        $storage = Config::getStoragePath('');
        $writable = is_writable(dirname($storage . '/dummy'));
        $this->sendResponse([
            'success' => $writable,
            'storage_writable' => $writable
        ], $writable ? 200 : 500);
    }

    private function handleMetrics(): void
    {
        if (!(bool)Config::get('METRICS_ENABLED', false)) {
            $this->sendError('Metrics disabled', 404);
            return;
        }

        $this->sendResponse(['success' => true, 'data' => Metrics::snapshot()]);
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    private function normalize(array $params): array
    {
        if (isset($params['symbol']) && is_string($params['symbol'])) {
            $params['symbol'] = strtoupper($params['symbol']);
        }
        return $params;
    }

    private function recordMetrics(int $status): void
    {
        if (!(bool)Config::get('METRICS_ENABLED', false)) {
            return;
        }
        $duration = (int)((microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))) * 1000);
        Metrics::record($status, $duration);
    }
}
