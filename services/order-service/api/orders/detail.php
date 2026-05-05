<?php

declare(strict_types=1);

require_once __DIR__ . '/_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(405, ['success' => false, 'message' => 'Method not allowed. Use GET.', 'data' => null]);
}

$customerId = isset($_GET['customer_id']) ? (int) $_GET['customer_id'] : 0;
$userId     = isset($_GET['user_id'])     ? (int) $_GET['user_id']     : 0;
$orderId    = isset($_GET['order_id'])    ? (int) $_GET['order_id']    : (isset($_GET['id']) ? (int) $_GET['id'] : 0);

if (($customerId <= 0 && $userId <= 0) || $orderId <= 0) {
    jsonResponse(400, ['success' => false, 'message' => 'Cần cung cấp customer_id hoặc user_id, và order_id.', 'data' => null]);
}

if ($customerId > 0) {
    // ── CUSTOMER FLOW ──────────────────────────────────────────────────────────
    os_requireInternalKey();
    $requesterRole = 'customer';
} else {
    // ── STAFF / ADMIN FLOW ─────────────────────────────────────────────────────
    $requester     = os_requireRoleByUserId($userId, ['staff', 'admin']);
    $requesterRole = (string) ($requester['role'] ?? 'staff');
}

try {
    $pdo = getPDO();
    $order = $requesterRole === 'customer'
        ? os_getOrderOwnedByUser($pdo, $orderId, $customerId)
        : os_getOrderById($pdo, $orderId);

    if (!$order) {
        jsonResponse(404, ['success' => false, 'message' => 'Không tìm thấy đơn hàng.', 'data' => null]);
    }

    $detailRows = os_getOrderDetailRows($pdo, $orderId);
    $snapshot = json_decode((string) ($order['GHICHU'] ?? ''), true);
    $snapshotItems = [];

    if (is_array($snapshot) && isset($snapshot['items']) && is_array($snapshot['items'])) {
        foreach ($snapshot['items'] as $it) {
            $key = (string) ($it['detail_id'] ?? '');
            if ($key !== '') {
                $snapshotItems[$key] = $it;
            }
        }
    }

    // Extract payment information from both database columns and snapshot
    $paymentMethod = $order['PAYMENT_METHOD'] ?? null;
    $paymentStatus = $order['PAYMENT_STATUS'] ?? null;
    
    // Fallback to snapshot data if database columns are empty
    if (!$paymentMethod && is_array($snapshot)) {
        $paymentMethod = $snapshot['payment_method'] ?? null;
    }
    if (!$paymentStatus && is_array($snapshot)) {
        $paymentStatus = $snapshot['payment_status'] ?? 'paid'; // Default to paid for completed orders
    }

    $items = [];
    $total = 0.0;

    foreach ($detailRows as $row) {
        $detailId = (int) $row['IDCHITIETSANPHAM'];
        $qty = (int) $row['SOLUONG'];
        $info = $snapshotItems[(string) $detailId] ?? null;
        $unit = (float) ($info['unit_price'] ?? 0);
        $lineTotal = $unit * $qty;
        $total += $lineTotal;

        $items[] = [
            'detail_id' => $detailId,
            'product_id' => isset($info['product_id']) ? (int) $info['product_id'] : null,
            'quantity' => $qty,
            'unit_price' => $unit,
            'line_total' => $lineTotal,
        ];
    }

    jsonResponse(200, [
        'success' => true,
        'message' => 'Lấy chi tiết đơn hàng thành công.',
        'data' => [
            'order_id' => (int) $order['ID'],
            'user_id' => (int) $order['IDKHACHHANG'],
            'order_date' => $order['NGAYTHANHTOAN'],
            'active' => (int) $order['TREMOVE'] === 1,
            'total_amount' => $total,
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentStatus,
            'shipping_address' => $snapshot['shipping_address'] ?? null,
            'items' => $items,
        ],
    ]);
} catch (Throwable $e) {
    jsonResponse(500, ['success' => false, 'message' => 'Lỗi khi lấy chi tiết đơn hàng.', 'error' => $e->getMessage(), 'data' => null]);
}
