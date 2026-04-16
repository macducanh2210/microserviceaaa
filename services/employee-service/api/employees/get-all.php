<?php

declare(strict_types=1);

require_once __DIR__ . '/../../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResponse(200, ['success' => true, 'message' => 'Preflight OK', 'data' => null]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(405, [
        'success' => false,
        'message' => 'Method not allowed. Use GET.',
        'data' => null,
    ]);
}

try {
    $pdo = getPDO();

    $stmt = $pdo->prepare(
        'SELECT id, full_name, email, phone, position, role_level, salary, status, created_at FROM employees WHERE status = :status ORDER BY id DESC'
    );
    $stmt->execute(['status' => 'active']);

    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
        $row['role_level'] = (int) $row['role_level'];
        $row['salary'] = (float) $row['salary'];
    }
    unset($row);

    jsonResponse(200, [
        'success' => true,
        'message' => 'Fetched active employees successfully.',
        'data' => $rows,
    ]);
} catch (Throwable $e) {
    jsonResponse(500, [
        'success' => false,
        'message' => 'Failed to fetch employees.',
        'error' => $e->getMessage(),
        'data' => null,
    ]);
}
