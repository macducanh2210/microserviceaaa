<?php
header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'service' => 'order-service',
    'status' => 'ok',
    'database' => getenv('DB_NAME') ?: 'order_db'
], JSON_UNESCAPED_UNICODE);
