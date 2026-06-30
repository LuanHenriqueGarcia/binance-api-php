<?php

namespace TradersApi;

class Cache
{
    private string $dir;

    public function __construct(?string $dir = null)
    {
        $this->dir = $dir ?? Config::getStoragePath('cache');
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0750, true);
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    public function get(string $key, int $ttlSeconds): ?array
    {
        $file = $this->path($key);
        if (!file_exists($file)) {
            return null;
        }

        if (filemtime($file) + $ttlSeconds < time()) {
            @unlink($file);
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
     * @param array<string,mixed> $value
     */
    public function set(string $key, array $value): void
    {
        $file = $this->path($key);
        file_put_contents($file, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

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
}
