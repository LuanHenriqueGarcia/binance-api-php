<?php

namespace BinanceAPI;

class Config
{
    private static array $config = [];
    private static bool $loaded = false;

    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }

        $envFile = __DIR__ . '/../.env';

        if (!file_exists($envFile)) {
            $envFile = __DIR__ . '/../.env.example';
        }

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
     * Obter valor de configuração
     *
     * @param string $key Chave da configuração
     * @param mixed $default Valor padrão se não encontrado
     * @return mixed Valor da configuração
     */
    public static function get(string $key, $default = null)
    {
        self::load();

        return self::$config[$key] ?? getenv($key) ?: $default;
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
}