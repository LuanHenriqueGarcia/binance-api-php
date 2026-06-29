<?php

namespace BinanceAPI;

class RateLimiter
{
    private string $dir;
    private int $max;
    private int $window;

    public function __construct(?string $dir = null, ?int $max = null, ?int $window = null)
    {
        $this->dir = $dir ?? Config::getStoragePath('ratelimit');
        $this->max = $max ?? (int)Config::get('RATE_LIMIT_MAX', 60);
        $this->window = $window ?? (int)Config::get('RATE_LIMIT_WINDOW', 60);

        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0777, true);
        }
    }

    /**
     * @return array{allowed:bool,retryAfter:int|null}
     */
    public function hit(string $key): array
    {
        $now = time();
        $file = $this->dir . '/' . md5($key) . '.json';

        /** @var array<int,int> $timestamps */
        $timestamps = [];
        $fh = $this->openFile($file);
        if ($fh === false) {
            return ['allowed' => true, 'retryAfter' => null];
        }

        if (flock($fh, LOCK_EX)) {
            $content = stream_get_contents($fh);
            if ($content) {
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    $timestamps = $decoded;
                }
            }

            // remove expirados
            $cutoff = $now - $this->window;
            $timestamps = array_values(array_filter($timestamps, fn ($ts) => $ts >= $cutoff));

            if (count($timestamps) >= $this->max) {
                $oldest = $timestamps[0];
                $retryAfter = ($oldest + $this->window) - $now;
                flock($fh, LOCK_UN);
                fclose($fh);
                return ['allowed' => false, 'retryAfter' => max(1, $retryAfter)];
            }

            $timestamps[] = $now;
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, json_encode($timestamps));
            fflush($fh);
            flock($fh, LOCK_UN);
        }

        fclose($fh);
        return ['allowed' => true, 'retryAfter' => null];
    }

    /**
     * @return resource|false
     */
    protected function openFile(string $file)
    {
        return @fopen($file, 'c+');
    }
}
