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

$items = $input['items'] ?? null;
if (!is_array($items) || count($items) === 0) {
    jsonResponse(400, [
        'success' => false,
        'message' => 'items must be a non-empty array.',
        'data' => null,
    ]);
}

try {
    $pdo = getPDO();
    $pdo->beginTransaction();

    $selectByDetailStmt = $pdo->prepare('SELECT ID, IDSANPHAM, SOLUONG FROM chitietsanpham WHERE ID = :detail_id LIMIT 1 FOR UPDATE');
    $selectByProductStmt = $pdo->prepare('SELECT ID, IDSANPHAM, SOLUONG FROM chitietsanpham WHERE IDSANPHAM = :product_id ORDER BY ID ASC LIMIT 1 FOR UPDATE');
    $updateStmt = $pdo->prepare('UPDATE chitietsanpham SET SOLUONG = SOLUONG + :quantity, NGAYCAPNHAT = NOW() WHERE ID = :detail_id');

    $updated = [];

    foreach ($items as $idx => $item) {
        if (!is_array($item)) {
            throw new InvalidArgumentException('Item at index ' . $idx . ' is invalid.');
        }

        $detailId = isset($item['detail_id']) ? (int) $item['detail_id'] : 0;
        $productId = isset($item['product_id']) ? (int) $item['product_id'] : 0;
        $quantity = isset($item['quantity']) ? (int) $item['quantity'] : 0;

        if ($quantity <= 0 || ($detailId <= 0 && $productId <= 0)) {
            throw new InvalidArgumentException('Invalid item at index ' . $idx . '.');
        }

        $detailRow = null;
        if ($detailId > 0) {
            $selectByDetailStmt->execute(['detail_id' => $detailId]);
            $detailRow = $selectByDetailStmt->fetch();
        } else {
            $selectByProductStmt->execute(['product_id' => $productId]);
            $detailRow = $selectByProductStmt->fetch();
        }

        if (!$detailRow) {
            throw new RuntimeException('Product/detail not found for item index ' . $idx . '.');
        }

        $resolvedDetailId = (int) $detailRow['ID'];
        $resolvedProductId = (int) $detailRow['IDSANPHAM'];

        $updateStmt->execute([
            'quantity' => $quantity,
            'detail_id' => $resolvedDetailId,
        ]);

        $updated[] = [
            'product_id' => $resolvedProductId,
            'detail_id' => $resolvedDetailId,
            'quantity_added' => $quantity,
        ];
    }

    $pdo->commit();

    jsonResponse(200, [
        'success' => true,
        'message' => 'Stock updated successfully.',
        'data' => [
            'updated_items' => $updated,
            'count' => count($updated),
        ],
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $statusCode = $e instanceof InvalidArgumentException ? 400 : 500;
    jsonResponse($statusCode, [
        'success' => false,
        'message' => 'Failed to update stock.',
        'error' => $e->getMessage(),
        'data' => null,
    ]);
}
