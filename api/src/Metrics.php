<?php

namespace TradersApi;

class Metrics
{
    private static int $success = 0;
    private static int $clientError = 0;
    private static int $serverError = 0;
    /** @var array<int> */
    private static array $latencies = [];

    public static function record(int $status, int $durationMs): void
    {
        if ($status >= 200 && $status < 300) {
            self::$success++;
        } elseif ($status >= 400 && $status < 500) {
            self::$clientError++;
        } elseif ($status >= 500) {
            self::$serverError++;
        }

        self::$latencies[] = $durationMs;
        if (count(self::$latencies) > 100) {
            array_shift(self::$latencies);
        }
    }

    /**
     * @return array<string,mixed>
     */
    public static function snapshot(): array
    {
        $count = count(self::$latencies);
        $avg = $count ? array_sum(self::$latencies) / $count : 0;

        return [
            'http_2xx' => self::$success,
            'http_4xx' => self::$clientError,
            'http_5xx' => self::$serverError,
            'latency_ms_avg_last_100' => (int)$avg,
            'latency_ms_count' => $count
        ];
    }
}
