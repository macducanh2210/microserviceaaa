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
    $dbName = getenv('DB_NAME') ?: 'inventory_db';
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
    header('Access-Control-Allow-Headers: Content-Type, X-Internal-Key');
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

function iv_callJsonApi(string $method, string $url, ?array $payload = null, array $extraHeaders = []): array
{
    $ch = curl_init();
    $headers = array_merge(['Accept: application/json'], $extraHeaders);

    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => $headers,
    ];

    if ($method === 'POST') {
        $options[CURLOPT_POST] = true;
        $headers[] = 'Content-Type: application/json';
        $options[CURLOPT_HTTPHEADER] = $headers;
        $options[CURLOPT_POSTFIELDS] = json_encode($payload ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    curl_setopt_array($ch, $options);
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
        throw new RuntimeException('Invalid JSON response from: ' . $url);
    }

    if ($statusCode >= 400 || ($json['success'] ?? false) !== true) {
        $message = (string) ($json['message'] ?? ('HTTP ' . $statusCode));
        throw new RuntimeException($message);
    }

    return $json;
}

function iv_callUpdateStock(array $items): array
{
    $urls = [
        'http://product-service/api/products/update-stock.php',
        'http://localhost:8080/api/products/update-stock.php',
    ];

    $internalKey = trim((string) (getenv('INTERNAL_API_KEY') ?: ''));
    $headers = $internalKey !== '' ? ['X-Internal-Key: ' . $internalKey] : [];

    $lastError = 'Unknown stock API error';
    foreach ($urls as $url) {
        try {
            $json = iv_callJsonApi('POST', $url, ['items' => $items], $headers);
            return is_array($json['data'] ?? null) ? $json['data'] : [];
        } catch (Throwable $e) {
            $lastError = $e->getMessage();
        }
    }

    throw new RuntimeException('Stock update failed: ' . $lastError);
}

function iv_logImportExpense(int $purchaseOrderId, int $employeeId, float $totalAmount, int $supplierId, string $note = ''): array
{
    $amount = -abs($totalAmount);
    $refCode = 'IMPORT-PO-' . $purchaseOrderId;
    $payload = [
        'purpose' => 'Nhap hang nha cung cap #' . $supplierId,
        'employee_id' => $employeeId > 0 ? $employeeId : null,
        'amount' => $amount,
        'note' => $note,
        'status' => 1,
        'type' => 'import',
        'category' => 'import',
        'ref_code' => $refCode,
    ];

    $urls = [
        'http://expense-service/api/expenses/add.php',
        'http://localhost:8080/api/expenses/add.php',
    ];

    $internalKey = trim((string) (getenv('INTERNAL_API_KEY') ?: ''));
    $headers = $internalKey !== '' ? ['X-Internal-Key: ' . $internalKey] : [];

    $lastError = 'Unknown expense API error';
    foreach ($urls as $url) {
        try {
            $json = iv_callJsonApi('POST', $url, $payload, $headers);
            return is_array($json['data'] ?? null) ? $json['data'] : [];
        } catch (Throwable $e) {
            $lastError = $e->getMessage();
            if (str_contains(strtolower($lastError), 'ref_code da ton tai')) {
                return ['duplicate' => true, 'ref_code' => $refCode];
            }
        }
    }

    throw new RuntimeException('Expense log failed: ' . $lastError);
}
