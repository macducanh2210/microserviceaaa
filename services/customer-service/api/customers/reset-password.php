<?php

declare(strict_types=1);

require_once __DIR__ . '/../../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, ['success' => false, 'message' => 'Method not allowed']);
}

$input = getJsonInput();
$email = trim($input['email'] ?? '');
$otp = trim($input['otp'] ?? '');
$newPassword = trim($input['new_password'] ?? '');

if (!validateEmail($email)) {
    jsonResponse(400, ['success' => false, 'message' => 'Email không hợp lệ']);
}

if (strlen($otp) !== 6 || !ctype_digit($otp)) {
    jsonResponse(400, ['success' => false, 'message' => 'OTP phải là 6 chữ số']);
}

if (strlen($newPassword) < 6) {
    jsonResponse(400, ['success' => false, 'message' => 'Mật khẩu mới phải ít nhất 6 ký tự']);
}

$pdo = getPDO();

try {
    // Check OTP
    $stmt = $pdo->prepare('SELECT ID, OTP, OTP_EXPIRES, OTP_TYPE FROM khachhang WHERE EMAIL = ? AND EMAIL_VERIFIED = 1');
    $stmt->execute([$email]);
    $customer = $stmt->fetch();

    if (!$customer) {
        jsonResponse(404, ['success' => false, 'message' => 'Email không tồn tại hoặc chưa xác thực']);
    }

    if ($customer['OTP'] !== $otp) {
        jsonResponse(400, ['success' => false, 'message' => 'OTP không đúng']);
    }

    if ($customer['OTP_TYPE'] !== 'forgot') {
        jsonResponse(400, ['success' => false, 'message' => 'OTP không hợp lệ cho chức năng này']);
    }

    $now = date('Y-m-d H:i:s');
    if ($customer['OTP_EXPIRES'] < $now) {
        jsonResponse(400, ['success' => false, 'message' => 'OTP đã hết hạn']);
    }

    // Update password and clear OTP
    $hashedPassword = hashPassword($newPassword);
    $stmt = $pdo->prepare('UPDATE khachhang SET PASSWORD = ?, OTP = NULL, OTP_EXPIRES = NULL, OTP_TYPE = NULL, UPDATED_AT = NOW() WHERE ID = ?');
    $stmt->execute([$hashedPassword, $customer['ID']]);

    jsonResponse(200, ['success' => true, 'message' => 'Mật khẩu đã được đặt lại thành công']);

} catch (Exception $e) {
    jsonResponse(500, ['success' => false, 'message' => 'Lỗi hệ thống', 'error' => $e->getMessage()]);
}