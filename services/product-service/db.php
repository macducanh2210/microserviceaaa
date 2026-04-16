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
    $dbName = getenv('DB_NAME') ?: 'product_db';
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
    header('Access-Control-Allow-Headers: Content-Type, X-Internal-Key, X-Actor-User-Id');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function getJsonInput(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    if (strncmp($raw, "\xEF\xBB\xBF", 3) === 0) {
        $raw = substr($raw, 3);
    }

    $data = json_decode($raw, true);
    if (is_array($data)) {
        return $data;
    }

    $fallback = [];
    parse_str($raw, $fallback);
    return is_array($fallback) ? $fallback : [];
}

function getRequestHeaderValue(string $name): string
{
    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    if (isset($_SERVER[$serverKey])) {
        return trim((string) $_SERVER[$serverKey]);
    }

    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            foreach ($headers as $key => $value) {
                if (strtolower((string) $key) === strtolower($name)) {
                    return trim((string) $value);
                }
            }
        }
    }

    return '';
}

function ps_callJsonApi(string $url): array
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);

    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch) ?: 'cURL execution failed';
        curl_close($ch);
        throw new RuntimeException('HTTP call failed: ' . $error);
    }

    $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode($response, true);
    if (!is_array($json)) {
        throw new RuntimeException('Invalid JSON response');
    }

    if ($statusCode >= 400 || ($json['success'] ?? false) !== true) {
        $message = (string) ($json['message'] ?? ('HTTP ' . $statusCode));
        throw new RuntimeException($message);
    }

    return $json;
}

function ps_resolveUserRole(int $userId): string
{
    $urls = [
        'http://user-service/api/users/get-role.php?user_id=' . $userId,
        'http://localhost:8080/api/users/get-role.php?user_id=' . $userId,
    ];

    $lastError = 'Cannot resolve role';
    foreach ($urls as $url) {
        try {
            $json = ps_callJsonApi($url);
            $role = (string) ($json['data']['role'] ?? '');
            if ($role !== '') {
                return $role;
            }
            throw new RuntimeException('Missing role in response');
        } catch (Throwable $e) {
            $lastError = $e->getMessage();
        }
    }

    throw new RuntimeException($lastError);
}

function ensureProductStockWriteAuthorized(array $input): void
{
    $configuredInternalKey = trim((string) (getenv('INTERNAL_API_KEY') ?: ''));
    $requestInternalKey = getRequestHeaderValue('X-Internal-Key');

    if ($configuredInternalKey !== '' && hash_equals($configuredInternalKey, $requestInternalKey)) {
        return;
    }

    $actorId = isset($input['user_id']) ? (int) $input['user_id'] : 0;
    if ($actorId <= 0) {
        $actorId = (int) getRequestHeaderValue('X-Actor-User-Id');
    }

    if ($actorId <= 0) {
        jsonResponse(403, [
            'success' => false,
            'message' => 'Bạn không có quyền thao tác tồn kho.',
            'data' => null,
        ]);
    }

    try {
        $role = ps_resolveUserRole($actorId);
    } catch (Throwable $e) {
        jsonResponse(401, [
            'success' => false,
            'message' => 'Không thể xác thực người dùng.',
            'error' => $e->getMessage(),
            'data' => null,
        ]);
    }

    if ($role !== 'admin') {
        jsonResponse(403, [
            'success' => false,
            'message' => 'Chỉ admin mới được phép cập nhật tồn kho.',
            'data' => null,
        ]);
    }
}

function ensureProductCrudWriteAuthorized(array $input): int
{
    $configuredInternalKey = trim((string) (getenv('INTERNAL_API_KEY') ?: ''));
    $requestInternalKey = getRequestHeaderValue('X-Internal-Key');

    if ($configuredInternalKey !== '' && hash_equals($configuredInternalKey, $requestInternalKey)) {
        $actorId = isset($input['user_id']) ? (int) $input['user_id'] : 0;
        if ($actorId > 0) {
            return $actorId;
        }
        $headerActorId = (int) getRequestHeaderValue('X-Actor-User-Id');
        return $headerActorId > 0 ? $headerActorId : 0;
    }

    $actorId = isset($input['user_id']) ? (int) $input['user_id'] : 0;
    if ($actorId <= 0) {
        $actorId = (int) getRequestHeaderValue('X-Actor-User-Id');
    }

    if ($actorId <= 0) {
        jsonResponse(403, [
            'success' => false,
            'message' => 'Bạn không có quyền thao tác sản phẩm.',
            'data' => null,
        ]);
    }

    try {
        $role = ps_resolveUserRole($actorId);
    } catch (Throwable $e) {
        jsonResponse(401, [
            'success' => false,
            'message' => 'Không thể xác thực người dùng.',
            'error' => $e->getMessage(),
            'data' => null,
        ]);
    }

    if ($role !== 'admin' && $role !== 'staff') {
        jsonResponse(403, [
            'success' => false,
            'message' => 'Chỉ admin hoặc staff mới được phép quản lý sản phẩm.',
            'data' => null,
        ]);
    }

    return $actorId;
}
