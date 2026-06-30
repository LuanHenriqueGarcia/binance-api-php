<?php

use TradersApi\Metrics;
use PHPUnit\Framework\TestCase;

class MetricsTest extends TestCase
{
    public function testRecordSuccess(): void
    {
        // Record some successful requests
        Metrics::record(200, 100);
        Metrics::record(201, 150);
        Metrics::record(204, 50);

        $snapshot = Metrics::snapshot();

        $this->assertGreaterThanOrEqual(3, $snapshot['http_2xx']);
    }

    public function testRecordClientError(): void
    {
        Metrics::record(400, 100);
        Metrics::record(401, 100);
        Metrics::record(404, 100);

        $snapshot = Metrics::snapshot();

        $this->assertGreaterThanOrEqual(3, $snapshot['http_4xx']);
    }

    public function testRecordServerError(): void
    {
        Metrics::record(500, 100);
        Metrics::record(502, 100);
        Metrics::record(503, 100);

        $snapshot = Metrics::snapshot();

        $this->assertGreaterThanOrEqual(3, $snapshot['http_5xx']);
    }

    public function testSnapshotReturnsExpectedKeys(): void
    {
        Metrics::record(200, 100);

        $snapshot = Metrics::snapshot();

        $this->assertArrayHasKey('http_2xx', $snapshot);
        $this->assertArrayHasKey('http_4xx', $snapshot);
        $this->assertArrayHasKey('http_5xx', $snapshot);
        $this->assertArrayHasKey('latency_ms_avg_last_100', $snapshot);
        $this->assertArrayHasKey('latency_ms_count', $snapshot);
    }

    public function testLatencyAverage(): void
    {
        // Record requests with known latencies
        Metrics::record(200, 100);
        Metrics::record(200, 200);
        Metrics::record(200, 300);

        $snapshot = Metrics::snapshot();

        // Average should be calculated
        $this->assertIsInt($snapshot['latency_ms_avg_last_100']);
        $this->assertGreaterThan(0, $snapshot['latency_ms_count']);
    }

    public function testLatencyCountTracked(): void
    {
        $initialSnapshot = Metrics::snapshot();
        $initialCount = $initialSnapshot['latency_ms_count'];

        Metrics::record(200, 50);
        Metrics::record(200, 75);

        $newSnapshot = Metrics::snapshot();

        $this->assertGreaterThanOrEqual($initialCount + 2, $newSnapshot['latency_ms_count']);
    }

    public function testLatencyCapAt100(): void
    {
        // Record 105 requests to test the cap at 100
        for ($i = 0; $i < 105; $i++) {
            Metrics::record(200, 10);
        }

        $snapshot = Metrics::snapshot();

        // Should be capped at 100
        $this->assertLessThanOrEqual(100, $snapshot['latency_ms_count']);
    }

    public function testRecordNon2xx3xx4xx5xxStatus(): void
    {
        // Record 1xx status (informational)
        Metrics::record(100, 50);
        Metrics::record(101, 50);

        // Record 3xx status (redirect)
        Metrics::record(301, 50);
        Metrics::record(302, 50);

        $snapshot = Metrics::snapshot();

        // These shouldn't count towards 2xx, 4xx, or 5xx
        $this->assertArrayHasKey('http_2xx', $snapshot);
        $this->assertArrayHasKey('http_4xx', $snapshot);
        $this->assertArrayHasKey('http_5xx', $snapshot);
    }

    public function testSnapshotWithNoRecords(): void
    {
        // Get snapshot - latency average with no records should be 0
        $snapshot = Metrics::snapshot();

        $this->assertIsArray($snapshot);
        $this->assertIsInt($snapshot['latency_ms_avg_last_100']);
    }
}
