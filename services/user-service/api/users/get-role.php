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
    $stmt = $pdo->prepare('SELECT ID, HOTEN, EMAIL, CHUCVU FROM nhanvien WHERE ID = :user_id LIMIT 1');
    $stmt->execute(['user_id' => $userId]);
    $user = $stmt->fetch();

    if (!$user) {
        jsonResponse(404, [
            'success' => false,
            'message' => 'Không tìm thấy người dùng.',
            'data' => null,
        ]);
    }

    $rawPosition = (string) ($user['CHUCVU'] ?? 'USER');

    jsonResponse(200, [
        'success' => true,
        'message' => 'Lấy vai trò người dùng thành công.',
        'data' => [
            'user_id' => (int) $user['ID'],
            'full_name' => (string) $user['HOTEN'],
            'email' => (string) $user['EMAIL'],
            'role' => normalizeUserRole($rawPosition),
            'position_raw' => $rawPosition,
        ],
    ]);
} catch (Throwable $e) {
    jsonResponse(500, [
        'success' => false,
        'message' => 'Lỗi máy chủ khi lấy vai trò người dùng.',
        'error' => $e->getMessage(),
        'data' => null,
    ]);
}
