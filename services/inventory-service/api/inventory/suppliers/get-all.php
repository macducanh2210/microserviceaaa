<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../db.php';

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

$includeInactive = isset($_GET['include_inactive']) && (string) $_GET['include_inactive'] === '1';

try {
    $pdo = getPDO();
    $sql = 'SELECT id, supplier_name, contact_name, phone, email, address, is_active, created_at, updated_at FROM suppliers';
    if (!$includeInactive) {
        $sql .= ' WHERE is_active = 1';
    }
    $sql .= ' ORDER BY id DESC';

    $rows = $pdo->query($sql)->fetchAll();
    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
        $row['is_active'] = (int) $row['is_active'];
    }
    unset($row);

    jsonResponse(200, [
        'success' => true,
        'message' => 'Fetched suppliers successfully.',
        'data' => $rows,
    ]);
} catch (Throwable $e) {
    jsonResponse(500, [
        'success' => false,
        'message' => 'Internal server error when fetching suppliers.',
        'error' => $e->getMessage(),
        'data' => null,
    ]);
}
