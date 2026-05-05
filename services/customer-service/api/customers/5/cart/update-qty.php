<?php

require_once __DIR__ . '/../../../db.php';

// Get customer ID from URL
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
preg_match('/\/api\/customers\/(\d+)\/cart/', $path, $matches);
$customerId = isset($matches[1]) ? (int)$matches[1] : 0;

if (!$customerId) {
    jsonResponse(400, ['success' => false, 'message' => 'Customer ID required']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    jsonResponse(405, ['success' => false, 'message' => 'Method not allowed']);
}

$input = getJsonInput();

if (empty($input['product_id']) || empty($input['detail_id']) || !isset($input['quantity'])) {
    jsonResponse(400, ['success' => false, 'message' => 'Missing fields: product_id, detail_id, quantity']);
}

$productId = (int)$input['product_id'];
$detailId = (int)$input['detail_id'];
$quantity = (int)$input['quantity'];

if ($quantity < 0) {
    jsonResponse(400, ['success' => false, 'message' => 'Quantity cannot be negative']);
}

try {
    $pdo = getPDO();
    
    // Get current cart
    $stmt = $pdo->prepare('SELECT GHICHU FROM gio_hang WHERE IDKHACHHANG = ?');
    $stmt->execute([$customerId]);
    $cart = $stmt->fetch();
    
    if (!$cart) {
        jsonResponse(404, ['success' => false, 'message' => 'Cart not found']);
    }
    
    $cartData = json_decode($cart['GHICHU'], true) ?: ['items' => []];
    $items = $cartData['items'] ?? [];
    
    // Update quantity
    foreach ($items as &$item) {
        if ($item['product_id'] == $productId && $item['detail_id'] == $detailId) {
            if ($quantity === 0) {
                // Remove item if quantity is 0
                $item = null;
            } else {
                $item['quantity'] = $quantity;
            }
            break;
        }
    }
    
    // Remove null items
    $items = array_filter($items);
    $items = array_values($items);
    
    // Save cart
    $cartData['items'] = $items;
    $stmt = $pdo->prepare('UPDATE gio_hang SET GHICHU = ? WHERE IDKHACHHANG = ?');
    $stmt->execute([json_encode($cartData), $customerId]);
    
    jsonResponse(200, [
        'success' => true,
        'message' => 'Cart updated successfully',
        'data' => [
            'total_items' => count($items)
        ]
    ]);
    
} catch (Exception $e) {
    jsonResponse(500, ['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
