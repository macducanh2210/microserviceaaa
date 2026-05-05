<?php

declare(strict_types=1);

require_once __DIR__ . '/../../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, ['success' => false, 'message' => 'Method not allowed']);
}

$input = getJsonInput();
$email = trim($input['email'] ?? '');

if (!validateEmail($email)) {
    jsonResponse(400, ['success' => false, 'message' => 'Email không hợp lệ']);
}

$pdo = getPDO();

try {
    // Check if customer exists
    $stmt = $pdo->prepare('SELECT ID, EMAIL_VERIFIED FROM khachhang WHERE EMAIL = ?');
    $stmt->execute([$email]);
    $customer = $stmt->fetch();

    if (!$customer) {
        jsonResponse(404, ['success' => false, 'message' => 'Email không tồn tại trong hệ thống']);
    }

    if (!$customer['EMAIL_VERIFIED']) {
        jsonResponse(400, ['success' => false, 'message' => 'Email chưa được xác thực. Vui lòng xác thực email trước']);
    }

    // Generate OTP
    $otp = generateOTP();
    $otpExpires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    // Update customer with OTP for forgot password
    $stmt = $pdo->prepare('UPDATE khachhang SET OTP = ?, OTP_EXPIRES = ?, OTP_TYPE = ? WHERE EMAIL = ?');
    $stmt->execute([$otp, $otpExpires, 'forgot', $email]);

    // Send OTP email
    $subject = 'Mã OTP đặt lại mật khẩu UTTMart';
    $body = "Mã OTP đặt lại mật khẩu của bạn là: $otp\n\nVui lòng không chia sẻ mã này với người khác.\nMã có hiệu lực trong 10 phút.";

    // Modify sendOTPEmail to accept custom subject and body
    $sent = sendCustomOTPEmail($email, $subject, $body);

    if (!$sent) {
        jsonResponse(500, ['success' => false, 'message' => 'Không thể gửi email. Vui lòng thử lại sau']);
    }

    jsonResponse(200, ['success' => true, 'message' => 'Mã OTP đã được gửi đến email của bạn']);

} catch (Exception $e) {
    jsonResponse(500, ['success' => false, 'message' => 'Lỗi hệ thống', 'error' => $e->getMessage()]);
}

function sendCustomOTPEmail(string $email, string $subject, string $body): bool
{
    $fromEmail = getenv('MAIL_FROM') ?: 'no-reply@uttmart.local';
    $fromName = getenv('MAIL_FROM_NAME') ?: 'UTTMart';

    // If SMTP configured, use SMTP
    $smtpHost = getenv('SMTP_HOST');
    if ($smtpHost) {
        $smtpPort = (int)(getenv('SMTP_PORT') ?: 587);
        $smtpUser = getenv('SMTP_USER');
        $smtpPass = getenv('SMTP_PASSWORD');
        return sendEmailViaSmtp($smtpHost, $smtpPort, $smtpUser, $smtpPass, $fromEmail, $fromName, $email, $subject, $body);
    }

    // If mail() available, use it
    if (function_exists('mail')) {
        $headers = sprintf("From: %s <%s>\r\nReply-To: %s\r\nContent-Type: text/plain; charset=UTF-8\r\n", $fromName, $fromEmail, $fromEmail);
        return mail($email, $subject, $body, $headers);
    }

    // Fallback: log
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    $logFile = $logDir . '/otp_log.txt';
    $message = sprintf("[%s] TO: %s SUBJECT: %s BODY: %s\n", date('Y-m-d H:i:s'), $email, $subject, $body);
    file_put_contents($logFile, $message, FILE_APPEND);
    return true;
}