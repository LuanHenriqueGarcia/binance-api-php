<?php

/**
 * Autoloader PSR-4 simples
 */
spl_autoload_register(function ($class) {
    $prefix = 'BinanceAPI\\';

    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relative_class = substr($class, strlen($prefix));
    $file = __DIR__ . '/src/' . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

use BinanceAPI\Router;

header('Content-Type: application/json');

try {
    $router = new Router();
    $router->dispatch();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}