<?php

declare(strict_types=1);

require_once __DIR__ . '/../../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(405, [
        'success' => false,
        'message' => 'Method not allowed. Use GET.',
        'data' => null,
    ]);
}

$userId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
if ($userId <= 0) {
    jsonResponse(400, [
        'success' => false,
        'message' => 'user_id không hợp lệ.',
        'data' => null,
    ]);
}

try {
    $pdo = getPDO();

    $orderStmt = $pdo->prepare('SELECT ID, NGAYTHANHTOAN, GHICHU FROM hoadonthanhtoan WHERE IDKHACHHANG = :user_id AND TREMOVE = 1 ORDER BY ID DESC');
    $orderStmt->execute(['user_id' => $userId]);
    $orders = $orderStmt->fetchAll();

    $detailStmt = $pdo->prepare('SELECT IDCHITIETSANPHAM, SOLUONG FROM chitiethoadonthanhtoan WHERE IDHOADONTHANHTOAN = :order_id ORDER BY ID ASC');

    $result = [];
    foreach ($orders as $order) {
        $snapshot = json_decode((string) ($order['GHICHU'] ?? ''), true);
        $itemsFromNote = [];
        if (is_array($snapshot) && isset($snapshot['items']) && is_array($snapshot['items'])) {
            foreach ($snapshot['items'] as $it) {
                $itemsFromNote[(string) ($it['detail_id'] ?? '')] = $it;
            }
        }

        $detailStmt->execute(['order_id' => (int) $order['ID']]);
        $rows = $detailStmt->fetchAll();

        $items = [];
        $total = 0.0;

        foreach ($rows as $row) {
            $detailId = (int) $row['IDCHITIETSANPHAM'];
            $qty = (int) $row['SOLUONG'];
            $noteItem = $itemsFromNote[(string) $detailId] ?? null;
            $unitPrice = (float) ($noteItem['unit_price'] ?? 0);
            $lineTotal = $unitPrice * $qty;
            $total += $lineTotal;

            $items[] = [
                'detail_id' => $detailId,
                'product_id' => isset($noteItem['product_id']) ? (int) $noteItem['product_id'] : null,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
            ];
        }

        $result[] = [
            'order_id' => (int) $order['ID'],
            'order_date' => $order['NGAYTHANHTOAN'],
            'total_amount' => $total,
            'items' => $items,
        ];
    }

    jsonResponse(200, [
        'success' => true,
        'message' => 'Lấy lịch sử đơn hàng thành công.',
        'data' => $result,
    ]);
} catch (Throwable $e) {
    jsonResponse(500, [
        'success' => false,
        'message' => 'Lỗi khi lấy lịch sử đơn hàng.',
        'error' => $e->getMessage(),
        'data' => null,
    ]);
}
