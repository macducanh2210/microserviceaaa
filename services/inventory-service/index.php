<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

jsonResponse(200, [
    'success' => true,
    'message' => 'Inventory service is running.',
    'data' => [
        'service' => 'inventory-service',
        'time' => date('c'),
    ],
]);
