<?php

declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/_service.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResponse(200, [
        'success' => true,
        'message' => 'Preflight OK',
        'data' => null,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, [
        'success' => false,
        'message' => 'Method not allowed. Use POST.',
        'data' => null,
    ]);
}

function fetchProductDetailByCurl(int $productId): array
{
    $urls = [
        'http://product-service/get-detail.php?id=' . $productId,
        'http://product-service/api/products/get-detail.php?id=' . $productId,
        'http://localhost:8080/api/products/get-detail.php?id=' . $productId,
    ];

    $lastError = 'Unknown error';

    foreach ($urls as $url) {
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
            $lastError = curl_error($ch) ?: 'cURL execution failed';
            curl_close($ch);
            continue;
        }

        $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode >= 400) {
            $lastError = 'Product service returned HTTP ' . $statusCode;
            continue;
        }

        $json = json_decode($response, true);
        if (!is_array($json) || !isset($json['success'])) {
            $lastError = 'Invalid JSON from product service';
            continue;
        }

        if (($json['success'] ?? false) !== true || !isset($json['data']) || !is_array($json['data'])) {
            $lastError = (string)($json['message'] ?? 'Product not found');
            continue;
        }

        return $json['data'];
    }

    throw new RuntimeException('Cannot verify product via Product Service: ' . $lastError);
}

function fetchProductByDetailIdCurl(int $detailId): array
{
    $url = 'http://product-service/api/products/get-by-detail.php?detail_id=' . $detailId;

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
        throw new RuntimeException('Cannot verify detail via Product Service: ' . $error);
    }

    $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode($response, true);
    if ($statusCode >= 400 || !is_array($json) || ($json['success'] ?? false) !== true) {
        $message = is_array($json) ? (string) ($json['message'] ?? 'Detail not found') : ('HTTP ' . $statusCode);
        throw new RuntimeException('Cannot verify detail via Product Service: ' . $message);
    }

    return $json['data'];
}

function callProductStockApi(string $action, int $detailId, int $quantity): array
{
    $url = 'http://product-service/api/products/' . $action . '.php';
    $internalKey = trim((string) (getenv('INTERNAL_API_KEY') ?: ''));
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($internalKey !== '') {
        $headers[] = 'X-Internal-Key: ' . $internalKey;
    }

    $payload = json_encode([
        'detail_id' => $detailId,
        'quantity' => $quantity,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $payload,
    ]);

    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch) ?: 'cURL execution failed';
        curl_close($ch);
        throw new RuntimeException('Stock API call failed: ' . $error);
    }

    $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode($response, true);
    if (!is_array($json)) {
        throw new RuntimeException('Stock API returned invalid JSON');
    }

    if ($statusCode >= 400 || ($json['success'] ?? false) !== true) {
        throw new RuntimeException((string) ($json['message'] ?? ('Stock API HTTP ' . $statusCode)));
    }

    return $json;
}

