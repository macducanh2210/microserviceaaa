<?php

declare(strict_types=1);

require_once __DIR__ . '/../../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(405, [
        'success' => false,
        'message' => 'Method not allowed. Use GET.',
        'data' => null,
    ]);
}

try {
    $pdo = getPDO();
    $stmt = $pdo->query('SELECT ID AS category_id, TEN AS category_name FROM loaisanpham ORDER BY TEN ASC');
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['category_id'] = (int) $row['category_id'];
    }
    unset($row);

    jsonResponse(200, [
        'success' => true,
        'message' => 'Fetched categories successfully.',
        'data' => $rows,
    ]);
} catch (Throwable $e) {
    jsonResponse(500, [
        'success' => false,
        'message' => 'Internal server error when fetching categories.',
        'error' => $e->getMessage(),
        'data' => null,
    ]);
}
