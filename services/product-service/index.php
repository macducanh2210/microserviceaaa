<?php
header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'service' => 'product-service',
    'status' => 'ok',
    'database' => getenv('DB_NAME') ?: 'product_db'
], JSON_UNESCAPED_UNICODE);
