<?php

declare(strict_types=1);

require_once __DIR__ . '/_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'PATCH') {
    jsonResponse(405, ['success' => false, 'message' => 'Method not allowed. Use POST/PUT/PATCH.', 'data' => null]);
}

/**
 * Phân biệt 2 flow:
 *  - customer_id có  → Customer flow: chỉ cập nhật đơn của mình
 *  - user_id có      → Staff/Admin flow: verify role, admin cập nhật được bất kỳ đơn nào
 */
$input      = getJsonInput();
$customerId = isset($input['customer_id']) ? (int) $input['customer_id'] : 0;
$userId     = isset($input['user_id'])     ? (int) $input['user_id']     : 0;
$orderId    = isset($input['order_id'])    ? (int) $input['order_id']    : 0;
$items      = $input['items'] ?? [];

if (($customerId <= 0 && $userId <= 0) || $orderId <= 0 || !is_array($items) || count($items) === 0) {
    jsonResponse(400, ['success' => false, 'message' => 'Thiếu hoặc sai dữ liệu: customer_id/user_id, order_id, items.', 'data' => null]);
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

$reservedNew = [];

try {
    $pdo   = getPDO();
    $order = $requesterRole === 'customer'
        ? os_getOrderOwnedByUser($pdo, $orderId, $customerId)
        : os_getOrderById($pdo, $orderId);

    if (!$order) {
        jsonResponse(404, ['success' => false, 'message' => 'Không tìm thấy đơn hàng.', 'data' => null]);
    }

    if ((int) $order['TREMOVE'] !== 1) {
        jsonResponse(409, ['success' => false, 'message' => 'Đơn hàng đã hủy, không thể cập nhật.', 'data' => null]);
    }

    $oldRows  = os_getOrderDetailRows($pdo, $orderId);
    $resolved = os_resolveItems($items);
    $newItems = $resolved['items'];

    // Reserve stock for new items first.
    foreach ($newItems as $line) {
        os_callStockApi('decrease-stock', (int) $line['detail_id'], (int) $line['quantity']);
        $reservedNew[] = [
            'detail_id' => (int) $line['detail_id'],
            'quantity'  => (int) $line['quantity'],
        ];
    }

    $pdo->beginTransaction();

    $del = $pdo->prepare('DELETE FROM chitiethoadonthanhtoan WHERE IDHOADONTHANHTOAN = :order_id');
    $del->execute(['order_id' => $orderId]);

    $ins = $pdo->prepare('INSERT INTO chitiethoadonthanhtoan (IDCHITIETSANPHAM, IDHOADONTHANHTOAN, SOLUONG) VALUES (:detail_id, :order_id, :qty)');
    foreach ($newItems as $line) {
        $ins->execute([
            'detail_id' => (int) $line['detail_id'],
            'order_id'  => $orderId,
            'qty'       => (int) $line['quantity'],
        ]);
    }

    $snapshot = [
        'updated_at'   => date('Y-m-d H:i:s'),
        'items'        => $newItems,
        'total_amount' => $resolved['total_amount'],
    ];

    $upd = $pdo->prepare('UPDATE hoadonthanhtoan SET GHICHU = :note WHERE ID = :order_id');
    $upd->execute([
        'note'     => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'order_id' => $orderId,
    ]);

    $pdo->commit();

    // Release stock reserved by old order lines after successful update.
    foreach ($oldRows as $old) {
        os_callStockApi('increase-stock', (int) $old['IDCHITIETSANPHAM'], (int) $old['SOLUONG']);
    }

    jsonResponse(200, [
        'success' => true,
        'message' => 'Cập nhật đơn hàng thành công.',
        'data'    => [
            'order_id'     => $orderId,
            'customer_id'  => (int) ($order['IDKHACHHANG'] ?? 0),
            'total_amount' => $resolved['total_amount'],
            'items'        => $newItems,
        ],
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if (!empty($reservedNew)) {
        foreach ($reservedNew as $reserved) {
            try {
                os_callStockApi('increase-stock', (int) $reserved['detail_id'], (int) $reserved['quantity']);
            } catch (Throwable $ignored) {
                // Best-effort compensation only.
            }
        }
    }

    jsonResponse(500, ['success' => false, 'message' => 'Lỗi khi cập nhật đơn hàng.', 'error' => $e->getMessage(), 'data' => null]);
}
