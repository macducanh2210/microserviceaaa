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
$productName = trim((string) ($input['product_name'] ?? ''));
$description = isset($input['description']) ? trim((string) $input['description']) : null;
$categoryId = isset($input['category_id']) ? (int) $input['category_id'] : 0;
$businessStatus = isset($input['business_status']) ? (int) $input['business_status'] : 1;
$variants = $input['variants'] ?? null;
$removedDetailIds = $input['removed_detail_ids'] ?? [];

if ($productId <= 0) {
    jsonResponse(400, [
        'success' => false,
        'message' => 'product_id is required.',
        'data' => null,
    ]);
}

if ($productName === '' || $categoryId <= 0) {
    jsonResponse(400, [
        'success' => false,
        'message' => 'product_name and category_id are required.',
        'data' => null,
    ]);
}

if (!is_array($variants) || count($variants) === 0) {
    jsonResponse(400, [
        'success' => false,
        'message' => 'variants must be a non-empty array.',
        'data' => null,
    ]);
}

if ($businessStatus !== 0 && $businessStatus !== 1) {
    $businessStatus = 1;
}

if (!is_array($removedDetailIds)) {
    $removedDetailIds = [];
}

try {
    $pdo = getPDO();

    $productExistsStmt = $pdo->prepare('SELECT ID FROM sanpham WHERE ID = :id LIMIT 1');
    $productExistsStmt->execute(['id' => $productId]);
    if (!$productExistsStmt->fetch()) {
        jsonResponse(404, [
            'success' => false,
            'message' => 'Product not found.',
            'data' => null,
        ]);
    }

    $categoryStmt = $pdo->prepare('SELECT ID FROM loaisanpham WHERE ID = :id LIMIT 1');
    $categoryStmt->execute(['id' => $categoryId]);
    if (!$categoryStmt->fetch()) {
        jsonResponse(404, [
            'success' => false,
            'message' => 'Category not found.',
            'data' => null,
        ]);
    }

    $normalizedVariants = [];
    foreach ($variants as $index => $variant) {
        if (!is_array($variant)) {
            throw new InvalidArgumentException('Variant at index ' . $index . ' is invalid.');
        }

        if (array_key_exists('stock', $variant) || array_key_exists('quantity', $variant)) {
            throw new InvalidArgumentException('Stock/quantity is managed by import flow, not product CRUD.');
        }

        $detailId = isset($variant['detail_id']) ? (int) $variant['detail_id'] : 0;
        $color = trim((string) ($variant['color'] ?? ''));
        $size = trim((string) ($variant['size'] ?? ''));
        $price = isset($variant['price']) ? (float) $variant['price'] : -1;
        $discount = isset($variant['discount']) ? (float) $variant['discount'] : 0.0;
        $costPrice = isset($variant['cost_price']) ? (float) $variant['cost_price'] : 0.0;
        $status = isset($variant['status']) ? (int) $variant['status'] : 1;
        $image = trim((string) ($variant['image'] ?? ''));

        if ($color === '' || $size === '' || $price < 0) {
            throw new InvalidArgumentException('Invalid variant at index ' . $index . '.');
        }

        if ($discount < 0) {
            $discount = 0.0;
        }

        if ($status !== 0 && $status !== 1) {
            $status = 1;
        }

        $normalizedVariants[] = [
            'detail_id' => $detailId,
            'color' => $color,
            'size' => $size,
            'price' => $price,
            'discount' => $discount,
            'cost_price' => $costPrice,
            'status' => $status,
            'image' => $image,
        ];
    }

    $removedIds = array_values(array_filter(array_map(static fn($id): int => (int) $id, $removedDetailIds), static fn($id): bool => $id > 0));

    $pdo->beginTransaction();

    $updateProductStmt = $pdo->prepare('UPDATE sanpham SET IDLOAISANPHAM = :category_id, TEN = :name, MOTA = :description, TRANGTHAIKINHDOANH = :business_status WHERE ID = :product_id');
    $updateProductStmt->execute([
        'category_id' => $categoryId,
        'name' => $productName,
        'description' => $description !== '' ? $description : null,
        'business_status' => $businessStatus,
        'product_id' => $productId,
    ]);

    if (!empty($removedIds)) {
        $inClause = implode(',', array_fill(0, count($removedIds), '?'));
        $deleteImagesSql = 'DELETE asp FROM anhsanpham asp INNER JOIN chitietsanpham ct ON ct.ID = asp.IDCHITIETSANPHAM WHERE ct.IDSANPHAM = ? AND ct.ID IN (' . $inClause . ')';
        $deleteVariantsSql = 'DELETE FROM chitietsanpham WHERE IDSANPHAM = ? AND ID IN (' . $inClause . ')';

        $params = array_merge([$productId], $removedIds);
        $deleteImagesStmt = $pdo->prepare($deleteImagesSql);
        $deleteImagesStmt->execute($params);

        $deleteVariantsStmt = $pdo->prepare($deleteVariantsSql);
        $deleteVariantsStmt->execute($params);
    }

    $checkDetailStmt = $pdo->prepare('SELECT ID FROM chitietsanpham WHERE ID = :detail_id AND IDSANPHAM = :product_id LIMIT 1');
    $updateVariantStmt = $pdo->prepare('UPDATE chitietsanpham SET MAUSAC = :color, SIZE = :size, GIABAN = :price, GIAVON = :cost_price, GIAGIAM = :discount, TRANGTHAI = :status, NGAYCAPNHAT = NOW() WHERE ID = :detail_id AND IDSANPHAM = :product_id');
    $insertVariantStmt = $pdo->prepare('INSERT INTO chitietsanpham (IDSANPHAM, MAUSAC, SIZE, SOLUONG, GIABAN, GIAVON, GIAGIAM, TRANGTHAI) VALUES (:product_id, :color, :size, 0, :price, :cost_price, :discount, :status)');

    $deleteImagesByDetailStmt = $pdo->prepare('DELETE FROM anhsanpham WHERE IDCHITIETSANPHAM = :detail_id');
    $insertImageStmt = $pdo->prepare('INSERT INTO anhsanpham (IDCHITIETSANPHAM, TENANH) VALUES (:detail_id, :image)');

    $updatedVariants = [];

    foreach ($normalizedVariants as $variant) {
        $detailId = (int) $variant['detail_id'];

        if ($detailId > 0) {
            $checkDetailStmt->execute([
                'detail_id' => $detailId,
                'product_id' => $productId,
            ]);

            if (!$checkDetailStmt->fetch()) {
                throw new InvalidArgumentException('detail_id ' . $detailId . ' does not belong to this product.');
            }

            $updateVariantStmt->execute([
                'color' => $variant['color'],
                'size' => $variant['size'],
                'price' => $variant['price'],
                'cost_price' => $variant['cost_price'],
                'discount' => $variant['discount'],
                'status' => $variant['status'],
                'detail_id' => $detailId,
                'product_id' => $productId,
            ]);
        } else {
            $insertVariantStmt->execute([
                'product_id' => $productId,
                'color' => $variant['color'],
                'size' => $variant['size'],
                'price' => $variant['price'],
                'cost_price' => $variant['cost_price'],
                'discount' => $variant['discount'],
                'status' => $variant['status'],
            ]);
            $detailId = (int) $pdo->lastInsertId();
        }

        $deleteImagesByDetailStmt->execute(['detail_id' => $detailId]);
        if ($variant['image'] !== '') {
            $insertImageStmt->execute([
                'detail_id' => $detailId,
                'image' => $variant['image'],
            ]);
        }

        $updatedVariants[] = [
            'detail_id' => $detailId,
            'color' => $variant['color'],
            'size' => $variant['size'],
            'price' => $variant['price'],
            'discount' => $variant['discount'],
            'status' => $variant['status'],
            'image' => $variant['image'] !== '' ? $variant['image'] : null,
        ];
    }

    $pdo->commit();

    jsonResponse(200, [
        'success' => true,
        'message' => 'Product updated successfully.',
        'data' => [
            'product_id' => $productId,
            'product_name' => $productName,
            'category_id' => $categoryId,
            'business_status' => $businessStatus,
            'variants' => $updatedVariants,
            'removed_detail_ids' => $removedIds,
        ],
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $status = $e instanceof InvalidArgumentException ? 400 : 500;
    jsonResponse($status, [
        'success' => false,
        'message' => 'Failed to update product.',
        'error' => $e->getMessage(),
        'data' => null,
    ]);
}
