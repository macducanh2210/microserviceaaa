<?php

declare(strict_types=1);

require_once __DIR__ . '/../../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResponse(200, ['success' => true, 'message' => 'Preflight OK', 'data' => null]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, [
        'success' => false,
        'message' => 'Method not allowed. Use POST.',
        'data' => null,
    ]);
}

$input = getJsonInput();
$id = isset($input['id']) ? (int) $input['id'] : 0;

if ($id <= 0) {
    jsonResponse(400, [
        'success' => false,
        'message' => 'id is required.',
        'data' => null,
    ]);
}

try {
    $pdo = getPDO();

    $existsStmt = $pdo->prepare('SELECT id, status FROM employees WHERE id = :id LIMIT 1');
    $existsStmt->execute(['id' => $id]);
    $row = $existsStmt->fetch();

    if (!$row) {
        jsonResponse(404, [
            'success' => false,
            'message' => 'Employee not found.',
            'data' => null,
        ]);
    }

    if ($row['status'] === 'inactive') {
        jsonResponse(200, [
            'success' => true,
            'message' => 'Employee already inactive.',
            'data' => [
                'id' => $id,
                'status' => 'inactive',
            ],
        ]);
    }

    $stmt = $pdo->prepare('UPDATE employees SET status = :status WHERE id = :id');
    $stmt->execute([
        'status' => 'inactive',
        'id' => $id,
    ]);

    jsonResponse(200, [
        'success' => true,
        'message' => 'Employee marked as inactive successfully.',
        'data' => [
            'id' => $id,
            'status' => 'inactive',
        ],
    ]);
} catch (Throwable $e) {
    jsonResponse(500, [
        'success' => false,
        'message' => 'Failed to delete employee.',
        'error' => $e->getMessage(),
        'data' => null,
    ]);
}
