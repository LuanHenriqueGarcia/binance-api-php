<?php

namespace BinanceAPI;

use BinanceAPI\Contracts\CacheInterface;

/**
 * Implementação de cache baseada em arquivos
 */
class FileCache implements CacheInterface
{
    private string $dir;

    public function __construct(?string $dir = null)
    {
        $this->dir = $dir ?? Config::getStoragePath('cache');

        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0777, true);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, int $ttlSeconds): ?array
    {
        $file = $this->path($key);

        if (!file_exists($file)) {
            return null;
        }

        if (filemtime($file) + $ttlSeconds < time()) {
            $this->delete($key);
            return null;
        }

        $content = $this->readFile($file);
        if ($content === false) {
            return null;
        }

        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, array $value): void
    {
        $file = $this->path($key);
        file_put_contents(
            $file,
            json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): void
    {
        $file = $this->path($key);
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $files = $this->globFiles($this->dir . '/*.json');

        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            @unlink($file);
        }
    }

    /**
     * Gera o caminho do arquivo de cache
     */
    private function path(string $key): string
    {
        return $this->dir . '/' . md5($key) . '.json';
    }

    /**
     * @return string|false
     */
    protected function readFile(string $path)
    {
        return file_get_contents($path);
    }

    /**
     * @return array<string>|false
     */
    protected function globFiles(string $pattern)
    {
        return glob($pattern);
    }
}
