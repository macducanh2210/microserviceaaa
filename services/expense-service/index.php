<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'service' => 'expense-service',
    'message' => 'Expense service is running.',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
