<?php

namespace TradersApi;

class Config
{
    /** @var array<string,mixed> */
    private static array $config = [];
    private static bool $loaded = false;

    public const DEFAULT_STORAGE = __DIR__ . '/../storage';
    private static ?string $requestId = null;

    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }

        // Carrega apenas o .env real. Sem fallback para .env.example em runtime:
        // isso evitaria que a aplicação subisse com defaults de desenvolvimento
        // (APP_ENV=development, APP_DEBUG=true, SSL desabilitado) sem perceber,
        // caso o deploy esquecesse de provisionar o .env.
        $envFile = __DIR__ . '/../.env';

        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) {
                    continue;
                }

                if (strpos($line, '=') !== false) {
                    [$key, $value] = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);

                    if (preg_match('/^"(.*)"$/', $value, $matches)) {
                        $value = $matches[1];
                    } elseif (preg_match("/^'(.*)'$/", $value, $matches)) {
                        $value = $matches[1];
                    }

                    self::$config[$key] = $value;
                    putenv("$key=$value");
                }
            }
        }

        self::$loaded = true;
    }

    /**
     * Definir valores manualmente (uso em testes)
     *
     * @param array<string,mixed> $values
     */
    public static function fake(array $values): void
    {
        self::$config = $values;
        self::$loaded = true;
    }

    /**
     * Obter valor de configuração
     *
     * @param string $key Chave da configuração
     * @param mixed $default Valor padrão se não encontrado
     * @return mixed Valor da configuração
     */
    public static function get(string $key, $default = null)
    {
        self::load();

        // (config ?? env) ?: default — config tem prioridade, depois variável de
        // ambiente, por fim o default. Parênteses tornam explícita a precedência
        // (?? liga mais forte que ?:). Obs.: valores "falsy" ("0", "") caem para o
        // default — comportamento legado intencional (ex.: SSL_VERIFY=0 => verifica).
        return (self::$config[$key] ?? getenv($key)) ?: $default;
    }

    /**
     * Obter chave de API da Binance
     */
    public static function getBinanceApiKey(): ?string
    {
        return self::get('BINANCE_API_KEY');
    }

    /**
     * Obter chave secreta da Binance
     */
    public static function getBinanceSecretKey(): ?string
    {
        return self::get('BINANCE_SECRET_KEY');
    }

    /**
     * Obter chave de API da Coinbase
     */
    public static function getCoinbaseApiKey(): ?string
    {
        return self::get('COINBASE_API_KEY');
    }

    /**
     * Obter chave secreta da Coinbase (private key PEM)
     */
    public static function getCoinbaseApiSecret(): ?string
    {
        return self::get('COINBASE_API_SECRET');
    }

    /**
     * Obter caminho para JSON de credenciais Coinbase
     */
    public static function getCoinbaseKeyFile(): ?string
    {
        return self::get('COINBASE_KEY_FILE');
    }

    /**
     * Obter base URL da Coinbase
     */
    public static function getCoinbaseBaseUrl(): string
    {
        $envUrl = self::get('COINBASE_BASE_URL');

        if ($envUrl) {
            return rtrim($envUrl, '/');
        }

        return 'https://api.coinbase.com';
    }

    /**
     * Obter caminho para bundle de CA customizado (Coinbase)
     */
    public static function getCoinbaseCaBundle(): ?string
    {
        return self::get('COINBASE_CA_BUNDLE');
    }

    /**
     * Verificar se deve validar SSL (Coinbase)
     */
    public static function shouldVerifyCoinbaseSsl(): bool
    {
        return self::get('COINBASE_SSL_VERIFY', 'true') === 'true';
    }

    public static function getRecvWindow(): int
    {
        return (int) self::get('BINANCE_RECV_WINDOW', 5000);
    }

    /**
     * Obter usuário do Basic Auth (proteção das rotas)
     */
    public static function getAuthUser(): ?string
    {
        return self::get('BASIC_AUTH_USER');
    }

    /**
     * Obter senha do Basic Auth (proteção das rotas)
     */
    public static function getAuthPassword(): ?string
    {
        return self::get('BASIC_AUTH_PASSWORD');
    }

    /**
     * Obter caminho para bundle de CA customizado (SSL)
     */
    public static function getCaBundle(): ?string
    {
        return self::get('BINANCE_CA_BUNDLE');
    }

    /**
     * Verificar se deve validar SSL
     */
    public static function shouldVerifySsl(): bool
    {
        return self::get('BINANCE_SSL_VERIFY', 'true') === 'true';
    }

    /**
     * Verificar se deve usar testnet
     */
    public static function isTestnet(): bool
    {
        return self::get('BINANCE_TESTNET', 'false') === 'true';
    }

    /**
     * Obter base URL da Binance (mainnet/testnet)
     */
    public static function getBinanceBaseUrl(): string
    {
        $envUrl = self::get('BINANCE_BASE_URL');

        if ($envUrl) {
            return rtrim($envUrl, '/');
        }

        if (self::isTestnet()) {
            return 'https://testnet.binance.vision';
        }

        return 'https://api.binance.com';
    }

    /**
     * Verificar se está em modo debug
     */
    public static function isDebug(): bool
    {
        return self::get('APP_DEBUG', 'false') === 'true';
    }

    /**
     * Obter ambiente (development ou production)
     */
    public static function getEnvironment(): string
    {
        return self::get('APP_ENV', 'development');
    }

    public static function getStoragePath(string $subdir): string
    {
        $base = rtrim((string) self::get('STORAGE_PATH', self::DEFAULT_STORAGE), '/');
        return $base . '/' . trim($subdir, '/');
    }

    public static function getRequestId(): string
    {
        if (self::$requestId === null) {
            self::$requestId = bin2hex(random_bytes(8));
        }
        return self::$requestId;
    }

    public static function setRequestId(?string $id): void
    {
        if ($id && preg_match('/^[A-Za-z0-9\-_.]{6,64}$/', $id)) {
            self::$requestId = $id;
        }
    }
}
