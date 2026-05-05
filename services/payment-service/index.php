<?php
header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'service' => 'payment-service',
    'status' => 'ok',
    'database' => getenv('DB_NAME') ?: 'order_db'
], JSON_UNESCAPED_UNICODE);