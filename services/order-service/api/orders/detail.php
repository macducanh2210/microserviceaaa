<?php

declare(strict_types=1);

require_once __DIR__ . '/_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(405, ['success' => false, 'message' => 'Method not allowed. Use GET.', 'data' => null]);
}

$userId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
$orderId = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;

if ($userId <= 0 || $orderId <= 0) {
    jsonResponse(400, ['success' => false, 'message' => 'user_id hoặc order_id không hợp lệ.', 'data' => null]);
}

$requester = os_requireRoleByUserId($userId, ['customer', 'staff', 'admin']);
$requesterRole = (string) ($requester['role'] ?? 'customer');

try {
    $pdo = getPDO();
    $order = $requesterRole === 'customer'
        ? os_getOrderOwnedByUser($pdo, $orderId, $userId)
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
            'items' => $items,
        ],
    ]);
} catch (Throwable $e) {
    jsonResponse(500, ['success' => false, 'message' => 'Lỗi khi lấy chi tiết đơn hàng.', 'error' => $e->getMessage(), 'data' => null]);
}
