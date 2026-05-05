<?php

require_once __DIR__ . '/../../db.php';

$input = getJsonInput();

// Validate input
if (empty($input['email']) || empty($input['password'])) {
    jsonResponse(400, ['success' => false, 'message' => 'Missing required fields: email, password']);
}

$email = trim($input['email']);
$password = trim($input['password']);

try {
    $pdo = getPDO();
    
    // Find customer
    $stmt = $pdo->prepare('
        SELECT ID, HOTEN, EMAIL, PASSWORD, EMAIL_VERIFIED, TICHDIEM 
        FROM khachhang 
        WHERE EMAIL = ? AND TRANGTHAI = 1
    ');
    $stmt->execute([$email]);
    $customer = $stmt->fetch();
    
    if (!$customer) {
        jsonResponse(401, ['success' => false, 'message' => 'Email or password incorrect']);
    }
    
    if (!$customer['EMAIL_VERIFIED']) {
        jsonResponse(403, ['success' => false, 'message' => 'Please verify your email first']);
    }
    
    // Verify password
    if (!verifyPassword($password, $customer['PASSWORD'])) {
        jsonResponse(401, ['success' => false, 'message' => 'Email or password incorrect']);
    }
    
    // Set session (or return token in future)
    session_start();
    $_SESSION['customer_id'] = $customer['ID'];
    $_SESSION['customer_email'] = $customer['EMAIL'];
    
    jsonResponse(200, [
        'success' => true,
        'message' => 'Login successful',
        'customer_id' => (int)$customer['ID'],
        'full_name' => $customer['HOTEN'],
        'email' => $customer['EMAIL'],
        'loyalty_points' => (int)$customer['TICHDIEM']
    ]);
    
} catch (Exception $e) {
    jsonResponse(500, ['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