function ensureIdempotencyTable(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS order_idempotency (
            ID BIGINT NOT NULL AUTO_INCREMENT,
            IDEMPOTENCY_KEY VARCHAR(128) NOT NULL,
            ORDER_ID INT DEFAULT NULL,
            STATUS VARCHAR(20) NOT NULL DEFAULT "PENDING",
            CREATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (ID),
            UNIQUE KEY UK_ORDER_IDEMPOTENCY_KEY (IDEMPOTENCY_KEY)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

$input = getJsonInput();

if (!is_array($input) || count($input) === 0) {
    $input = $_POST;
}

$userId = isset($input['user_id']) ? (int)$input['user_id'] : (isset($input['userId']) ? (int)$input['userId'] : 0);
$items = $input['items'] ?? ($input['cart'] ?? []);
$idempotencyKey = trim((string)($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ($input['idempotency_key'] ?? '')));

if ($userId <= 0 || !is_array($items) || count($items) === 0) {
    jsonResponse(400, [
        'success' => false,
        'message' => 'Payload không hợp lệ. Cần user_id và mảng items.',
        'data' => null,
    ]);
}

os_requireRoleByUserId($userId, ['customer', 'staff', 'admin']);

try {
    $pdo = getPDO();
    ensureIdempotencyTable($pdo);

    if ($idempotencyKey !== '') {
        try {
            $insertIdem = $pdo->prepare('INSERT INTO order_idempotency (IDEMPOTENCY_KEY, STATUS) VALUES (:key, "PENDING")');
            $insertIdem->execute(['key' => $idempotencyKey]);
        } catch (Throwable $idemInsertError) {
            $existingStmt = $pdo->prepare('SELECT ORDER_ID, STATUS FROM order_idempotency WHERE IDEMPOTENCY_KEY = :key LIMIT 1');
            $existingStmt->execute(['key' => $idempotencyKey]);
            $existing = $existingStmt->fetch();

            if ($existing && (string)$existing['STATUS'] === 'SUCCESS' && (int)$existing['ORDER_ID'] > 0) {
                jsonResponse(200, [
                    'success' => true,
                    'message' => 'Yêu cầu đã được xử lý trước đó (idempotent).',
                    'data' => [
                        'order_id' => (int)$existing['ORDER_ID'],
                        'user_id' => $userId,
                        'idempotent_reuse' => true,
                    ],
                ]);
            }

            jsonResponse(409, [
                'success' => false,
                'message' => 'Yêu cầu đang được xử lý, vui lòng thử lại sau.',
                'data' => null,
            ]);
        }
    }

    $pdo->beginTransaction();

    $validatedItems = [];
    $totalAmount = 0.0;
    $reservedStocks = [];

    foreach ($items as $idx => $item) {
        $productId = isset($item['product_id']) ? (int)$item['product_id'] : 0;
        $detailIdInput = isset($item['detail_id']) ? (int)$item['detail_id'] : 0;
        $legacyDetailId = isset($item['idchitiet']) ? (int)$item['idchitiet'] : 0;

        if ($detailIdInput <= 0 && $legacyDetailId > 0) {
            $detailIdInput = $legacyDetailId;
        }

        $quantity = isset($item['quantity']) ? (int)$item['quantity'] : (isset($item['soluong']) ? (int)$item['soluong'] : 0);

        if (($productId <= 0 && $detailIdInput <= 0) || $quantity <= 0) {
            throw new InvalidArgumentException('Item tại vị trí ' . $idx . ' không hợp lệ.');
        }

        $productDetail = null;
        $resolvedByDetail = $detailIdInput > 0;

        if ($productId > 0) {
            try {
                $productDetail = fetchProductDetailByCurl($productId);
            } catch (Throwable $lookupErr) {
                if ($detailIdInput > 0) {
                    $productDetail = fetchProductByDetailIdCurl($detailIdInput);
                    $resolvedByDetail = true;
                } else {
                    // Legacy cart can send detail_id in product_id field.
                    $productDetail = fetchProductByDetailIdCurl($productId);
                    $resolvedByDetail = true;
                }
            }
        } elseif ($detailIdInput > 0) {
            $productDetail = fetchProductByDetailIdCurl($detailIdInput);
        } else {
            throw new InvalidArgumentException('Item tại vị trí ' . $idx . ' không hợp lệ.');
        }

        $variants = $productDetail['variants'] ?? [];
        if (!is_array($variants) || count($variants) === 0) {
            throw new RuntimeException('Sản phẩm #' . $productId . ' không có biến thể để đặt hàng.');
        }

        $selectedVariant = null;
        if ($resolvedByDetail) {
            if ($detailIdInput > 0) {
                foreach ($variants as $variant) {
                    if ((int)($variant['detail_id'] ?? 0) === $detailIdInput && (int)($variant['stock'] ?? 0) >= $quantity) {
                        $selectedVariant = $variant;
                        break;
                    }
                }
            }

            if ($selectedVariant === null) {
                foreach ($variants as $variant) {
                    if ((int)($variant['stock'] ?? 0) >= $quantity) {
                        $selectedVariant = $variant;
                        break;
                    }
                }
            }
        } else {
            foreach ($variants as $variant) {
                $stock = (int)($variant['stock'] ?? 0);
                if ($stock >= $quantity) {
                    $selectedVariant = $variant;
                    break;
                }
            }
        }

        if ($selectedVariant === null) {
            throw new RuntimeException('Sản phẩm #' . $productId . ' không đủ tồn kho.');
        }

        $detailId = (int)($selectedVariant['detail_id'] ?? 0);
        $unitPrice = (float)($selectedVariant['price'] ?? 0);

        if ($detailId <= 0) {
            throw new RuntimeException('Sản phẩm #' . $productId . ' không xác định được detail_id.');
        }

        $validatedItems[] = [
            'product_id' => (int)($productDetail['product_id'] ?? $productId),
            'detail_id' => $detailId,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $unitPrice * $quantity,
        ];

        $totalAmount += $unitPrice * $quantity;
    }

    // Reserve stock in Product Service before writing local order data.
    foreach ($validatedItems as $line) {
        callProductStockApi('decrease-stock', (int) $line['detail_id'], (int) $line['quantity']);
        $reservedStocks[] = [
            'detail_id' => (int) $line['detail_id'],
            'quantity' => (int) $line['quantity'],
        ];
    }

    $orderSnapshot = [
        'source' => 'microservice-order-api',
        'created_at' => date('c'),
        'items' => $validatedItems,
        'total_amount' => $totalAmount,
    ];

    $orderStmt = $pdo->prepare(
        'INSERT INTO hoadonthanhtoan (IDKHACHHANG, IDNHANVIEN, DIEMDADOI, GHICHU, TREMOVE) VALUES (:user_id, NULL, 0, :note, 1)'
    );
    $orderStmt->execute([
        'user_id' => $userId,
        'note' => json_encode($orderSnapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    $orderId = (int)$pdo->lastInsertId();

    $detailStmt = $pdo->prepare(
        'INSERT INTO chitiethoadonthanhtoan (IDCHITIETSANPHAM, IDHOADONTHANHTOAN, SOLUONG, GIAMGIATHEM, TRAHANG) VALUES (:detail_id, :order_id, :quantity, 0, 0)'
    );

    foreach ($validatedItems as $line) {
        $detailStmt->execute([
            'detail_id' => $line['detail_id'],
            'order_id' => $orderId,
            'quantity' => $line['quantity'],
        ]);
    }

    $pdo->commit();

    if ($idempotencyKey !== '') {
        $doneStmt = $pdo->prepare('UPDATE order_idempotency SET ORDER_ID = :order_id, STATUS = "SUCCESS" WHERE IDEMPOTENCY_KEY = :key');
        $doneStmt->execute([
            'order_id' => $orderId,
            'key' => $idempotencyKey,
        ]);
    }

    jsonResponse(201, [
        'success' => true,
        'message' => 'Tạo đơn hàng thành công.',
        'data' => [
            'order_id' => $orderId,
            'user_id' => $userId,
            'total_amount' => $totalAmount,
            'items' => $validatedItems,
        ],
    ]);
} catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $idempotencyKey !== '') {
            try {
                $failStmt = $pdo->prepare('UPDATE order_idempotency SET STATUS = "FAILED" WHERE IDEMPOTENCY_KEY = :key');
                $failStmt->execute(['key' => $idempotencyKey]);
            } catch (Throwable $ignore) {
                // no-op
            }
        }

    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Best-effort stock compensation if any reservation already happened.
    if (isset($reservedStocks) && is_array($reservedStocks)) {
        foreach ($reservedStocks as $reserved) {
            try {
                callProductStockApi('increase-stock', (int) $reserved['detail_id'], (int) $reserved['quantity']);
            } catch (Throwable $compensateError) {
                // Ignore compensation error here; API still returns the original failure reason.
            }
        }
    }

    $status = ($e instanceof InvalidArgumentException || $e instanceof RuntimeException) ? 400 : 500;
    jsonResponse($status, [
        'success' => false,
        'message' => 'Không thể tạo đơn hàng.',
        'error' => $e->getMessage(),
        'data' => null,
    ]);
}
