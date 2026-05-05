<?php

declare(strict_types=1);

require_once __DIR__ . '/_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(405, ['success' => false, 'message' => 'Method not allowed. Use GET.', 'data' => null]);
}

/**
 * Phân biệt 2 flow:
 *  - customer_id có  → Customer flow: lọc đơn theo customer đó, không cần verify role
 *  - user_id có      → Staff/Admin flow: verify role qua user-service, xem được tất cả đơn hàng
 */
$customerId = isset($_GET['customer_id']) ? (int) $_GET['customer_id'] : 0;
$userId     = isset($_GET['user_id'])     ? (int) $_GET['user_id']     : 0;

if ($customerId <= 0 && $userId <= 0) {
    jsonResponse(400, ['success' => false, 'message' => 'Cần cung cấp customer_id hoặc user_id.', 'data' => null]);
}

// Xác định flow
if ($customerId > 0) {
    // ── CUSTOMER FLOW ──────────────────────────────────────────────────────────
    os_requireInternalKey();
    $requesterRole    = 'customer';
    $customerFilterId = $customerId;
} else {
    // ── STAFF / ADMIN FLOW ─────────────────────────────────────────────────────
    $requester     = os_requireRoleByUserId($userId, ['staff', 'admin']);
    $requesterRole = (string) ($requester['role'] ?? 'staff');
    // Staff có thể lọc theo customer cụ thể qua customer_filter_id
    $customerFilterId = isset($_GET['customer_filter_id']) ? (int) $_GET['customer_filter_id'] : 0;
}

try {
    $pdo = getPDO();

    if ($requesterRole === 'customer') {
        // Customer chỉ thấy đơn của mình
        $stmt = $pdo->prepare(
            'SELECT ID, NGAYTHANHTOAN, GHICHU, TREMOVE, IDKHACHHANG
             FROM hoadonthanhtoan
             WHERE IDKHACHHANG = :customer_id
             ORDER BY ID DESC'
        );
        $stmt->execute(['customer_id' => $customerFilterId]);
    } elseif ($customerFilterId > 0) {
        // Staff lọc theo customer cụ thể
        $stmt = $pdo->prepare(
            'SELECT ID, NGAYTHANHTOAN, GHICHU, TREMOVE, IDKHACHHANG
             FROM hoadonthanhtoan
             WHERE IDKHACHHANG = :customer_id
             ORDER BY ID DESC'
        );
        $stmt->execute(['customer_id' => $customerFilterId]);
    } else {
        // Staff xem tất cả đơn hàng
        $stmt = $pdo->query(
            'SELECT ID, NGAYTHANHTOAN, GHICHU, TREMOVE, IDKHACHHANG
             FROM hoadonthanhtoan
             ORDER BY ID DESC'
        );
    }

    $orders = $stmt->fetchAll();
    $result = [];

    foreach ($orders as $order) {
        $snapshot = json_decode((string) ($order['GHICHU'] ?? ''), true);
        $items    = is_array($snapshot) && isset($snapshot['items']) && is_array($snapshot['items']) ? $snapshot['items'] : [];
        $total    = is_array($snapshot) && isset($snapshot['total_amount']) ? (float) $snapshot['total_amount'] : 0.0;

        $result[] = [
            'order_id'     => (int) $order['ID'],
            'customer_id'  => (int) ($order['IDKHACHHANG'] ?? 0),
            'order_date'   => $order['NGAYTHANHTOAN'],
            'active'       => (int) ($order['TREMOVE'] ?? 0) === 1,
            'item_count'   => count($items),
            'total_amount' => $total,
        ];
    }

    jsonResponse(200, ['success' => true, 'message' => 'Lấy danh sách đơn hàng thành công.', 'data' => $result]);
} catch (Throwable $e) {
    jsonResponse(500, ['success' => false, 'message' => 'Lỗi khi lấy danh sách đơn hàng.', 'error' => $e->getMessage(), 'data' => null]);
}
