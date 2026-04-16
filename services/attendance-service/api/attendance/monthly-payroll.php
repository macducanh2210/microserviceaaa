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

$month = trim((string) ($_GET['month'] ?? date('Y-m')));
$employeeId = isset($_GET['employee_id']) ? (int) $_GET['employee_id'] : 0;

try {
    $result = calculateMonthlyPayroll($month, $employeeId);

    jsonResponse(200, [
        'success' => true,
        'message' => 'Tinh luong thang thanh cong.',
        'data' => $result,
    ]);
} catch (Throwable $e) {
    jsonResponse(500, [
        'success' => false,
        'message' => 'Failed to calculate monthly payroll.',
        'error' => $e->getMessage(),
        'data' => null,
    ]);
}
