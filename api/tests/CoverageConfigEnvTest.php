<?php

use TradersApi\Config;
use PHPUnit\Framework\TestCase;

/**
 * Cobre o parsing de arquivo .env em Config::load(),
 * incluindo remoção de aspas duplas/simples e linhas sem '='.
 */
class CoverageConfigEnvTest extends TestCase
{
    public function testLoadParsesQuotedAndPlainValues(): void
    {
        $loaded = new ReflectionProperty(Config::class, 'loaded');
        $loaded->setAccessible(true);
        $config = new ReflectionProperty(Config::class, 'config');
        $config->setAccessible(true);

        $originalLoaded = $loaded->getValue();
        $originalConfig = $config->getValue();
    
        // Config::load() lê de __DIR__/../.env (= api/.env) com fallback para .env.example
        $envPath = __DIR__ . '/../.env';
        $backup = $envPath . '.coverage_backup';
        $hadEnv = file_exists($envPath);
        if ($hadEnv) {
            rename($envPath, $backup);
        }

        $contents = implode("\n", [
            '# linha de comentario ignorada',
            'COV_DOUBLE="double-quoted"',
            "COV_SINGLE='single-quoted'",
            'COV_PLAIN=plain-value',
            'COV_NO_EQUALS_LINE',
            '',
        ]);
        file_put_contents($envPath, $contents);

        try {
            $loaded->setValue(null, false);
            $config->setValue(null, []);

            Config::load();

            $this->assertSame('double-quoted', Config::get('COV_DOUBLE'));
            $this->assertSame('single-quoted', Config::get('COV_SINGLE'));
            $this->assertSame('plain-value', Config::get('COV_PLAIN'));
        } finally {
            unlink($envPath);
            if ($hadEnv) {
                rename($backup, $envPath);
            }
            // Limpa variáveis de ambiente definidas por putenv() em load()
            putenv('COV_DOUBLE');
            putenv('COV_SINGLE');
            putenv('COV_PLAIN');
            // Restaura estado original do Config
            $loaded->setValue(null, $originalLoaded);
            $config->setValue(null, $originalConfig);
        }
    }
}
