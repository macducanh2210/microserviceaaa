<?php

declare(strict_types=1);

function getPDO(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $dbName = getenv('DB_NAME') ?: 'attendance_db';
    $user = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASSWORD') ?: '';

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $dbName);

    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function jsonResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function getJsonInput(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);
    if (is_array($data)) {
        return $data;
    }

    $fallback = [];
    parse_str($raw, $fallback);
    return is_array($fallback) ? $fallback : [];
}

function fetchEmployeesFromUserService(): array
{
    $url = getenv('USER_EMPLOYEE_API') ?: 'http://user-service/api/users/employees/get-all.php';

    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('Cannot initialize request to user-service.');
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);

    $body = curl_exec($ch);
    if ($body === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Failed to fetch employees: ' . $error);
    }

    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode($body, true);
    if ($status >= 400 || !is_array($json) || ($json['success'] ?? false) !== true || !isset($json['data']) || !is_array($json['data'])) {
        throw new RuntimeException('Invalid response from user-service employee API.');
    }

    return $json['data'];
}

function calculateMonthlyPayroll(string $month, int $employeeId = 0): array
{
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
        throw new InvalidArgumentException('month phai theo dinh dang YYYY-MM.');
    }

    $monthStart = $month . '-01';
    $monthEnd = date('Y-m-d', strtotime($monthStart . ' +1 month'));

    $employees = fetchEmployeesFromUserService();
    if ($employeeId > 0) {
        $employees = array_values(array_filter($employees, static fn($e) => (int) ($e['id'] ?? 0) === $employeeId));
    }

    $employeeMap = [];
    foreach ($employees as $employee) {
        $id = (int) ($employee['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }

        $employeeMap[$id] = [
            'employee_id' => $id,
            'full_name' => (string) ($employee['full_name'] ?? ''),
            'position' => (string) ($employee['position'] ?? ''),
            'base_salary' => (float) ($employee['salary'] ?? 0),
        ];
    }

    if (!$employeeMap) {
        return [];
    }

    $pdo = getPDO();
    $sql = 'SELECT IDNHANVIEN, NGAYCHAMCONG, Giora FROM chamcong WHERE NGAYCHAMCONG >= :month_start AND NGAYCHAMCONG < :month_end';
    $params = [
        'month_start' => $monthStart . ' 00:00:00',
        'month_end' => $monthEnd . ' 00:00:00',
    ];

    if ($employeeId > 0) {
        $sql .= ' AND IDNHANVIEN = :employee_id';
        $params['employee_id'] = $employeeId;
    }

    $sql .= ' ORDER BY IDNHANVIEN ASC, NGAYCHAMCONG ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $attendanceByEmployee = [];
    foreach ($rows as $row) {
        $id = (int) ($row['IDNHANVIEN'] ?? 0);
        if ($id <= 0 || !isset($employeeMap[$id])) {
            continue;
        }

        $checkInAt = (string) ($row['NGAYCHAMCONG'] ?? '');
        $checkOutAt = $row['Giora'] !== null ? (string) $row['Giora'] : null;

        $checkInTime = substr($checkInAt, 11, 8);
        $checkOutTime = $checkOutAt !== null ? substr($checkOutAt, 11, 8) : null;

        $isLate = $checkInTime > '07:00:00';
        $isEarly = $checkOutTime === null || $checkOutTime < '20:00:00';
        $violation = $isLate || $isEarly;

        if (!isset($attendanceByEmployee[$id])) {
            $attendanceByEmployee[$id] = [
                'work_days' => 0,
                'violation_days' => 0,
                'deduction_amount' => 0,
                'records' => [],
            ];
        }

        $attendanceByEmployee[$id]['work_days']++;
        if ($violation) {
            $attendanceByEmployee[$id]['violation_days']++;
            $attendanceByEmployee[$id]['deduction_amount'] += 100000;
        }

        $attendanceByEmployee[$id]['records'][] = [
            'check_in_at' => $checkInAt,
            'check_out_at' => $checkOutAt,
            'is_late' => $isLate,
            'is_early' => $isEarly,
            'violation' => $violation,
            'deduction' => $violation ? 100000 : 0,
        ];
    }

    $result = [];
    foreach ($employeeMap as $id => $employee) {
        $attendance = $attendanceByEmployee[$id] ?? [
            'work_days' => 0,
            'violation_days' => 0,
            'deduction_amount' => 0,
            'records' => [],
        ];

        $baseSalary = (float) $employee['base_salary'];
        $deduction = (float) $attendance['deduction_amount'];
        $finalSalary = max(0, $baseSalary - $deduction);

        $result[] = [
            'month' => $month,
            'employee_id' => $id,
            'full_name' => $employee['full_name'],
            'position' => $employee['position'],
            'base_salary' => $baseSalary,
            'work_days' => (int) $attendance['work_days'],
            'violation_days' => (int) $attendance['violation_days'],
            'deduction_amount' => $deduction,
            'final_salary' => $finalSalary,
            'records' => $attendance['records'],
        ];
    }

    return $result;
}

function logSalaryExpense(string $month, array $payrollRow, int $paidByEmployeeId = 0): array
{
    $employeeId = (int) ($payrollRow['employee_id'] ?? 0);
    $finalSalary = (float) ($payrollRow['final_salary'] ?? 0);
    if ($employeeId <= 0 || $finalSalary <= 0) {
        return ['skipped' => true];
    }

    $refCode = 'SALARY-' . $month . '-' . $employeeId;
    $payload = [
        'purpose' => 'Thanh toan luong thang ' . $month,
        'employee_id' => $employeeId,
        'amount' => -abs($finalSalary),
        'note' => 'Nguoi thanh toan: #' . max(0, $paidByEmployeeId),
        'status' => 1,
        'type' => 'salary',
        'category' => 'salary',
        'ref_code' => $refCode,
    ];

    $urls = [
        'http://expense-service/api/expenses/add.php',
        'http://localhost:8080/api/expenses/add.php',
    ];

    $lastError = 'Unknown expense API error';
    foreach ($urls as $url) {
        $ch = curl_init($url);
        if ($ch === false) {
            continue;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $body = curl_exec($ch);
        if ($body === false) {
            $lastError = curl_error($ch) ?: 'Expense API call failed';
            curl_close($ch);
            continue;
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $json = json_decode($body, true);
        if ($status === 409) {
            return ['duplicate' => true, 'ref_code' => $refCode];
        }

        if ($status < 400 && is_array($json) && ($json['success'] ?? false) === true) {
            return is_array($json['data'] ?? null) ? $json['data'] : ['logged' => true, 'ref_code' => $refCode];
        }

        $lastError = is_array($json) ? (string) ($json['message'] ?? ('HTTP ' . $status)) : ('HTTP ' . $status);
    }

    throw new RuntimeException('Failed to log salary expense: ' . $lastError);
}
