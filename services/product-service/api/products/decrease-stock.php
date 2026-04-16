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
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('UPDATE chitietsanpham SET SOLUONG = SOLUONG - :qty_set WHERE ID = :detail_id AND SOLUONG >= :qty_check');
    $stmt->execute([
        'qty_set' => $quantity,
        'qty_check' => $quantity,
        'detail_id' => $detailId,
    ]);

    if ($stmt->rowCount() === 0) {
        $pdo->rollBack();
        jsonResponse(400, [
            'success' => false,
            'message' => 'Không đủ tồn kho hoặc detail không tồn tại.',
            'data' => null,
        ]);
    }

    $stockStmt = $pdo->prepare('SELECT SOLUONG FROM chitietsanpham WHERE ID = :detail_id LIMIT 1');
    $stockStmt->execute(['detail_id' => $detailId]);
    $row = $stockStmt->fetch();

    $pdo->commit();

    jsonResponse(200, [
        'success' => true,
        'message' => 'Đã trừ tồn kho.',
        'data' => [
            'detail_id' => $detailId,
            'quantity' => $quantity,
            'remaining_stock' => (int) ($row['SOLUONG'] ?? 0),
        ],
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    jsonResponse(500, [
        'success' => false,
        'message' => 'Lỗi khi trừ tồn kho.',
        'error' => $e->getMessage(),
        'data' => null,
    ]);
}
