<?php

declare(strict_types=1);

require_once __DIR__ . '/../../db.php';

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
ensureProductStockWriteAuthorized($input);

$detailId = isset($input['detail_id']) ? (int) $input['detail_id'] : 0;
$quantity = isset($input['quantity']) ? (int) $input['quantity'] : 0;

if ($detailId <= 0 || $quantity <= 0) {
    jsonResponse(400, [
        'success' => false,
        'message' => 'detail_id và quantity phải > 0.',
        'data' => null,
    ]);
}

try {
    $pdo = getPDO();

    $stmt = $pdo->prepare('UPDATE chitietsanpham SET SOLUONG = SOLUONG + :qty WHERE ID = :detail_id');
    $stmt->execute([
        'qty' => $quantity,
        'detail_id' => $detailId,
    ]);

    if ($stmt->rowCount() === 0) {
        jsonResponse(400, [
            'success' => false,
            'message' => 'detail_id không tồn tại.',
            'data' => null,
        ]);
    }

    jsonResponse(200, [
        'success' => true,
        'message' => 'Đã hoàn tồn kho.',
        'data' => [
            'detail_id' => $detailId,
            'quantity' => $quantity,
        ],
    ]);
} catch (Throwable $e) {
    jsonResponse(500, [
        'success' => false,
        'message' => 'Lỗi khi hoàn tồn kho.',
        'error' => $e->getMessage(),
        'data' => null,
    ]);
}
