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

    $sql = <<<SQL
SELECT
    sp.ID AS product_id,
    sp.TEN AS product_name,
    sp.MOTA AS description,
    sp.TRANGTHAIKINHDOANH AS business_status,
    lsp.ID AS category_id,
    lsp.TEN AS category_name,
    (
        SELECT ct1.ID
        FROM chitietsanpham ct1
        WHERE ct1.IDSANPHAM = sp.ID
        ORDER BY ct1.ID ASC
        LIMIT 1
    ) AS detail_id,
    IFNULL(SUM(ctsp.SOLUONG), 0) AS total_stock,
    MIN(ctsp.GIABAN - ctsp.GIAGIAM) AS price,
    MIN(ctsp.GIABAN) AS original_price,
    MIN(ctsp.GIAGIAM) AS discount,
    (
        SELECT asp.TENANH
        FROM chitietsanpham ct1
        LEFT JOIN anhsanpham asp ON asp.IDCHITIETSANPHAM = ct1.ID
        WHERE ct1.IDSANPHAM = sp.ID
        ORDER BY ct1.ID ASC
        LIMIT 1
    ) AS image
FROM sanpham sp
LEFT JOIN loaisanpham lsp ON lsp.ID = sp.IDLOAISANPHAM
LEFT JOIN chitietsanpham ctsp ON ctsp.IDSANPHAM = sp.ID
GROUP BY sp.ID, sp.TEN, sp.MOTA, sp.TRANGTHAIKINHDOANH, lsp.ID, lsp.TEN
ORDER BY sp.ID DESC
SQL;

    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['product_id'] = (int) $row['product_id'];
        $row['category_id'] = $row['category_id'] !== null ? (int) $row['category_id'] : null;
        $row['business_status'] = (int) $row['business_status'];
        $row['detail_id'] = $row['detail_id'] !== null ? (int) $row['detail_id'] : null;
        $row['total_stock'] = (int) $row['total_stock'];
        $row['price'] = $row['price'] !== null ? (float) $row['price'] : null;
        $row['original_price'] = $row['original_price'] !== null ? (float) $row['original_price'] : null;
        $row['discount'] = $row['discount'] !== null ? (float) $row['discount'] : null;
    }
    unset($row);

    jsonResponse(200, [
        'success' => true,
        'message' => 'Fetched all products successfully.',
        'data' => $rows,
    ]);
} catch (Throwable $e) {
    jsonResponse(500, [
        'success' => false,
        'message' => 'Internal server error when fetching products.',
        'error' => $e->getMessage(),
        'data' => null,
    ]);
}
