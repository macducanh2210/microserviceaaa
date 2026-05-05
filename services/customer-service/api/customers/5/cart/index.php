<?php

require_once '/var/www/html/db.php';

// Get customer ID from URL
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
preg_match('/\/api\/customers\/(\d+)\/cart/', $path, $matches);
$customerId = isset($matches[1]) ? (int)$matches[1] : 0;

if (!$customerId) {
    jsonResponse(400, ['success' => false, 'message' => 'Customer ID required']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(405, ['success' => false, 'message' => 'Method not allowed']);
}

try {
    $pdo = getPDO();
    
    // Get cart
    $stmt = $pdo->prepare('SELECT GHICHU FROM gio_hang WHERE IDKHACHHANG = ?');
    $stmt->execute([$customerId]);
    $cart = $stmt->fetch();
    
    if (!$cart) {
        // Create empty cart if not exists
        $stmt = $pdo->prepare('INSERT INTO gio_hang (IDKHACHHANG, GHICHU) VALUES (?, ?)');
        $stmt->execute([$customerId, json_encode(['items' => []])]);
        
        jsonResponse(200, [
            'success' => true,
            'data' => [
                'items' => [],
                'total_items' => 0,
                'total_price' => 0
            ]
        ]);
    }
    
    $cartData = json_decode($cart['GHICHU'], true) ?: ['items' => []];
    $items = $cartData['items'] ?? [];
    
    // Calculate totals
    $totalItems = array_sum(array_column($items, 'quantity', 0));
    $totalPrice = array_sum(array_map(function($item) {
        return ($item['price'] ?? 0) * ($item['quantity'] ?? 0);
    }, $items));
    
    jsonResponse(200, [
        'success' => true,
        'data' => [
            'items' => $items,
            'total_items' => (int)$totalItems,
            'total_price' => round($totalPrice, 2)
        ]
    ]);
    
} catch (Exception $e) {
    jsonResponse(500, ['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
