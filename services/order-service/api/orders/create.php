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

function clearCustomerCart(int $customerId): void
{
    $url = "http://api-gateway/api/customers/{$customerId}/cart/clear.php";
    $internalKey = os_getInternalApiKey();
    $headers = ['Content-Type: application/json'];
    if ($internalKey !== '') {
        $headers[] = 'X-Internal-Key: ' . $internalKey;
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode([]),
    ]);

    $response = curl_exec($ch);
    $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Log if cart clearing fails, but don't fail the order creation
    if ($statusCode >= 400) {
        error_log("Failed to clear cart for customer {$customerId}: HTTP {$statusCode}");
    }
}

function fetchProductByIdCurl(int $productId): array
{
    $urls = [
        'http://api-gateway/api/products/get-detail.php?id=' . $productId,
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
    $url = 'http://api-gateway/api/products/get-by-detail.php?detail_id=' . $detailId;

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
    $url = 'http://api-gateway/api/products/' . $action . '.php';
    $internalKey = os_getInternalApiKey();
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

function fetchCustomerAddress(int $customerId, ?int $addressId = null): ?array
{
    $internalKey = os_getInternalApiKey();
    $headers = $internalKey !== '' ? ['X-Internal-Key: ' . $internalKey] : [];

    $urls = [
        'http://api-gateway/api/customers/' . $customerId . '/addresses/index.php',
    ];

    foreach ($urls as $url) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            curl_close($ch);
            continue;
        }

        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $json = json_decode($response, true);
        if ($statusCode === 200 && is_array($json) && ($json['success'] ?? false) === true) {
            $addresses = $json['data'] ?? [];
            
            // If specific address requested, find it
            if ($addressId !== null) {
                foreach ($addresses as $addr) {
                    if ((int)$addr['id'] === $addressId) {
                        return $addr;
                    }
                }
                return null; // Specific address not found
            }
            
            // Otherwise, return default or first address
            foreach ($addresses as $addr) {
                if (($addr['is_default'] ?? false) === true) {
                    return $addr;
                }
            }
            // If no default, return first
            return $addresses[0] ?? null;
        }
    }

    return null;
}

$input = getJsonInput();

if (!is_array($input) || count($input) === 0) {
    $input = $_POST;
}

// Hỗ trợ cả customer_id (khách hàng) và user_id (nhân viên)
$customerId = isset($input['customer_id']) ? (int)$input['customer_id'] : 0;
$userId     = isset($input['user_id'])     ? (int)$input['user_id']     : (isset($input['userId']) ? (int)$input['userId'] : 0);
$isCustomerCheckout = $customerId > 0;

// Dùng customer_id làm userId nếu checkout với tư cách khách hàng
if ($isCustomerCheckout && $userId <= 0) {
    $userId = $customerId;
}

$items = $input['items'] ?? ($input['cart'] ?? []);
$idempotencyKey = trim((string)($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ($input['idempotency_key'] ?? '')));
$paymentMethod = trim((string)($input['payment_method'] ?? 'cod'));
$selectedAddressId = isset($input['address_id']) ? (int)$input['address_id'] : null;

if ($userId <= 0 || !is_array($items) || count($items) === 0) {
    jsonResponse(400, [
        'success' => false,
        'message' => 'Payload không hợp lệ. Cần user_id (hoặc customer_id) và mảng items.',
        'data' => null,
    ]);
}

// Chỉ check role với user-service nếu là nhân viên/admin
// Khách hàng dùng customer-service DB riêng, không cần verify qua user-service
if ($isCustomerCheckout) {
    os_requireInternalKey();
} else {
    os_requireRoleByUserId($userId, ['customer', 'staff', 'admin']);
}

try {
    $pdo = getPDO();
        $pdo->beginTransaction();
    $totalAmount = 0.0;
    $reservedStocks = [];

    foreach ($items as $idx => $item) {
        $productId = isset($item['product_id']) ? (int)$item['product_id'] : (isset($item['productId']) ? (int)$item['productId'] : 0);
        $detailIdInput = isset($item['detail_id']) ? (int)$item['detail_id'] : (isset($item['detailId']) ? (int)$item['detailId'] : 0);
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
        'payment_method' => $paymentMethod,
    ];

    // Fetch customer address if customer checkout
    if ($isCustomerCheckout) {
        $address = fetchCustomerAddress($customerId, $selectedAddressId);
        if ($address) {
            $orderSnapshot['shipping_address'] = $address;
        }
    }

    $paymentStatus = 'paid'; // All payments are considered completed immediately

    $orderStmt = $pdo->prepare(
        'INSERT INTO hoadonthanhtoan (IDKHACHHANG, IDNHANVIEN, DIEMDADOI, PAYMENT_METHOD, PAYMENT_STATUS, GHICHU, TREMOVE) VALUES (:user_id, NULL, 0, :payment_method, :payment_status, :note, 1)'
    );
    $orderStmt->execute([
        'user_id' => $userId,
        'payment_method' => $paymentMethod,
        'payment_status' => $paymentStatus,
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

    // Clear customer cart after successful order creation
    if ($isCustomerCheckout) {
        clearCustomerCart($customerId);
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
