<?php

namespace TradersApi;

class Logger
{
    /**
     * @param array<string,mixed> $context
     */
    public static function info(array $context): void
    {
        if (!Config::isDebug()) {
            return;
        }

        self::write($context, 'info');
    }

    /**
     * @param array<string,mixed> $context
     */
    public static function error(array $context): void
    {
        self::write($context, 'error');
    }

    /**
     * @param array<string,mixed> $context
     */
    private static function write(array $context, string $level): void
    {
        $payload = array_merge(self::mask($context), [
            'level' => $level,
            'ts' => date('c')
        ]);

        $line = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $logFile = Config::get('APP_LOG_FILE');

        if ($logFile) {
            $dir = dirname($logFile);
            if (!is_dir($dir)) {
                @mkdir($dir, 0750, true);
            }
            file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND);
            return;
        }

        error_log($line);
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private static function mask(array $context): array
    {
        $sensitiveKeys = ['api_key', 'secret_key', 'secretkey', 'apikey', 'x-mbx-apikey', 'api_secret', 'password', 'authorization'];

        $masked = [];
        foreach ($context as $key => $value) {
            if (is_string($value) && in_array(strtolower((string) $key), $sensitiveKeys, true)) {
                $masked[$key] = substr($value, 0, 4) . '****';
            } else {
                $masked[$key] = $value;
            }
        }

        return $masked;
    }
}
