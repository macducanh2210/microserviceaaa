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

$detailId = isset($_GET['detail_id']) ? (int) $_GET['detail_id'] : 0;
if ($detailId <= 0) {
    jsonResponse(400, [
        'success' => false,
        'message' => 'Invalid detail_id.',
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
    ctsp.ID AS detail_id,
    ctsp.MAUSAC AS color,
    ctsp.SIZE AS size,
    ctsp.SOLUONG AS stock,
    ctsp.GIABAN AS original_price,
    ctsp.GIAGIAM AS discount,
    (ctsp.GIABAN - ctsp.GIAGIAM) AS price,
    ctsp.TRANGTHAI AS status,
    ctsp.NGAYCAPNHAT AS updated_at
FROM chitietsanpham ctsp
JOIN sanpham sp ON sp.ID = ctsp.IDSANPHAM
LEFT JOIN loaisanpham lsp ON lsp.ID = sp.IDLOAISANPHAM
WHERE ctsp.ID = :detail_id
LIMIT 1
SQL;

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['detail_id' => $detailId]);
    $row = $stmt->fetch();

    if (!$row) {
        jsonResponse(404, [
            'success' => false,
            'message' => 'Detail not found.',
            'data' => null,
        ]);
    }

    $variant = [
        'detail_id' => (int) $row['detail_id'],
        'color' => $row['color'],
        'size' => $row['size'],
        'stock' => (int) $row['stock'],
        'original_price' => (float) $row['original_price'],
        'discount' => (float) $row['discount'],
        'price' => (float) $row['price'],
        'status' => (int) $row['status'],
        'updated_at' => $row['updated_at'],
        'images' => [],
    ];

    jsonResponse(200, [
        'success' => true,
        'message' => 'Fetched product detail by detail_id successfully.',
        'data' => [
            'product_id' => (int) $row['product_id'],
            'product_name' => $row['product_name'],
            'description' => $row['description'],
            'business_status' => (int) $row['business_status'],
            'category_id' => $row['category_id'] !== null ? (int) $row['category_id'] : null,
            'category_name' => $row['category_name'],
            'variants' => [$variant],
            'images' => [],
        ],
    ]);
} catch (Throwable $e) {
    jsonResponse(500, [
        'success' => false,
        'message' => 'Internal server error when fetching by detail_id.',
        'error' => $e->getMessage(),
        'data' => null,
    ]);
}
