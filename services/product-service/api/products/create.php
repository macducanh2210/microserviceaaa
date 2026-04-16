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

$productName = trim((string) ($input['product_name'] ?? ''));
$description = isset($input['description']) ? trim((string) $input['description']) : null;
$categoryId = isset($input['category_id']) ? (int) $input['category_id'] : 0;
$businessStatus = isset($input['business_status']) ? (int) $input['business_status'] : 1;
$variants = $input['variants'] ?? null;

if ($productName === '') {
    jsonResponse(400, [
        'success' => false,
        'message' => 'product_name is required.',
        'data' => null,
    ]);
}

if ($categoryId <= 0) {
    jsonResponse(400, [
        'success' => false,
        'message' => 'category_id is required.',
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

try {
    $pdo = getPDO();

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
            'color' => $color,
            'size' => $size,
            'price' => $price,
            'discount' => $discount,
            'cost_price' => $costPrice,
            'status' => $status,
            'image' => $image,
        ];
    }

    $pdo->beginTransaction();

    $productStmt = $pdo->prepare('INSERT INTO sanpham (IDLOAISANPHAM, TEN, MOTA, TRANGTHAIKINHDOANH) VALUES (:category_id, :name, :description, :business_status)');
    $productStmt->execute([
        'category_id' => $categoryId,
        'name' => $productName,
        'description' => $description !== '' ? $description : null,
        'business_status' => $businessStatus,
    ]);

    $productId = (int) $pdo->lastInsertId();

    $variantStmt = $pdo->prepare('INSERT INTO chitietsanpham (IDSANPHAM, MAUSAC, SIZE, SOLUONG, GIABAN, GIAVON, GIAGIAM, TRANGTHAI) VALUES (:product_id, :color, :size, 0, :price, :cost_price, :discount, :status)');
    $imageStmt = $pdo->prepare('INSERT INTO anhsanpham (IDCHITIETSANPHAM, TENANH) VALUES (:detail_id, :image)');

    $createdVariants = [];

    foreach ($normalizedVariants as $variant) {
        $variantStmt->execute([
            'product_id' => $productId,
            'color' => $variant['color'],
            'size' => $variant['size'],
            'price' => $variant['price'],
            'cost_price' => $variant['cost_price'],
            'discount' => $variant['discount'],
            'status' => $variant['status'],
        ]);

        $detailId = (int) $pdo->lastInsertId();

        if ($variant['image'] !== '') {
            $imageStmt->execute([
                'detail_id' => $detailId,
                'image' => $variant['image'],
            ]);
        }

        $createdVariants[] = [
            'detail_id' => $detailId,
            'color' => $variant['color'],
            'size' => $variant['size'],
            'price' => $variant['price'],
            'discount' => $variant['discount'],
            'status' => $variant['status'],
            'image' => $variant['image'] !== '' ? $variant['image'] : null,
            'stock' => 0,
        ];
    }

    $pdo->commit();

    jsonResponse(201, [
        'success' => true,
        'message' => 'Product created successfully.',
        'data' => [
            'product_id' => $productId,
            'product_name' => $productName,
            'category_id' => $categoryId,
            'business_status' => $businessStatus,
            'variants' => $createdVariants,
        ],
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $status = $e instanceof InvalidArgumentException ? 400 : 500;
    jsonResponse($status, [
        'success' => false,
        'message' => 'Failed to create product.',
        'error' => $e->getMessage(),
        'data' => null,
    ]);
}
