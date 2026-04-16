<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Ho_Chi_Minh');

require_once __DIR__ . '/../../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResponse(200, ['success' => true, 'message' => 'Preflight OK', 'data' => null]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, ['success' => false, 'message' => 'Method not allowed. Use POST.', 'data' => null]);
}

$input = getJsonInput();
$employeeId = (int) ($input['employee_id'] ?? 0);
if ($employeeId <= 0) {
    jsonResponse(400, ['success' => false, 'message' => 'employee_id khong hop le.', 'data' => null]);
}

$now = new DateTimeImmutable('now');
$timeNow = $now->format('H:i:s');
if ($timeNow < '20:00:00' || $timeNow > '23:00:00') {
    jsonResponse(400, [
        'success' => false,
        'message' => 'Chi duoc check-out tu 20:00 den 23:00.',
        'data' => null,
    ]);
}

try {
    $pdo = getPDO();

    $stmt = $pdo->prepare(
        'SELECT IDNHANVIEN, NGAYCHAMCONG, Giora FROM chamcong WHERE IDNHANVIEN = :employee_id AND DATE(NGAYCHAMCONG) = CURDATE() ORDER BY NGAYCHAMCONG DESC LIMIT 1'
    );
    $stmt->execute(['employee_id' => $employeeId]);
    $row = $stmt->fetch();

    if (!$row) {
        jsonResponse(404, [
            'success' => false,
            'message' => 'Hom nay chua check-in.',
            'data' => null,
        ]);
    }

    if (!empty($row['Giora'])) {
        jsonResponse(409, [
            'success' => false,
            'message' => 'Hom nay da check-out roi.',
            'data' => null,
        ]);
    }

    $updateStmt = $pdo->prepare('UPDATE chamcong SET Giora = NOW() WHERE IDNHANVIEN = :employee_id AND NGAYCHAMCONG = :check_in_at');
    $updateStmt->execute([
        'employee_id' => $employeeId,
        'check_in_at' => $row['NGAYCHAMCONG'],
    ]);

    jsonResponse(200, [
        'success' => true,
        'message' => 'Check-out thanh cong.',
        'data' => [
            'employee_id' => $employeeId,
            'check_in_at' => $row['NGAYCHAMCONG'],
            'check_out_at' => $now->format('Y-m-d H:i:s'),
        ],
    ]);
} catch (Throwable $e) {
    jsonResponse(500, [
        'success' => false,
        'message' => 'Failed to check out.',
        'error' => $e->getMessage(),
        'data' => null,
    ]);
}
