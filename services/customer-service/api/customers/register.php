<?php

require_once __DIR__ . '/../../db.php';

$input = getJsonInput();

// Validate input
if (empty($input['email']) || empty($input['password']) || empty($input['fullname']) || empty($input['phone'])) {
    jsonResponse(400, ['success' => false, 'message' => 'Missing required fields: email, password, fullname, phone']);
}

$email = trim($input['email']);
$password = trim($input['password']);
$fullname = trim($input['fullname']);
$phone = trim($input['phone']);

// Validate email format
if (!validateEmail($email)) {
    jsonResponse(400, ['success' => false, 'message' => 'Invalid email format']);
}

// Validate password strength
if (strlen($password) < 6) {
    jsonResponse(400, ['success' => false, 'message' => 'Password must be at least 6 characters']);
}

try {
    $pdo = getPDO();
    
    // Check if email already exists
    $stmt = $pdo->prepare('SELECT ID FROM khachhang WHERE EMAIL = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        jsonResponse(409, ['success' => false, 'message' => 'Email already registered']);
    }
    
    // Generate OTP
    $otp = generateOTP(6);
    $otpExpires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    // Hash password
    $hashedPassword = hashPassword($password);
    
    // Insert customer
    $stmt = $pdo->prepare('
        INSERT INTO khachhang 
        (EMAIL, PASSWORD, HOTEN, SODIENTHOAI, OTP, OTP_EXPIRES, EMAIL_VERIFIED, TRANGTHAI) 
        VALUES (?, ?, ?, ?, ?, ?, 0, 1)
    ');
    
    $stmt->execute([$email, $hashedPassword, $fullname, $phone, $otp, $otpExpires]);
    $customerId = (int)$pdo->lastInsertId();
    
    // Create empty cart
    $stmt = $pdo->prepare('INSERT INTO gio_hang (IDKHACHHANG, GHICHU) VALUES (?, ?)');
    $stmt->execute([$customerId, json_encode(['items' => []])]);
    
    // Send OTP (mock - logs to file for testing)
    sendOTPEmail($email, $otp);
    
    jsonResponse(201, [
        'success' => true,
        'message' => 'Registration successful. OTP sent to email.',
        'customer_id' => $customerId,
        'email' => $email,
        'otp_expires_in_minutes' => 10
    ]);
    
} catch (Exception $e) {
    jsonResponse(500, ['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
