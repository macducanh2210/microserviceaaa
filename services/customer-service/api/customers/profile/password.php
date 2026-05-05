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

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    jsonResponse(405, ['success' => false, 'message' => 'Method not allowed']);
}

$input = getJsonInput();

if (empty($input['old_password']) || empty($input['new_password'])) {
    jsonResponse(400, ['success' => false, 'message' => 'Missing fields: old_password, new_password']);
}

if (strlen($input['new_password']) < 6) {
    jsonResponse(400, ['success' => false, 'message' => 'New password must be at least 6 characters']);
}

try {
    $pdo = getPDO();
    
    // Get current password
    $stmt = $pdo->prepare('SELECT PASSWORD FROM khachhang WHERE ID = ?');
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch();
    
    if (!$customer) {
        jsonResponse(404, ['success' => false, 'message' => 'Customer not found']);
    }
    
    // Verify old password
    if (!verifyPassword($input['old_password'], $customer['PASSWORD'])) {
        jsonResponse(401, ['success' => false, 'message' => 'Current password is incorrect']);
    }
    
    // Update password
    $newHashedPassword = hashPassword($input['new_password']);
    $stmt = $pdo->prepare('UPDATE khachhang SET PASSWORD = ? WHERE ID = ?');
    $stmt->execute([$newHashedPassword, $customerId]);
    
    jsonResponse(200, [
        'success' => true,
        'message' => 'Password changed successfully'
    ]);
    
} catch (Exception $e) {
    jsonResponse(500, ['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
