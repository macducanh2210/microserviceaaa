<?php

require_once __DIR__ . '/../../../db.php';

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

if (empty($input['product_detail_id'])) {
    jsonResponse(400, ['success' => false, 'message' => 'product_detail_id required']);
}

$detailId = (int)$input['product_detail_id'];

try {
    $pdo = getPDO();
    
    $stmt = $pdo->prepare('
        DELETE FROM danh_sach_yeu_thich 
        WHERE IDKHACHHANG = ? AND IDCHITIETSANPHAM = ?
    ');
    $stmt->execute([$customerId, $detailId]);
    
    if ($stmt->rowCount() === 0) {
        jsonResponse(404, ['success' => false, 'message' => 'Item not in wishlist']);
    }
    
    jsonResponse(200, [
        'success' => true,
        'message' => 'Removed from wishlist'
    ]);
    
} catch (Exception $e) {
    jsonResponse(500, ['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
