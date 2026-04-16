<?php
header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'service' => 'user-service',
    'status' => 'ok',
    'database' => getenv('DB_NAME') ?: 'user_db'
], JSON_UNESCAPED_UNICODE);
