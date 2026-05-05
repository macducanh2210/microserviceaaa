<?php

declare(strict_types=1);

function getPDO(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $dbName = getenv('DB_NAME') ?: 'customer_db';
    $user = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASSWORD') ?: '';

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $dbName);

    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function jsonResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function requireCustomerSession(int $requestedCustomerId = 0): int
{
    // Check for internal API key first
    $internalKey = os_getInternalApiKey();
    $providedKey = $_SERVER['HTTP_X_INTERNAL_KEY'] ?? '';
    if ($internalKey !== '' && $providedKey === $internalKey) {
        // Internal call - allow access to any customer data
        return $requestedCustomerId > 0 ? $requestedCustomerId : 0;
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['customer_id'])) {
        jsonResponse(401, ['success' => false, 'message' => 'Unauthorized: Please login first']);
    }
    
    $sessionId = (int)$_SESSION['customer_id'];
    
    if ($requestedCustomerId > 0 && $requestedCustomerId !== $sessionId) {
        jsonResponse(403, ['success' => false, 'message' => 'Forbidden: You can only access your own data']);
    }
    
    return $sessionId;
}

function os_getInternalApiKey(): string
{
    $key = (string)getenv('INTERNAL_API_KEY');
    if ($key === '') {
        $key = (string)($_ENV['INTERNAL_API_KEY'] ?? '');
    }
    if ($key === '') {
        $key = (string)($_SERVER['INTERNAL_API_KEY'] ?? '');
    }
    return trim($key);
}

function getJsonInput(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function generateOTP(int $length = 6): string
{
    return str_pad((string)random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

function sendOTPEmail(string $email, string $otp): bool
{
    $fromEmail = getenv('MAIL_FROM') ?: 'no-reply@uttmart.local';
    $fromName = getenv('MAIL_FROM_NAME') ?: 'UTTMart';
    $subject = 'Mã OTP xác thực đăng ký UTTMart';
    $body = "Mã OTP của bạn là: $otp\n\nVui lòng không chia sẻ mã này với người khác.\nMã có hiệu lực trong 10 phút.";

    // Nếu cấu hình SMTP, dùng SMTP
    $smtpHost = getenv('SMTP_HOST');
    if ($smtpHost) {
        $smtpPort = (int)(getenv('SMTP_PORT') ?: 587);
        $smtpUser = getenv('SMTP_USER');
        $smtpPass = getenv('SMTP_PASSWORD');
        $sent = sendEmailViaSmtp($smtpHost, $smtpPort, $smtpUser, $smtpPass, $fromEmail, $fromName, $email, $subject, $body);
        if ($sent) {
            return true;
        }
        // Nếu gửi SMTP thất bại, ghi log để debug và vẫn lưu mã OTP
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        $logFile = $logDir . '/otp_log.txt';
        $timestamp = date('Y-m-d H:i:s');
        $message = sprintf("[%s] SMTP FAILED TO: %s OTP: %s\n", $timestamp, $email, $otp);
        file_put_contents($logFile, $message, FILE_APPEND);
        $message = sprintf("[%s] FALLBACK TO LOG TO: %s OTP: %s\n", $timestamp, $email, $otp);
        file_put_contents($logFile, $message, FILE_APPEND);
        return true;
    }

    // Nếu có hàm mail(), thử gửi
    if (function_exists('mail')) {
        $headers = sprintf("From: %s <%s>\r\nReply-To: %s\r\nContent-Type: text/plain; charset=UTF-8\r\n", $fromName, $fromEmail, $fromEmail);
        if (mail($email, $subject, $body, $headers)) {
            return true;
        }
    }

    // Fallback: log OTP để debug khi chưa có mail server
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    $logFile = $logDir . '/otp_log.txt';
    $message = sprintf("[%s] TO: %s OTP: %s\n", date('Y-m-d H:i:s'), $email, $otp);
    file_put_contents($logFile, $message, FILE_APPEND);
    return true;
}

function sendEmailViaSmtp(string $host, int $port, ?string $user, ?string $pass, string $fromEmail, string $fromName, string $toEmail, string $subject, string $body): bool
{
    $transport = $port === 465 ? 'ssl://' : '';
    $socket = stream_socket_client($transport . $host . ':' . $port, $errno, $errstr, 10);
    if (!$socket) {
        return false;
    }

    stream_set_timeout($socket, 10);

    $readLine = fn() => trim(fgets($socket, 514));
    $readResponse = function() use ($socket) {
        $response = '';
        while (($line = fgets($socket, 514)) !== false) {
            $response .= trim($line) . "\n";
            if (isset($line[3]) && $line[3] !== '-') {
                break;
            }
        }
        return trim($response);
    };
    $write = fn($cmd) => fwrite($socket, $cmd . "\r\n");

    $response = $readLine();
    if (strpos($response, '220') !== 0) {
        fclose($socket);
        return false;
    }

    $write("EHLO localhost");
    $response = $readResponse();
    if (strpos($response, '250') !== 0) {
        fclose($socket);
        return false;
    }

    if ($port === 587 && stripos($response, 'STARTTLS') !== false) {
        $write('STARTTLS');
        $response = $readLine();
        if (strpos($response, '220') !== 0) {
            fclose($socket);
            return false;
        }

        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            return false;
        }

        $write("EHLO localhost");
        $response = $readResponse();
        if (strpos($response, '250') !== 0) {
            fclose($socket);
            return false;
        }
    }

    if ($user && $pass) {
        $write('AUTH LOGIN');
        $response = $readLine();
        if (strpos($response, '334') !== 0) {
            fclose($socket);
            return false;
        }

        $write(base64_encode($user));
        $response = $readLine();
        if (strpos($response, '334') !== 0) {
            fclose($socket);
            return false;
        }

        $write(base64_encode($pass));
        $response = $readLine();
        if (strpos($response, '235') !== 0) {
            fclose($socket);
            return false;
        }
    }

    $write("MAIL FROM:<{$fromEmail}>");
    $response = $readLine();
    if (strpos($response, '250') !== 0) {
        fclose($socket);
        return false;
    }

    $write("RCPT TO:<{$toEmail}>");
    $response = $readLine();
    if (strpos($response, '250') !== 0 && strpos($response, '251') !== 0) {
        fclose($socket);
        return false;
    }

    $write('DATA');
    $response = $readLine();
    if (strpos($response, '354') !== 0) {
        fclose($socket);
        return false;
    }

    $headers = [];
    $headers[] = sprintf('From: %s <%s>', $fromName, $fromEmail);
    $headers[] = sprintf('To: %s', $toEmail);
    $headers[] = sprintf('Subject: %s', $subject);
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $smtpData = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.";
    $write($smtpData);
    $response = $readLine();
    $write('QUIT');
    fclose($socket);

    return strpos($response, '250') === 0;
}

function validateEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function hashPassword(string $password): string
{
    // Using simple MD5 for now, should use bcrypt in production
    return md5($password);
}

function verifyPassword(string $plainPassword, string $hashedPassword): bool
{
    return md5($plainPassword) === $hashedPassword;
}
