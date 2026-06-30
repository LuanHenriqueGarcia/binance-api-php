<?php

use TradersApi\Router;
use TradersApi\Config;
use PHPUnit\Framework\TestCase;

/**
 * Preenche lacunas de cobertura do Router:
 *  - parseParams() para POST/DELETE (lê php://input)
 *  - ramo OPTIONS no dispatch()
 *  - roteamento Coinbase (general/market/account/trading)
 *  - isRateLimited() para ação Coinbase não protegida
 *  - normalize() de product_id / product_ids
 *  - resolveAllowedOrigin() (wildcard e match exato)
 *
 * Endpoints Coinbase públicos usam uma base URL inalcançável para que a
 * chamada de rede falhe imediatamente (connection refused) sem acessar a internet.
 */
class CoverageRouterTest extends TestCase
{
    /** @var array<string,mixed> */
    private array $serverBackup;

    protected function setUp(): void
    {
        Config::fake([]);
        $this->serverBackup = $_SERVER;
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        $_GET = [];
    }

    private function fakeUnreachableCoinbase(): void
    {
        Config::fake([
            'COINBASE_BASE_URL' => 'http://127.0.0.1:9',
            'COINBASE_SSL_VERIFY' => 'false',
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function params(Router $router): array
    {
        $prop = new ReflectionProperty(Router::class, 'params');
        $prop->setAccessible(true);
        return $prop->getValue($router);
    }

    private function dispatchToString(Router $router): string
    {
        ob_start();
        $router->dispatch();
        return (string) ob_get_clean();
    }

    // ---------- parseParams POST/DELETE (php://input) ----------

    public function testParseParamsPostReadsPhpInput(): void
    {
        $router = new Router('POST', '/api/test', null);

        // php://input é vazio em CLI -> json_decode(null) -> []
        $this->assertSame([], $this->params($router));
    }

    public function testParseParamsDeleteReadsPhpInput(): void
    {
        $router = new Router('DELETE', '/api/test', null);

        $this->assertSame([], $this->params($router));
    }

    // ---------- dispatch OPTIONS ----------

    public function testDispatchOptionsReturns204(): void
    {
        $router = new Router('OPTIONS', '/api/general/ping', []);

        $output = $this->dispatchToString($router);

        $this->assertSame(204, http_response_code());
        $this->assertSame('', $output);
    }

    // ---------- Coinbase general (ping/time) ----------

    public function testDispatchCoinbaseGeneralPing(): void
    {
        $this->fakeUnreachableCoinbase();
        $router = new Router('GET', '/api/coinbase/general/ping', []);

        $decoded = json_decode($this->dispatchToString($router), true);

        $this->assertIsArray($decoded);
    }

    public function testDispatchCoinbaseGeneralTime(): void
    {
        $this->fakeUnreachableCoinbase();
        $router = new Router('GET', '/api/coinbase/general/time', []);

        $decoded = json_decode($this->dispatchToString($router), true);

        $this->assertIsArray($decoded);
    }

    // ---------- Coinbase market (products/product-book/ticker/candles) ----------

    public function testDispatchCoinbaseMarketProducts(): void
    {
        $this->fakeUnreachableCoinbase();
        $router = new Router('GET', '/api/coinbase/market/products', []);

        $decoded = json_decode($this->dispatchToString($router), true);

        $this->assertIsArray($decoded);
    }

    public function testDispatchCoinbaseMarketProductBook(): void
    {
        $this->fakeUnreachableCoinbase();
        $router = new Router('GET', '/api/coinbase/market/product-book', []);

        $decoded = json_decode($this->dispatchToString($router), true);

        $this->assertIsArray($decoded);
    }

    public function testDispatchCoinbaseMarketTicker(): void
    {
        $this->fakeUnreachableCoinbase();
        $router = new Router('GET', '/api/coinbase/market/ticker', []);

        $decoded = json_decode($this->dispatchToString($router), true);

        $this->assertIsArray($decoded);
    }

    public function testDispatchCoinbaseMarketCandles(): void
    {
        $this->fakeUnreachableCoinbase();
        $router = new Router('GET', '/api/coinbase/market/candles', []);

        $decoded = json_decode($this->dispatchToString($router), true);

        $this->assertIsArray($decoded);
    }

    // ---------- Coinbase account (accounts) — sem credenciais, sem rede ----------

    public function testDispatchCoinbaseAccountAccounts(): void
    {
        $router = new Router('GET', '/api/coinbase/account/accounts', []);

        $decoded = json_decode($this->dispatchToString($router), true);

        $this->assertIsArray($decoded);
        $this->assertFalse($decoded['success']);
    }

    // ---------- Coinbase trading (cancel-order/get-order/list-orders) — sem credenciais ----------

    public function testDispatchCoinbaseTradingCancelOrder(): void
    {
        $router = new Router('POST', '/api/coinbase/trading/cancel-order', []);

        $decoded = json_decode($this->dispatchToString($router), true);

        $this->assertIsArray($decoded);
        $this->assertFalse($decoded['success']);
    }

    public function testDispatchCoinbaseTradingGetOrder(): void
    {
        $router = new Router('GET', '/api/coinbase/trading/get-order', []);

        $decoded = json_decode($this->dispatchToString($router), true);

        $this->assertIsArray($decoded);
        $this->assertFalse($decoded['success']);
    }

    public function testDispatchCoinbaseTradingListOrders(): void
    {
        $router = new Router('GET', '/api/coinbase/trading/list-orders', []);

        $decoded = json_decode($this->dispatchToString($router), true);

        $this->assertIsArray($decoded);
        $this->assertFalse($decoded['success']);
    }

    // ---------- isRateLimited: Coinbase com ação não protegida ----------

    public function testIsRateLimitedCoinbaseNonProtectedActionReturnsFalse(): void
    {
        Config::fake(['RATE_LIMIT_ENABLED' => 'true']);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $router = new Router('GET', '/api/coinbase/market/products', []);
        $method = new ReflectionMethod(Router::class, 'isRateLimited');
        $method->setAccessible(true);

        // 'market' não está em ['account','trading'] -> retorna false
        $this->assertFalse($method->invoke($router, 'coinbase', 'market'));
    }

    // ---------- normalize: product_id / product_ids ----------

    public function testNormalizeUppercasesProductId(): void
    {
        $_GET = ['product_id' => 'btc-usd'];
        $router = new Router('GET', '/', null);

        $this->assertSame('BTC-USD', $this->params($router)['product_id']);
    }

    public function testNormalizeUppercasesProductIdsArray(): void
    {
        $_GET = ['product_ids' => ['btc-usd', 'eth-usd', 123]];
        $router = new Router('GET', '/', null);

        $params = $this->params($router);
        $this->assertSame(['BTC-USD', 'ETH-USD', 123], $params['product_ids']);
    }

    public function testNormalizeUppercasesProductIdsString(): void
    {
        $_GET = ['product_ids' => 'btc-usd'];
        $router = new Router('GET', '/', null);

        $this->assertSame('BTC-USD', $this->params($router)['product_ids']);
    }

    // ---------- resolveAllowedOrigin ----------

    public function testResolveAllowedOriginWildcard(): void
    {
        $router = new Router('GET', '/', []);
        $method = new ReflectionMethod(Router::class, 'resolveAllowedOrigin');
        $method->setAccessible(true);

        $this->assertSame('*', $method->invoke($router, 'http://example.com', ['*']));
    }

    public function testResolveAllowedOriginExactMatch(): void
    {
        $router = new Router('GET', '/', []);
        $method = new ReflectionMethod(Router::class, 'resolveAllowedOrigin');
        $method->setAccessible(true);

        $allowed = ['http://localhost:3000', 'http://example.com'];
        $this->assertSame(
            'http://example.com',
            $method->invoke($router, 'http://example.com', $allowed)
        );
    }

    // ---------- Auth: fail-closed em produção sem Basic Auth ----------

    public function testProductionWithoutBasicAuthIsBlocked(): void
    {
        Config::fake(['APP_ENV' => 'production']);
        $router = new Router('GET', '/', []);

        $output = $this->dispatchToString($router);

        $this->assertSame(503, http_response_code());
        $decoded = json_decode($output, true);
        $this->assertFalse($decoded['success']);
        $this->assertStringContainsString('Autenticação obrigatória', $decoded['error']);
    }

    public function testProductionWithoutBasicAuthAllowedByExplicitFlag(): void
    {
        Config::fake(['APP_ENV' => 'production', 'ALLOW_UNAUTHENTICATED' => 'true']);
        $router = new Router('GET', '/', []);

        $output = $this->dispatchToString($router);

        $this->assertSame(200, http_response_code());
        $this->assertStringContainsString('Traders API REST', $output);
    }

    public function testDevelopmentWithoutBasicAuthIsAllowed(): void
    {
        Config::fake(['APP_ENV' => 'development']);
        $router = new Router('GET', '/', []);

        $output = $this->dispatchToString($router);

        $this->assertSame(200, http_response_code());
        $this->assertStringContainsString('Traders API REST', $output);
    }

    // ---------- Credenciais via cabeçalhos HTTP ----------

    public function testInjectCredentialHeadersFromHttpHeaders(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = 'pub-key';
        $_SERVER['HTTP_X_API_SECRET'] = 'sec-key';

        $router = new Router('GET', '/api/account/info', []);
        $params = $this->params($router);

        $this->assertSame('pub-key', $params['api_key']);
        $this->assertSame('sec-key', $params['secret_key']);
        $this->assertSame('sec-key', $params['api_secret']);
    }

    public function testInjectCredentialHeadersDoesNotOverrideExplicitParams(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = 'header-key';
        $_SERVER['HTTP_X_API_SECRET'] = 'header-secret';

        $router = new Router('POST', '/api/trading/create-order', [
            'api_key' => 'body-key',
            'secret_key' => 'body-secret',
        ]);
        $params = $this->params($router);

        $this->assertSame('body-key', $params['api_key']);
        $this->assertSame('body-secret', $params['secret_key']);
        $this->assertSame('header-secret', $params['api_secret']);
    }

    public function testInjectCredentialHeadersIgnoresEmptyHeader(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = '';

        $router = new Router('GET', '/api/account/info', []);
        $params = $this->params($router);

        $this->assertArrayNotHasKey('api_key', $params);
    }

    // ---------- Health é público (sem Basic Auth) ----------

    public function testHealthIsPublicEvenWithBasicAuthConfigured(): void
    {
        Config::fake([
            'BASIC_AUTH_USER' => 'u',
            'BASIC_AUTH_PASSWORD' => 'p',
            'STORAGE_PATH' => sys_get_temp_dir(),
        ]);

        $router = new Router('GET', '/api/health', []);
        $decoded = json_decode($this->dispatchToString($router), true);

        $this->assertNotSame(401, http_response_code());
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('storage_writable', $decoded);
    }
}
