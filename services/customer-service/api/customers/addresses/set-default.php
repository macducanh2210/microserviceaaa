<?php

require_once __DIR__ . '/../../../db.php';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
preg_match('/\/api\/customers\/(\d+)\/addresses\/(\d+)\//', $path, $matches);
$requestedCustomerId = isset($matches[1]) ? (int)$matches[1] : 0;
$addressId = isset($matches[2]) ? (int)$matches[2] : 0;

if (!$requestedCustomerId || !$addressId) {
    jsonResponse(400, ['success' => false, 'message' => 'Customer ID and Address ID required']);
}

$customerId = requireCustomerSession($requestedCustomerId);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, ['success' => false, 'message' => 'Method not allowed']);
}

try {
    $pdo = getPDO();
    
    // Verify address belongs to customer
    $stmt = $pdo->prepare('SELECT ID FROM dia_chi_khachhang WHERE IDKHACHHANG = ? AND ID = ?');
    $stmt->execute([$customerId, $addressId]);
    if (!$stmt->fetch()) {
        jsonResponse(404, ['success' => false, 'message' => 'Address not found']);
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Set all to non-default
    $stmt = $pdo->prepare('UPDATE dia_chi_khachhang SET LA_MAC_DINH = 0 WHERE IDKHACHHANG = ?');
    $stmt->execute([$customerId]);
    
    // Set this as default
    $stmt = $pdo->prepare('UPDATE dia_chi_khachhang SET LA_MAC_DINH = 1 WHERE ID = ?');
    $stmt->execute([$addressId]);
    
    $pdo->commit();
    
    jsonResponse(200, [
        'success' => true,
        'message' => 'Default address updated'
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonResponse(500, ['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
