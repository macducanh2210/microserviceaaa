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
$purpose = trim((string) ($input['purpose'] ?? ''));
$employeeId = isset($input['employee_id']) ? (int) $input['employee_id'] : null;
$amount = isset($input['amount']) ? (float) $input['amount'] : 0.0;
$note = trim((string) ($input['note'] ?? ''));
$status = isset($input['status']) ? (int) $input['status'] : 1;
$type = strtolower(trim((string) ($input['type'] ?? 'other')));
$category = strtolower(trim((string) ($input['category'] ?? 'other')));
$refCode = trim((string) ($input['ref_code'] ?? ''));
$expenseDate = trim((string) ($input['expense_date'] ?? ''));

if ($purpose === '') {
    jsonResponse(400, ['success' => false, 'message' => 'purpose la bat buoc.', 'data' => null]);
}

if ($amount == 0.0) {
    jsonResponse(400, ['success' => false, 'message' => 'amount phai khac 0.', 'data' => null]);
}

$allowedTypes = ['import', 'salary', 'other'];
if (!in_array($type, $allowedTypes, true)) {
    $type = 'other';
}

$allowedCategories = ['electric', 'water', 'internet', 'salary', 'import', 'office', 'maintenance', 'other'];
if (!in_array($category, $allowedCategories, true)) {
    $category = 'other';
}

if ($expenseDate !== '') {
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $expenseDate);
    if (!$dt) {
        jsonResponse(400, ['success' => false, 'message' => 'expense_date phai theo dinh dang YYYY-MM-DD HH:MM:SS.', 'data' => null]);
    }
}

try {
    $pdo = getPDO();

    if ($refCode !== '') {
        $dupStmt = $pdo->prepare('SELECT MACT FROM chitieu WHERE REF_CODE = :ref_code LIMIT 1');
        $dupStmt->execute(['ref_code' => $refCode]);
        if ($dupStmt->fetch()) {
            jsonResponse(409, [
                'success' => false,
                'message' => 'ref_code da ton tai.',
                'data' => null,
            ]);
        }
    }

        $sql = 'INSERT INTO chitieu (MUCDICHCHITIEU, IDNHANVIEN, SOTIEN, NGAYCHITIEU, GHICHU, TRANGTHAI, LOAI, DANHMUC, REF_CODE, IS_DELETED, DELETED_AT)
            VALUES (:purpose, :employee_id, :amount, :expense_date, :note, :status, :type, :category, :ref_code, 0, NULL)';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'purpose' => $purpose,
        'employee_id' => $employeeId,
        'amount' => $amount,
        'expense_date' => $expenseDate !== '' ? $expenseDate : date('Y-m-d H:i:s'),
        'note' => $note !== '' ? $note : null,
        'status' => $status,
        'type' => $type,
        'category' => $category,
        'ref_code' => $refCode !== '' ? $refCode : null,
    ]);

    $id = (int) $pdo->lastInsertId();

    jsonResponse(201, [
        'success' => true,
        'message' => 'Created expense successfully.',
        'data' => [
            'expense_id' => $id,
            'purpose' => $purpose,
            'employee_id' => $employeeId,
            'amount' => $amount,
            'type' => $type,
            'category' => $category,
            'ref_code' => $refCode !== '' ? $refCode : null,
        ],
    ]);
} catch (Throwable $e) {
    jsonResponse(500, [
        'success' => false,
        'message' => 'Failed to create expense.',
        'error' => $e->getMessage(),
        'data' => null,
    ]);
}
