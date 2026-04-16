<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Ho_Chi_Minh');

require_once __DIR__ . '/../../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResponse(200, ['success' => true, 'message' => 'Preflight OK', 'data' => null]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(405, ['success' => false, 'message' => 'Method not allowed. Use GET.', 'data' => null]);
}

$employeeId = isset($_GET['employee_id']) ? (int) $_GET['employee_id'] : 0;
if ($employeeId <= 0) {
    jsonResponse(400, ['success' => false, 'message' => 'employee_id khong hop le.', 'data' => null]);
}

try {
    $pdo = getPDO();

    $stmt = $pdo->prepare(
        'SELECT IDNHANVIEN, NGAYCHAMCONG, Giora FROM chamcong WHERE IDNHANVIEN = :employee_id AND DATE(NGAYCHAMCONG) = CURDATE() ORDER BY NGAYCHAMCONG DESC LIMIT 1'
    );
    $stmt->execute(['employee_id' => $employeeId]);
    $row = $stmt->fetch();

    $now = new DateTimeImmutable('now');
    $timeNow = $now->format('H:i:s');

    $canCheckIn = $timeNow >= '07:00:00' && $timeNow <= '21:00:00';
    $canCheckOut = $timeNow >= '20:00:00' && $timeNow <= '21:00:00';

    jsonResponse(200, [
        'success' => true,
        'message' => 'Fetched attendance status.',
        'data' => [
            'employee_id' => $employeeId,
            'today' => $now->format('Y-m-d'),
            'current_time' => $timeNow,
            'record_exists' => (bool) $row,
            'check_in_at' => $row['NGAYCHAMCONG'] ?? null,
            'check_out_at' => $row['Giora'] ?? null,
            'can_check_in_now' => $canCheckIn,
            'can_check_out_now' => $canCheckOut,
        ],
    ]);
} catch (Throwable $e) {
    jsonResponse(500, [
        'success' => false,
        'message' => 'Failed to fetch attendance status.',
        'error' => $e->getMessage(),
        'data' => null,
    ]);
}
