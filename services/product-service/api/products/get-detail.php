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

$id = $_GET['id'] ?? null;
if ($id === null || !ctype_digit((string) $id)) {
    jsonResponse(400, [
        'success' => false,
        'message' => 'Invalid or missing product id.',
        'data' => null,
    ]);
}

$productId = (int) $id;

try {
    $pdo = getPDO();

    $productSql = <<<SQL
SELECT
    sp.ID AS product_id,
    sp.TEN AS product_name,
    sp.MOTA AS description,
    sp.TRANGTHAIKINHDOANH AS business_status,
    lsp.ID AS category_id,
    lsp.TEN AS category_name
FROM sanpham sp
LEFT JOIN loaisanpham lsp ON lsp.ID = sp.IDLOAISANPHAM
WHERE sp.ID = :id
LIMIT 1
SQL;

    $productStmt = $pdo->prepare($productSql);
    $productStmt->execute(['id' => $productId]);
    $product = $productStmt->fetch();

    if (!$product) {
        jsonResponse(404, [
            'success' => false,
            'message' => 'Product not found.',
            'data' => null,
        ]);
    }

    $variantSql = <<<SQL
SELECT
    ctsp.ID AS detail_id,
    ctsp.MAUSAC AS color,
    ctsp.SIZE AS size,
    ctsp.SOLUONG AS stock,
    ctsp.GIABAN AS original_price,
    ctsp.GIAGIAM AS discount,
    (ctsp.GIABAN - ctsp.GIAGIAM) AS price,
    ctsp.TRANGTHAI AS status,
    ctsp.NGAYCAPNHAT AS updated_at,
    asp.TENANH AS image
FROM chitietsanpham ctsp
LEFT JOIN anhsanpham asp ON asp.IDCHITIETSANPHAM = ctsp.ID
WHERE ctsp.IDSANPHAM = :id
ORDER BY ctsp.ID ASC
SQL;

    $variantStmt = $pdo->prepare($variantSql);
    $variantStmt->execute(['id' => $productId]);
    $variantRows = $variantStmt->fetchAll();

    $variantsMap = [];
    $images = [];

    foreach ($variantRows as $row) {
        $detailId = (int) $row['detail_id'];

        if (!isset($variantsMap[$detailId])) {
            $variantsMap[$detailId] = [
                'detail_id' => $detailId,
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
        }

        if ($row['image'] !== null && $row['image'] !== '') {
            $variantsMap[$detailId]['images'][] = $row['image'];
            $images[$row['image']] = true;
        }
    }

    $product['product_id'] = (int) $product['product_id'];
    $product['category_id'] = $product['category_id'] !== null ? (int) $product['category_id'] : null;
    $product['business_status'] = (int) $product['business_status'];
    $product['variants'] = array_values($variantsMap);
    $product['images'] = array_keys($images);

    jsonResponse(200, [
        'success' => true,
        'message' => 'Fetched product detail successfully.',
        'data' => $product,
    ]);
} catch (Throwable $e) {
    jsonResponse(500, [
        'success' => false,
        'message' => 'Internal server error when fetching product detail.',
        'error' => $e->getMessage(),
        'data' => null,
    ]);
}
