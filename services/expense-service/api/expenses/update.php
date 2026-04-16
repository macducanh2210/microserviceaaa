<?php

declare(strict_types=1);

require_once __DIR__ . '/../../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResponse(200, ['success' => true, 'message' => 'Preflight OK', 'data' => null]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, ['success' => false, 'message' => 'Method not allowed. Use POST.', 'data' => null]);
}

$input = getJsonInput();
$expenseId = (int) ($input['expense_id'] ?? 0);
if ($expenseId <= 0) {
    jsonResponse(400, ['success' => false, 'message' => 'expense_id khong hop le.', 'data' => null]);
}

$allowedMap = [
    'purpose' => 'MUCDICHCHITIEU',
    'employee_id' => 'IDNHANVIEN',
    'amount' => 'SOTIEN',
    'expense_date' => 'NGAYCHITIEU',
    'note' => 'GHICHU',
    'status' => 'TRANGTHAI',
    'type' => 'LOAI',
    'category' => 'DANHMUC',
];

$updates = [];
$params = ['expense_id' => $expenseId];

foreach ($allowedMap as $key => $column) {
    if (!array_key_exists($key, $input)) {
        continue;
    }

    $value = $input[$key];

    if ($key === 'purpose') {
        $value = trim((string) $value);
        if ($value === '') {
            jsonResponse(400, ['success' => false, 'message' => 'purpose khong duoc rong.', 'data' => null]);
        }
    }

    if ($key === 'amount') {
        $value = (float) $value;
        if ($value == 0.0) {
            jsonResponse(400, ['success' => false, 'message' => 'amount phai khac 0.', 'data' => null]);
        }
    }

    if ($key === 'type') {
        $value = strtolower(trim((string) $value));
        if (!in_array($value, ['import', 'salary', 'other'], true)) {
            $value = 'other';
        }
    }

    if ($key === 'category') {
        $value = strtolower(trim((string) $value));
        if (!in_array($value, ['electric', 'water', 'internet', 'salary', 'import', 'office', 'maintenance', 'other'], true)) {
            $value = 'other';
        }
    }

    if ($key === 'expense_date') {
        $value = trim((string) $value);
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $value);
        if (!$dt) {
            jsonResponse(400, ['success' => false, 'message' => 'expense_date phai theo dinh dang YYYY-MM-DD HH:MM:SS.', 'data' => null]);
        }
    }

    if ($key === 'note') {
        $value = trim((string) $value);
        $value = $value !== '' ? $value : null;
    }

    if ($key === 'employee_id') {
        $value = (int) $value;
        if ($value <= 0) {
            $value = null;
        }
    }

    $param = 'p_' . $key;
    $updates[] = $column . ' = :' . $param;
    $params[$param] = $value;
}

if (!$updates) {
    jsonResponse(400, ['success' => false, 'message' => 'Khong co du lieu de cap nhat.', 'data' => null]);
}

try {
    $pdo = getPDO();

    $existsStmt = $pdo->prepare('SELECT MACT FROM chitieu WHERE MACT = :expense_id LIMIT 1');
    $existsStmt->execute(['expense_id' => $expenseId]);
    if (!$existsStmt->fetch()) {
        jsonResponse(404, ['success' => false, 'message' => 'Khong tim thay chi tieu.', 'data' => null]);
    }

    $sql = 'UPDATE chitieu SET ' . implode(', ', $updates) . ' WHERE MACT = :expense_id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    jsonResponse(200, [
        'success' => true,
        'message' => 'Updated expense successfully.',
        'data' => ['expense_id' => $expenseId],
    ]);
} catch (Throwable $e) {
    jsonResponse(500, [
        'success' => false,
        'message' => 'Failed to update expense.',
        'error' => $e->getMessage(),
        'data' => null,
    ]);
}
