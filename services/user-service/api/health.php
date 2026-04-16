<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';

try {
    $pdo = getPDO();
    $pdo->query('SELECT 1');

    jsonResponse(200, [
        'success' => true,
        'service' => 'user-service',
        'status' => 'healthy',
        'time' => date('c'),
    ]);
} catch (Throwable $e) {
    jsonResponse(500, [
        'success' => false,
        'service' => 'user-service',
        'status' => 'unhealthy',
        'error' => $e->getMessage(),
    ]);
}
