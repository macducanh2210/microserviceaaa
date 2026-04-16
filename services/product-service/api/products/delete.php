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
ensureProductCrudWriteAuthorized($input);

$productId = isset($input['product_id']) ? (int) $input['product_id'] : 0;
$mode = trim((string) ($input['mode'] ?? 'soft'));

if ($productId <= 0) {
    jsonResponse(400, [
        'success' => false,
        'message' => 'product_id is required.',
        'data' => null,
    ]);
}

if ($mode !== 'soft' && $mode !== 'hard') {
    $mode = 'soft';
}

try {
    $pdo = getPDO();

    $checkStmt = $pdo->prepare('SELECT ID FROM sanpham WHERE ID = :id LIMIT 1');
    $checkStmt->execute(['id' => $productId]);
    if (!$checkStmt->fetch()) {
        jsonResponse(404, [
            'success' => false,
            'message' => 'Product not found.',
            'data' => null,
        ]);
    }

    $pdo->beginTransaction();

    if ($mode === 'hard') {
        $deleteImagesStmt = $pdo->prepare('DELETE asp FROM anhsanpham asp INNER JOIN chitietsanpham ct ON ct.ID = asp.IDCHITIETSANPHAM WHERE ct.IDSANPHAM = :product_id');
        $deleteVariantsStmt = $pdo->prepare('DELETE FROM chitietsanpham WHERE IDSANPHAM = :product_id');
        $deleteProductStmt = $pdo->prepare('DELETE FROM sanpham WHERE ID = :product_id');

        $deleteImagesStmt->execute(['product_id' => $productId]);
        $deleteVariantsStmt->execute(['product_id' => $productId]);
        $deleteProductStmt->execute(['product_id' => $productId]);
    } else {
        $disableProductStmt = $pdo->prepare('UPDATE sanpham SET TRANGTHAIKINHDOANH = 0 WHERE ID = :product_id');
        $disableVariantsStmt = $pdo->prepare('UPDATE chitietsanpham SET TRANGTHAI = 0, NGAYCAPNHAT = NOW() WHERE IDSANPHAM = :product_id');

        $disableProductStmt->execute(['product_id' => $productId]);
        $disableVariantsStmt->execute(['product_id' => $productId]);
    }

    $pdo->commit();

    jsonResponse(200, [
        'success' => true,
        'message' => $mode === 'hard' ? 'Product deleted permanently.' : 'Product disabled successfully.',
        'data' => [
            'product_id' => $productId,
            'mode' => $mode,
        ],
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    jsonResponse(500, [
        'success' => false,
        'message' => 'Failed to delete product.',
        'error' => $e->getMessage(),
        'data' => null,
    ]);
}
