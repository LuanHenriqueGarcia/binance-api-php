<?php

/**
 * Autoloader PSR-4 simples
 */
spl_autoload_register(function ($class) {
    $prefix = 'TradersApi\\';

    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relative_class = substr($class, strlen($prefix));
    $file = __DIR__ . '/src/' . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

use TradersApi\Router;

header('Content-Type: application/json');

try {
    $router = new Router();
    $router->dispatch();
} catch (\Throwable $e) {
    http_response_code(500);
    \TradersApi\Logger::error([
        'error' => $e->getMessage(),
        'request_id' => \TradersApi\Config::getRequestId(),
    ]);
    echo json_encode([
        'success' => false,
        'error' => \TradersApi\Config::isDebug() ? $e->getMessage() : 'Erro interno do servidor'
    ]);
}