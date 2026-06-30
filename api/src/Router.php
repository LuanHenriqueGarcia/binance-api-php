<?php

namespace TradersApi;

use TradersApi\Controllers\GeneralController;
use TradersApi\Controllers\MarketController;
use TradersApi\Controllers\AccountController;
use TradersApi\Controllers\TradingController;
use TradersApi\Controllers\CoinbaseGeneralController;
use TradersApi\Controllers\CoinbaseMarketController;
use TradersApi\Controllers\CoinbaseAccountController;
use TradersApi\Controllers\CoinbaseTradingController;
use TradersApi\Config;
use TradersApi\RateLimiter;
use TradersApi\Metrics;

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
        $this->path = $path ?? (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
        $this->params = $params ?? $this->parseParams();
        $this->injectCredentialHeaders();
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
            return $this->normalize($_GET);
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
     * Permite enviar credenciais de exchange via cabeçalhos HTTP
     * (X-API-Key / X-API-Secret) em vez de query string. Cabeçalhos não
     * vazam para access logs, histórico de navegador ou Referer como a URL.
     * O ideal continua sendo configurar as chaves no servidor (.env).
     */
    private function injectCredentialHeaders(): void
    {
        $key = $_SERVER['HTTP_X_API_KEY'] ?? null;
        $secret = $_SERVER['HTTP_X_API_SECRET'] ?? null;

        if (is_string($key) && $key !== '' && !isset($this->params['api_key'])) {
            $this->params['api_key'] = $key;
        }

        if (is_string($secret) && $secret !== '') {
            // Binance usa "secret_key"; Coinbase usa "api_secret".
            $this->params['secret_key'] ??= $secret;
            $this->params['api_secret'] ??= $secret;
        }
    }

    /**
     * Dispatch da requisição para o controller apropriado
     */
    public function dispatch(): void
    {
        $this->applyCorsHeaders();

        if ($this->method === 'OPTIONS') {
            http_response_code(204);
            header('X-Request-Id: ' . Config::getRequestId());
            return;
        }

        $pathParts = array_values(array_filter(explode('/', $this->path)));

        if (!empty($pathParts) && $pathParts[0] === 'api') {
            array_shift($pathParts);
        }

        $endpoint = $pathParts[0] ?? null;
        $action = $pathParts[1] ?? null;
        $subAction = $pathParts[2] ?? null;

        if ($endpoint === 'health') {
            $this->handleHealth();
            return;
        }

        if (!$this->checkAuth()) {
            return;
        }

        if (empty($pathParts)) {
            $this->sendResponse(['message' => 'Traders API REST - PHP']);
            return;
        }

        if ($endpoint === 'metrics') {
            $this->handleMetrics();
            return;
        }

        if ($this->isRateLimited($endpoint, $action)) {
            return;
        }

        if ($endpoint === 'coinbase') {
            $this->handleCoinbase($action, $subAction);
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
     * Manipular endpoints Coinbase
     *
     * @param string|null $section Seção (general/market/account/trading)
     * @param string|null $action Ação a executar
     */
    private function handleCoinbase(?string $section, ?string $action): void
    {
        match ($section) {
            'general' => $this->handleCoinbaseGeneral($action),
            'market' => $this->handleCoinbaseMarket($action),
            'account' => $this->handleCoinbaseAccount($action),
            'trading' => $this->handleCoinbaseTrading($action),
            default => $this->sendError('Ação não encontrada', 404)
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
            'avg-price' => $this->sendResponse($controller->avgPrice($this->params)),
            'book-ticker' => $this->sendResponse($controller->bookTicker($this->params)),
            'agg-trades' => $this->sendResponse($controller->aggTrades($this->params)),
            'klines' => $this->sendResponse($controller->klines($this->params)),
            'ui-klines' => $this->sendResponse($controller->uiKlines($this->params)),
            'historical-trades' => $this->sendResponse($controller->historicalTrades($this->params)),
            'rolling-window-ticker' => $this->sendResponse($controller->rollingWindowTicker($this->params)),
            'ticker-price' => $this->sendResponse($controller->tickerPrice($this->params)),
            'ticker-24h' => $this->sendResponse($controller->ticker24h($this->params)),
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
            'my-trades' => $this->sendResponse($controller->getMyTrades($this->params)),
            'account-status' => $this->sendResponse($controller->getAccountStatus($this->params)),
            'api-trading-status' => $this->sendResponse($controller->getApiTradingStatus($this->params)),
            'capital-config' => $this->sendResponse($controller->getCapitalConfig($this->params)),
            'dust-transfer' => $this->sendResponse($controller->dustTransfer($this->params)),
            'asset-dividend' => $this->sendResponse($controller->assetDividend($this->params)),
            'convert-transferable' => $this->sendResponse($controller->convertTransferable($this->params)),
            'p2p-orders' => $this->sendResponse($controller->p2pOrders($this->params)),
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
            'test-order' => $this->sendResponse($controller->testOrder($this->params)),
            'query-order' => $this->sendResponse($controller->queryOrder($this->params)),
            'cancel-open-orders' => $this->sendResponse($controller->cancelOpenOrders($this->params)),
            'create-oco' => $this->sendResponse($controller->createOco($this->params)),
            'list-oco' => $this->sendResponse($controller->listOco($this->params)),
            'cancel-oco' => $this->sendResponse($controller->cancelOco($this->params)),
            'order-rate-limit' => $this->sendResponse($controller->orderRateLimit($this->params)),
            'commission-rate' => $this->sendResponse($controller->commissionRate($this->params)),
            'cancel-replace' => $this->sendResponse($controller->cancelReplace($this->params)),
            default => $this->sendError('Ação não encontrada', 404)
        };
    }

    /**
     * Manipular endpoints gerais Coinbase
     *
     * @param string|null $action
     */
    private function handleCoinbaseGeneral(?string $action): void
    {
        $controller = new CoinbaseGeneralController();

        match ($action) {
            'ping' => $this->sendResponse($controller->ping()),
            'time' => $this->sendResponse($controller->time()),
            default => $this->sendError('Ação não encontrada', 404)
        };
    }

    /**
     * Manipular endpoints de market data Coinbase
     *
     * @param string|null $action
     */
    private function handleCoinbaseMarket(?string $action): void
    {
        $controller = new CoinbaseMarketController();

        match ($action) {
            'products' => $this->sendResponse($controller->products($this->params)),
            'product' => $this->sendResponse($controller->product($this->params)),
            'product-book' => $this->sendResponse($controller->productBook($this->params)),
            'ticker' => $this->sendResponse($controller->ticker($this->params)),
            'candles' => $this->sendResponse($controller->candles($this->params)),
            default => $this->sendError('Ação não encontrada', 404)
        };
    }

    /**
     * Manipular endpoints de conta Coinbase
     *
     * @param string|null $action
     */
    private function handleCoinbaseAccount(?string $action): void
    {
        $controller = new CoinbaseAccountController();

        match ($action) {
            'accounts' => $this->sendResponse($controller->accounts($this->params)),
            'account' => $this->sendResponse($controller->account($this->params)),
            default => $this->sendError('Ação não encontrada', 404)
        };
    }

    /**
     * Manipular endpoints de trading Coinbase
     *
     * @param string|null $action
     */
    private function handleCoinbaseTrading(?string $action): void
    {
        $controller = new CoinbaseTradingController();

        match ($action) {
            'create-order' => $this->sendResponse($controller->createOrder($this->params)),
            'cancel-order' => $this->sendResponse($controller->cancelOrder($this->params)),
            'get-order' => $this->sendResponse($controller->getOrder($this->params)),
            'list-orders' => $this->sendResponse($controller->listOrders($this->params)),
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
        $isSuccess = array_key_exists('success', $data) ? (bool)$data['success'] : true;
        $fallbackCode = $isSuccess ? 200 : (int)($data['code'] ?? 400);
        $httpCode = $code ?? $fallbackCode;
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
            // Sem Basic Auth configurado: a API exporia conta/trading com as
            // credenciais de exchange do servidor. Em produção, falha fechada,
            // a menos que ALLOW_UNAUTHENTICATED=true seja definido explicitamente.
            $isProduction = Config::getEnvironment() === 'production';
            $allowOpen = Config::get('ALLOW_UNAUTHENTICATED', 'false') === 'true';

            if ($isProduction && !$allowOpen) {
                $this->sendError(
                    'Autenticação obrigatória não configurada. Defina BASIC_AUTH_USER e '
                    . 'BASIC_AUTH_PASSWORD (ou ALLOW_UNAUTHENTICATED=true para liberar conscientemente).',
                    503
                );
                return false;
            }

            return true;
        }

        $inputUser = (string) ($_SERVER['PHP_AUTH_USER'] ?? '');
        $inputPass = (string) ($_SERVER['PHP_AUTH_PW'] ?? '');
        
        $userOk = hash_equals($user, $inputUser);
        $passOk = hash_equals($pass, $inputPass);

        if ($userOk && $passOk) {
            return true;
        }

        header('WWW-Authenticate: Basic realm="Restricted"');
        $this->sendError('Não autorizado', 401);
        return false;
    }

    private function isRateLimited(?string $endpoint, ?string $action = null): bool
    {
        $enabled = (bool)Config::get('RATE_LIMIT_ENABLED', false);
        if (!$enabled) {
            return false;
        }

        if (!in_array($endpoint, ['account', 'trading', 'coinbase'], true)) {
            return false;
        }

        if ($endpoint === 'coinbase' && !in_array($action, ['account', 'trading'], true)) {
            return false;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
        // No ramo coinbase, a checagem acima garante $action em ['account','trading'].
        $prefix = $endpoint === 'coinbase' ? 'coinbase:' . $action : $endpoint;
        $routeKey = $prefix . ':' . $this->method . ':' . $ip;
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
        if (isset($params['product_id']) && is_string($params['product_id'])) {
            $params['product_id'] = strtoupper($params['product_id']);
        }
        if (isset($params['product_ids']) && is_array($params['product_ids'])) {
            $params['product_ids'] = array_map(function ($value) {
                return is_string($value) ? strtoupper($value) : $value;
            }, $params['product_ids']);
        } elseif (isset($params['product_ids']) && is_string($params['product_ids'])) {
            $params['product_ids'] = strtoupper($params['product_ids']);
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

    private function applyCorsHeaders(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowedOrigins = $this->parseCsv((string) Config::get('CORS_ALLOWED_ORIGINS', 'http://localhost:3000'));
        $allowedHeaders = implode(', ', $this->parseCsv(
            (string) Config::get('CORS_ALLOWED_HEADERS', 'Authorization, Content-Type, X-Correlation-Id')
        ));
        $allowedMethods = implode(', ', $this->parseCsv(
            (string) Config::get('CORS_ALLOWED_METHODS', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
        ));

        $allowOrigin = $this->resolveAllowedOrigin($origin, $allowedOrigins);

        header('Access-Control-Allow-Origin: ' . $allowOrigin);
        header('Vary: Origin');
        header('Access-Control-Allow-Methods: ' . $allowedMethods);
        header('Access-Control-Allow-Headers: ' . $allowedHeaders);
        header('Access-Control-Max-Age: 86400');
    }

    /**
     * @param array<int,string> $allowedOrigins
     */
    private function resolveAllowedOrigin(string $origin, array $allowedOrigins): string
    {
        if (in_array('*', $allowedOrigins, true)) {
            return '*';
        }

        if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
            return $origin;
        }

        return $allowedOrigins[0] ?? 'http://localhost:3000';
    }

    /**
     * @return array<int,string>
     */
    private function parseCsv(string $value): array
    {
        $items = array_map('trim', explode(',', $value));
        return array_values(array_filter($items, static fn (string $item): bool => $item !== ''));
    }
}
