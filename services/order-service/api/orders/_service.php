<?php

declare(strict_types=1);

require_once __DIR__ . '/../../db.php';

function os_callJsonApi(string $method, string $url, ?array $payload = null, array $extraHeaders = []): array
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

function os_fetchProductDetailByProductId(int $productId): array
{
    $urls = [
        'http://product-service/api/products/get-detail.php?id=' . $productId,
        'http://localhost:8080/api/products/get-detail.php?id=' . $productId,
    ];

    $lastError = 'Product not found';
    foreach ($urls as $url) {
        try {
            $json = os_callJsonApi('GET', $url);
            return $json['data'];
        } catch (Throwable $e) {
            $lastError = $e->getMessage();
        }
    }

    throw new RuntimeException('Cannot verify product #' . $productId . ': ' . $lastError);
}

function os_fetchProductByDetailId(int $detailId): array
{
    $urls = [
        'http://product-service/api/products/get-by-detail.php?detail_id=' . $detailId,
        'http://localhost:8080/api/products/get-by-detail.php?detail_id=' . $detailId,
    ];

    $lastError = 'Detail not found';
    foreach ($urls as $url) {
        try {
            $json = os_callJsonApi('GET', $url);
            return $json['data'];
        } catch (Throwable $e) {
            $lastError = $e->getMessage();
        }
    }

    throw new RuntimeException('Cannot verify detail #' . $detailId . ': ' . $lastError);
}

function os_callStockApi(string $action, int $detailId, int $quantity): void
{
    $urls = [
        'http://product-service/api/products/' . $action . '.php',
        'http://localhost:8080/api/products/' . $action . '.php',
    ];

    $lastError = 'Unknown stock API error';
    $internalKey = trim((string) (getenv('INTERNAL_API_KEY') ?: ''));
    $headers = $internalKey !== '' ? ['X-Internal-Key: ' . $internalKey] : [];

    foreach ($urls as $url) {
        try {
            os_callJsonApi('POST', $url, [
                'detail_id' => $detailId,
                'quantity' => $quantity,
            ], $headers);
            return;
        } catch (Throwable $e) {
            $lastError = $e->getMessage();
        }
    }

    throw new RuntimeException('Stock API failed: ' . $lastError);
}

function os_resolveItems(array $items): array
{
    $resolved = [];
    $totalAmount = 0.0;

    foreach ($items as $idx => $item) {
        $qty = isset($item['quantity']) ? (int) $item['quantity'] : (isset($item['soluong']) ? (int) $item['soluong'] : 0);
        $productId = isset($item['product_id']) ? (int) $item['product_id'] : 0;
        $detailIdInput = isset($item['detail_id']) ? (int) $item['detail_id'] : (isset($item['idchitiet']) ? (int) $item['idchitiet'] : 0);

        if ($qty <= 0 || ($productId <= 0 && $detailIdInput <= 0)) {
            throw new InvalidArgumentException('Item tại vị trí ' . $idx . ' không hợp lệ.');
        }

        $productData = $productId > 0
            ? os_fetchProductDetailByProductId($productId)
            : os_fetchProductByDetailId($detailIdInput);

        $variants = $productData['variants'] ?? [];
        if (!is_array($variants) || count($variants) === 0) {
            throw new RuntimeException('Sản phẩm không có biến thể để đặt hàng.');
        }

        $picked = null;
        if ($detailIdInput > 0) {
            foreach ($variants as $variant) {
                if ((int) ($variant['detail_id'] ?? 0) === $detailIdInput && (int) ($variant['stock'] ?? 0) >= $qty) {
                    $picked = $variant;
                    break;
                }
            }
        }

        if ($picked === null) {
            foreach ($variants as $variant) {
                if ((int) ($variant['stock'] ?? 0) >= $qty) {
                    $picked = $variant;
                    break;
                }
            }
        }

        if ($picked === null) {
            throw new RuntimeException('Sản phẩm #' . (int) ($productData['product_id'] ?? $productId) . ' không đủ tồn kho.');
        }

        $detailId = (int) ($picked['detail_id'] ?? 0);
        $unitPrice = (float) ($picked['price'] ?? 0);
        if ($detailId <= 0) {
            throw new RuntimeException('Không xác định được detail_id hợp lệ.');
        }

        $line = [
            'product_id' => (int) ($productData['product_id'] ?? $productId),
            'detail_id' => $detailId,
            'quantity' => $qty,
            'unit_price' => $unitPrice,
            'line_total' => $unitPrice * $qty,
        ];

        $resolved[] = $line;
        $totalAmount += $line['line_total'];
    }

    return [
        'items' => $resolved,
        'total_amount' => $totalAmount,
    ];
}

function os_getOrderOwnedByUser(PDO $pdo, int $orderId, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT ID, IDKHACHHANG, NGAYTHANHTOAN, GHICHU, TREMOVE FROM hoadonthanhtoan WHERE ID = :order_id AND IDKHACHHANG = :user_id LIMIT 1');
    $stmt->execute([
        'order_id' => $orderId,
        'user_id' => $userId,
    ]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function os_getOrderById(PDO $pdo, int $orderId): ?array
{
    $stmt = $pdo->prepare('SELECT ID, IDKHACHHANG, NGAYTHANHTOAN, GHICHU, TREMOVE FROM hoadonthanhtoan WHERE ID = :order_id LIMIT 1');
    $stmt->execute(['order_id' => $orderId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function os_getOrderDetailRows(PDO $pdo, int $orderId): array
{
    $stmt = $pdo->prepare('SELECT ID, IDCHITIETSANPHAM, SOLUONG FROM chitiethoadonthanhtoan WHERE IDHOADONTHANHTOAN = :order_id ORDER BY ID ASC');
    $stmt->execute(['order_id' => $orderId]);
    return $stmt->fetchAll();
}

function os_fetchUserContext(int $userId): array
{
    $urls = [
        'http://user-service/api/users/get-role.php?user_id=' . $userId,
        'http://localhost:8080/api/users/get-role.php?user_id=' . $userId,
    ];

    $lastError = 'Cannot resolve role';
    foreach ($urls as $url) {
        try {
            $json = os_callJsonApi('GET', $url);
            $data = $json['data'] ?? null;
            if (!is_array($data) || !isset($data['role'])) {
                throw new RuntimeException('Role payload is invalid');
            }
            return $data;
        } catch (Throwable $e) {
            $lastError = $e->getMessage();
        }
    }

    throw new RuntimeException('Không thể xác thực vai trò người dùng: ' . $lastError);
}

function os_requireRoleByUserId(int $userId, array $allowedRoles): array
{
    if ($userId <= 0) {
        jsonResponse(400, ['success' => false, 'message' => 'user_id không hợp lệ.', 'data' => null]);
    }

    try {
        $user = os_fetchUserContext($userId);
    } catch (Throwable $e) {
        jsonResponse(401, ['success' => false, 'message' => 'Không thể xác thực người dùng.', 'error' => $e->getMessage(), 'data' => null]);
    }

    $role = (string) ($user['role'] ?? 'customer');
    if (!in_array($role, $allowedRoles, true)) {
        jsonResponse(403, ['success' => false, 'message' => 'Bạn không có quyền thực hiện thao tác này.', 'data' => null]);
    }

    return $user;
}
