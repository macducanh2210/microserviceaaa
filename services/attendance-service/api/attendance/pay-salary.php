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
$month = trim((string) ($input['month'] ?? date('Y-m')));
$employeeId = isset($input['employee_id']) ? (int) $input['employee_id'] : 0;
$paidByEmployeeId = isset($input['paid_by_employee_id']) ? (int) $input['paid_by_employee_id'] : 0;

try {
    $payrollRows = calculateMonthlyPayroll($month, $employeeId);

    if (!$payrollRows) {
        jsonResponse(200, [
            'success' => true,
            'message' => 'Khong co du lieu luong de thanh toan.',
            'data' => [
                'month' => $month,
                'paid_count' => 0,
                'skipped_count' => 0,
                'duplicate_count' => 0,
                'total_paid_amount' => 0,
                'details' => [],
            ],
        ]);
    }

    $details = [];
    $paidCount = 0;
    $skippedCount = 0;
    $duplicateCount = 0;
    $totalPaidAmount = 0.0;

    foreach ($payrollRows as $row) {
        $employeeRowId = (int) ($row['employee_id'] ?? 0);
        $finalSalary = (float) ($row['final_salary'] ?? 0);

        if ($finalSalary <= 0) {
            $skippedCount++;
            $details[] = [
                'employee_id' => $employeeRowId,
                'status' => 'skipped',
                'reason' => 'final_salary <= 0',
            ];
            continue;
        }

        try {
            $logResult = logSalaryExpense($month, $row, $paidByEmployeeId);
            if (($logResult['duplicate'] ?? false) === true) {
                $duplicateCount++;
                $details[] = [
                    'employee_id' => $employeeRowId,
                    'status' => 'duplicate',
                    'ref_code' => $logResult['ref_code'] ?? null,
                ];
                continue;
            }

            $paidCount++;
            $totalPaidAmount += $finalSalary;
            $details[] = [
                'employee_id' => $employeeRowId,
                'status' => 'paid',
                'final_salary' => $finalSalary,
                'expense' => $logResult,
            ];
        } catch (Throwable $e) {
            $details[] = [
                'employee_id' => $employeeRowId,
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    jsonResponse(200, [
        'success' => true,
        'message' => 'Thanh toan luong va ghi chi tieu hoan tat.',
        'data' => [
            'month' => $month,
            'paid_count' => $paidCount,
            'skipped_count' => $skippedCount,
            'duplicate_count' => $duplicateCount,
            'total_paid_amount' => $totalPaidAmount,
            'details' => $details,
        ],
    ]);
} catch (Throwable $e) {
    jsonResponse(500, [
        'success' => false,
        'message' => 'Failed to pay salary.',
        'error' => $e->getMessage(),
        'data' => null,
    ]);
}
