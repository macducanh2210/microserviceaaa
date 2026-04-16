<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../db.php';

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
$supplierId = isset($input['supplier_id']) ? (int) $input['supplier_id'] : 0;
if ($supplierId <= 0) {
    jsonResponse(400, [
        'success' => false,
        'message' => 'supplier_id is required.',
        'data' => null,
    ]);
}

try {
    $pdo = getPDO();

    $stmt = $pdo->prepare('UPDATE suppliers SET is_active = 0 WHERE id = :id');
    $stmt->execute(['id' => $supplierId]);

    if ($stmt->rowCount() === 0) {
        jsonResponse(404, [
            'success' => false,
            'message' => 'Supplier not found or already inactive.',
            'data' => null,
        ]);
    }

    jsonResponse(200, [
        'success' => true,
        'message' => 'Supplier deleted successfully.',
        'data' => ['supplier_id' => $supplierId],
    ]);
} catch (Throwable $e) {
    jsonResponse(500, [
        'success' => false,
        'message' => 'Internal server error when deleting supplier.',
        'error' => $e->getMessage(),
        'data' => null,
    ]);
}
