<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResponse(200, [
        'success' => true,
        'message' => 'Preflight OK',
        'data' => null,
    ]);
}

jsonResponse(200, [
    'success' => true,
    'message' => 'employee-service is running.',
    'data' => [
        'service' => 'employee-service',
        'timestamp' => date('c'),
    ],
]);
