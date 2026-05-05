<?php

require_once __DIR__ . '/../../../db.php';

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

try {
    $pdo = getPDO();
    
    // Clear cart
    $stmt = $pdo->prepare('UPDATE gio_hang SET GHICHU = ? WHERE IDKHACHHANG = ?');
    $stmt->execute([json_encode(['items' => []]), $customerId]);
    
    jsonResponse(200, [
        'success' => true,
        'message' => 'Cart cleared',
        'data' => [
            'total_items' => 0
        ]
    ]);
    
} catch (Exception $e) {
    jsonResponse(500, ['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
