<?php

require_once __DIR__ . '/../../../db.php';

// Get customer ID from URL
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
preg_match('/\/api\/customers\/(\d+)\//', $path, $matches);
$requestedCustomerId = isset($matches[1]) ? (int)$matches[1] : 0;

if (!$requestedCustomerId) {
    jsonResponse(400, ['success' => false, 'message' => 'Customer ID required']);
}

$customerId = requireCustomerSession($requestedCustomerId);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, ['success' => false, 'message' => 'Method not allowed']);
}

$input = getJsonInput();

if (empty($input['product_id']) || !isset($input['detail_id'])) {
    jsonResponse(400, ['success' => false, 'message' => 'Missing fields: product_id, detail_id']);
}

$productId = (int)$input['product_id'];
$detailId = (int)$input['detail_id'];

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
    
    // Remove item
    $items = array_filter($items, function($item) use ($productId, $detailId) {
        return !($item['product_id'] == $productId && $item['detail_id'] == $detailId);
    });
    
    // Re-index array
    $items = array_values($items);
    
    // Save cart
    $cartData['items'] = $items;
    $stmt = $pdo->prepare('UPDATE gio_hang SET GHICHU = ? WHERE IDKHACHHANG = ?');
    $stmt->execute([json_encode($cartData), $customerId]);
    
    jsonResponse(200, [
        'success' => true,
        'message' => 'Item removed from cart',
        'data' => [
            'total_items' => count($items)
        ]
    ]);
    
} catch (Exception $e) {
    jsonResponse(500, ['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
