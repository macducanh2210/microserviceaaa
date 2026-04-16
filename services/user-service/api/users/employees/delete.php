<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResponse(200, [
        'success' => true,
        'message' => 'Preflight OK',
        'data' => null,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, [
        'success' => false,
        'message' => 'Method not allowed. Use POST.',
        'data' => null,
    ]);
}

$input = getJsonInput();
$id = (int) ($input['id'] ?? 0);

if ($id <= 0) {
    jsonResponse(400, [
        'success' => false,
        'message' => 'id khong hop le.',
        'data' => null,
    ]);
}

try {
    $pdo = getPDO();

    $existsStmt = $pdo->prepare('SELECT ID FROM nhanvien WHERE ID = :id LIMIT 1');
    $existsStmt->execute(['id' => $id]);
    if (!$existsStmt->fetch()) {
        jsonResponse(404, [
            'success' => false,
            'message' => 'Employee khong ton tai.',
            'data' => null,
        ]);
    }

    $stmt = $pdo->prepare('DELETE FROM nhanvien WHERE ID = :id');
    $stmt->execute(['id' => $id]);

    jsonResponse(200, [
        'success' => true,
        'message' => 'Deleted employee from user_db successfully.',
        'data' => [
            'id' => $id,
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
