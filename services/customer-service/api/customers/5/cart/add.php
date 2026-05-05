<?php

require_once '/var/www/html/db.php';

// Get customer ID from URL
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
preg_match('/\/api\/customers\/(\d+)\/cart/', $path, $matches);
$customerId = isset($matches[1]) ? (int)$matches[1] : 0;

if (!$customerId) {
    jsonResponse(400, ['success' => false, 'message' => 'Customer ID required']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, ['success' => false, 'message' => 'Method not allowed']);
}

$input = getJsonInput();

if (empty($input['product_id']) || empty($input['detail_id']) || empty($input['quantity'])) {
    jsonResponse(400, ['success' => false, 'message' => 'Missing fields: product_id, detail_id, quantity']);
}

$productId = (int)$input['product_id'];
$detailId = (int)$input['detail_id'];
$quantity = (int)$input['quantity'];
$name = $input['name'] ?? '';
$price = (float)($input['price'] ?? 0);

if ($quantity <= 0) {
    jsonResponse(400, ['success' => false, 'message' => 'Quantity must be greater than 0']);
}

try {
    $pdo = getPDO();
    
    // Get current cart
    $stmt = $pdo->prepare('SELECT GHICHU FROM gio_hang WHERE IDKHACHHANG = ?');
    $stmt->execute([$customerId]);
    $cart = $stmt->fetch();
    
    if (!$cart) {
        // Create empty cart
        $stmt = $pdo->prepare('INSERT INTO gio_hang (IDKHACHHANG, GHICHU) VALUES (?, ?)');
        $stmt->execute([$customerId, json_encode(['items' => []])]);
        $cartData = ['items' => []];
    } else {
        $cartData = json_decode($cart['GHICHU'], true) ?: ['items' => []];
    }
    
    $items = $cartData['items'] ?? [];
    
    // Check if item already exists
    $itemKey = null;
    foreach ($items as $key => $item) {
        if ($item['product_id'] == $productId && $item['detail_id'] == $detailId) {
            $itemKey = $key;
            break;
        }
    }
    
    if ($itemKey !== null) {
        // Update quantity
        $items[$itemKey]['quantity'] += $quantity;
    } else {
        // Add new item
        $items[] = [
            'product_id' => $productId,
            'detail_id' => $detailId,
            'name' => $name,
            'price' => $price,
            'quantity' => $quantity
        ];
    }
    
    // Save cart
    $cartData['items'] = $items;
    $stmt = $pdo->prepare('UPDATE gio_hang SET GHICHU = ? WHERE IDKHACHHANG = ?');
    $stmt->execute([json_encode($cartData), $customerId]);
    
    jsonResponse(200, [
        'success' => true,
        'message' => 'Item added to cart',
        'data' => [
            'total_items' => count($items)
        ]
    ]);
    
} catch (Exception $e) {
    jsonResponse(500, ['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}