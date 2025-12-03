<?php

use BinanceAPI\Router;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    public function testSendResponseSuccessSets200(): void
    {
        $router = new Router('GET', '/test', []);
        $output = $this->invokeSendResponse($router, ['success' => true, 'data' => []]);

        $this->assertSame(200, http_response_code());
        $this->assertStringContainsString('"success": true', $output);
    }

    public function testSendResponseErrorSets400(): void
    {
        $router = new Router('GET', '/test', []);
        $output = $this->invokeSendResponse($router, ['success' => false, 'error' => 'fail']);

        $this->assertSame(400, http_response_code());
        $this->assertStringContainsString('"success": false', $output);
    }

    public function testSendResponseUsesCustomCode(): void
    {
        $router = new Router('GET', '/test', []);
        $output = $this->invokeSendResponse($router, ['success' => false, 'error' => 'rate', 'code' => 429]);

        $this->assertSame(429, http_response_code());
        $this->assertStringContainsString('"code": 429', $output);
    }

    /**
     * @param array<string,mixed> $data
     */
    private function invokeSendResponse(Router $router, array $data): string
    {
        $method = new ReflectionMethod(Router::class, 'sendResponse');
        $method->setAccessible(true);

        ob_start();
        $method->invoke($router, $data);
        return (string)ob_get_clean();
    }
}
