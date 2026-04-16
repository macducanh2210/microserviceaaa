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
if ($timeNow < '07:00:00' || $timeNow > '21:00:00') {
    jsonResponse(400, [
        'success' => false,
        'message' => 'Chi duoc cham cong tu 07:00 den 21:00.',
        'data' => null,
    ]);
}

try {
    $pdo = getPDO();

    $existsStmt = $pdo->prepare('SELECT IDNHANVIEN FROM chamcong WHERE IDNHANVIEN = :employee_id AND DATE(NGAYCHAMCONG) = CURDATE() LIMIT 1');
    $existsStmt->execute(['employee_id' => $employeeId]);
    if ($existsStmt->fetch()) {
        jsonResponse(409, [
            'success' => false,
            'message' => 'Hom nay da cham cong roi.',
            'data' => null,
        ]);
    }

    $stmt = $pdo->prepare('INSERT INTO chamcong (IDNHANVIEN, NGAYCHAMCONG, Giora) VALUES (:employee_id, NOW(), NULL)');
    $stmt->execute(['employee_id' => $employeeId]);

    jsonResponse(201, [
        'success' => true,
        'message' => 'Cham cong vao thanh cong.',
        'data' => [
            'employee_id' => $employeeId,
            'check_in_at' => $now->format('Y-m-d H:i:s'),
        ],
    ]);
} catch (Throwable $e) {
    jsonResponse(500, [
        'success' => false,
        'message' => 'Failed to check in.',
        'error' => $e->getMessage(),
        'data' => null,
    ]);
}
