<?php

declare(strict_types=1);

require_once __DIR__ . '/_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(405, ['success' => false, 'message' => 'Method not allowed. Use GET.', 'data' => null]);
}

$userId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
if ($userId <= 0) {
    jsonResponse(400, ['success' => false, 'message' => 'user_id không hợp lệ.', 'data' => null]);
}

$requester = os_requireRoleByUserId($userId, ['customer', 'staff', 'admin']);
$requesterRole = (string) ($requester['role'] ?? 'customer');
$customerFilterId = isset($_GET['customer_id']) ? (int) $_GET['customer_id'] : 0;

try {
    $pdo = getPDO();

    if ($requesterRole === 'customer') {
        $stmt = $pdo->prepare('SELECT ID, NGAYTHANHTOAN, GHICHU, TREMOVE, IDKHACHHANG FROM hoadonthanhtoan WHERE IDKHACHHANG = :user_id ORDER BY ID DESC');
        $stmt->execute(['user_id' => $userId]);
    } else {
        if ($customerFilterId > 0) {
            $stmt = $pdo->prepare('SELECT ID, NGAYTHANHTOAN, GHICHU, TREMOVE, IDKHACHHANG FROM hoadonthanhtoan WHERE IDKHACHHANG = :customer_id ORDER BY ID DESC');
            $stmt->execute(['customer_id' => $customerFilterId]);
        } else {
            $stmt = $pdo->query('SELECT ID, NGAYTHANHTOAN, GHICHU, TREMOVE, IDKHACHHANG FROM hoadonthanhtoan ORDER BY ID DESC');
        }
    }
    $orders = $stmt->fetchAll();

    $result = [];
    foreach ($orders as $order) {
        $snapshot = json_decode((string) ($order['GHICHU'] ?? ''), true);
        $items = is_array($snapshot) && isset($snapshot['items']) && is_array($snapshot['items']) ? $snapshot['items'] : [];
        $total = is_array($snapshot) && isset($snapshot['total_amount']) ? (float) $snapshot['total_amount'] : 0.0;

        $result[] = [
            'order_id' => (int) $order['ID'],
            'customer_id' => (int) ($order['IDKHACHHANG'] ?? $userId),
            'order_date' => $order['NGAYTHANHTOAN'],
            'active' => (int) $order['TREMOVE'] === 1,
            'item_count' => count($items),
            'total_amount' => $total,
        ];
    }

    jsonResponse(200, ['success' => true, 'message' => 'Lấy danh sách đơn hàng thành công.', 'data' => $result]);
} catch (Throwable $e) {
    jsonResponse(500, ['success' => false, 'message' => 'Lỗi khi lấy danh sách đơn hàng.', 'error' => $e->getMessage(), 'data' => null]);
}
