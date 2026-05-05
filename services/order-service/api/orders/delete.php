<?php

declare(strict_types=1);

require_once __DIR__ . '/_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    jsonResponse(405, ['success' => false, 'message' => 'Method not allowed. Use POST/DELETE.', 'data' => null]);
}

/**
 * Phân biệt 2 flow:
 *  - customer_id có  → Customer flow: chỉ hủy đơn của mình
 *  - user_id có      → Staff/Admin flow: verify role, admin hủy được bất kỳ đơn nào
 */
$input      = getJsonInput();
$customerId = isset($input['customer_id']) ? (int) $input['customer_id'] : 0;
$userId     = isset($input['user_id'])     ? (int) $input['user_id']     : 0;
$orderId    = isset($input['order_id'])    ? (int) $input['order_id']    : 0;

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
    $pdo   = getPDO();
    $order = $requesterRole === 'customer'
        ? os_getOrderOwnedByUser($pdo, $orderId, $customerId)
        : os_getOrderById($pdo, $orderId);

    if (!$order) {
        jsonResponse(404, ['success' => false, 'message' => 'Không tìm thấy đơn hàng.', 'data' => null]);
    }

    if ((int) $order['TREMOVE'] !== 1) {
        jsonResponse(409, ['success' => false, 'message' => 'Đơn hàng đã hủy trước đó.', 'data' => null]);
    }

    $oldRows = os_getOrderDetailRows($pdo, $orderId);
    $ownerId = (int) ($order['IDKHACHHANG'] ?? 0);

    $pdo->beginTransaction();
    if ($requesterRole === 'customer') {
        // Customer chỉ hủy đơn của mình
        $stmt = $pdo->prepare('UPDATE hoadonthanhtoan SET TREMOVE = 0 WHERE ID = :order_id AND IDKHACHHANG = :customer_id');
        $stmt->execute(['order_id' => $orderId, 'customer_id' => $customerId]);
    } else {
        // Admin hủy bất kỳ đơn nào
        $stmt = $pdo->prepare('UPDATE hoadonthanhtoan SET TREMOVE = 0 WHERE ID = :order_id');
        $stmt->execute(['order_id' => $orderId]);
    }
    $pdo->commit();

    // Hoàn kho khi hủy đơn
    foreach ($oldRows as $old) {
        os_callStockApi('increase-stock', (int) $old['IDCHITIETSANPHAM'], (int) $old['SOLUONG']);
    }

    jsonResponse(200, [
        'success' => true,
        'message' => 'Hủy đơn hàng thành công.',
        'data'    => [
            'order_id'    => $orderId,
            'customer_id' => $ownerId,
            'active'      => false,
        ],
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    jsonResponse(500, ['success' => false, 'message' => 'Lỗi khi hủy đơn hàng.', 'error' => $e->getMessage(), 'data' => null]);
}