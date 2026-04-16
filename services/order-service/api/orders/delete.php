<?php

declare(strict_types=1);

require_once __DIR__ . '/_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    jsonResponse(405, ['success' => false, 'message' => 'Method not allowed. Use POST/DELETE.', 'data' => null]);
}

$input = getJsonInput();
$userId = isset($input['user_id']) ? (int) $input['user_id'] : 0;
$orderId = isset($input['order_id']) ? (int) $input['order_id'] : 0;

if ($userId <= 0 || $orderId <= 0) {
    jsonResponse(400, ['success' => false, 'message' => 'user_id hoặc order_id không hợp lệ.', 'data' => null]);
}

$requester = os_requireRoleByUserId($userId, ['customer', 'admin']);
$requesterRole = (string) ($requester['role'] ?? 'customer');

try {
    $pdo = getPDO();
    $order = $requesterRole === 'customer'
        ? os_getOrderOwnedByUser($pdo, $orderId, $userId)
        : os_getOrderById($pdo, $orderId);

    if (!$order) {
        jsonResponse(404, ['success' => false, 'message' => 'Không tìm thấy đơn hàng.', 'data' => null]);
    }

    if ((int) $order['TREMOVE'] !== 1) {
        jsonResponse(409, ['success' => false, 'message' => 'Đơn hàng đã hủy trước đó.', 'data' => null]);
    }

    $oldRows = os_getOrderDetailRows($pdo, $orderId);
    $ownerId = (int) ($order['IDKHACHHANG'] ?? $userId);

    $pdo->beginTransaction();
    if ($requesterRole === 'customer') {
        $stmt = $pdo->prepare('UPDATE hoadonthanhtoan SET TREMOVE = 0 WHERE ID = :order_id AND IDKHACHHANG = :user_id');
        $stmt->execute([
            'order_id' => $orderId,
            'user_id' => $userId,
        ]);
    } else {
        $stmt = $pdo->prepare('UPDATE hoadonthanhtoan SET TREMOVE = 0 WHERE ID = :order_id');
        $stmt->execute(['order_id' => $orderId]);
    }
    $pdo->commit();

    // Return reserved stock when order is canceled.
    foreach ($oldRows as $old) {
        os_callStockApi('increase-stock', (int) $old['IDCHITIETSANPHAM'], (int) $old['SOLUONG']);
    }

    jsonResponse(200, [
        'success' => true,
        'message' => 'Hủy đơn hàng thành công.',
        'data' => [
            'order_id' => $orderId,
            'user_id' => $ownerId,
            'canceled_by' => $userId,
            'active' => false,
        ],
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    jsonResponse(500, ['success' => false, 'message' => 'Lỗi khi hủy đơn hàng.', 'error' => $e->getMessage(), 'data' => null]);
}
