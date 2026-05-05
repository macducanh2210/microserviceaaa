<?php

require_once __DIR__ . '/../../db.php';

$input = getJsonInput();

// Validate input
if (empty($input['email']) || empty($input['otp'])) {
    jsonResponse(400, ['success' => false, 'message' => 'Missing required fields: email, otp']);
}

$email = trim($input['email']);
$otp = trim($input['otp']);

try {
    $pdo = getPDO();
    
    // Find customer by email
    $stmt = $pdo->prepare('
        SELECT ID, OTP, OTP_EXPIRES, EMAIL_VERIFIED 
        FROM khachhang 
        WHERE EMAIL = ?
    ');
    $stmt->execute([$email]);
    $customer = $stmt->fetch();
    
    if (!$customer) {
        jsonResponse(404, ['success' => false, 'message' => 'Email not found']);
    }
    
    if ($customer['EMAIL_VERIFIED']) {
        jsonResponse(400, ['success' => false, 'message' => 'Email already verified']);
    }
    
    // Check OTP
    if ($customer['OTP'] !== $otp) {
        jsonResponse(400, ['success' => false, 'message' => 'Invalid OTP']);
    }
    
    // Check OTP expiration
    if (new DateTime() > new DateTime($customer['OTP_EXPIRES'])) {
        jsonResponse(400, ['success' => false, 'message' => 'OTP expired']);
    }
    
    // Mark as verified and clear OTP
    $stmt = $pdo->prepare('
        UPDATE khachhang 
        SET EMAIL_VERIFIED = 1, OTP = NULL, OTP_EXPIRES = NULL 
        WHERE ID = ?
    ');
    $stmt->execute([$customer['ID']]);
    
    jsonResponse(200, [
        'success' => true,
        'message' => 'Email verified successfully. You can now login.',
        'customer_id' => (int)$customer['ID']
    ]);
    
} catch (Exception $e) {
    jsonResponse(500, ['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
