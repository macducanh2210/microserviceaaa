<?php

declare(strict_types=1);

require_once __DIR__ . '/../../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResponse(200, ['success' => true, 'message' => 'Preflight OK', 'data' => null]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(405, ['success' => false, 'message' => 'Method not allowed. Use GET.', 'data' => null]);
}

$month = trim((string) ($_GET['month'] ?? ''));
$type = strtolower(trim((string) ($_GET['type'] ?? '')));
$category = strtolower(trim((string) ($_GET['category'] ?? '')));
$employeeId = isset($_GET['employee_id']) ? (int) $_GET['employee_id'] : 0;
$trashMode = strtolower(trim((string) ($_GET['trash_mode'] ?? 'active')));

$sql = 'SELECT MACT AS expense_id, MUCDICHCHITIEU AS purpose, IDNHANVIEN AS employee_id, SOTIEN AS amount, NGAYCHITIEU AS expense_date, GHICHU AS note, TRANGTHAI AS status, LOAI AS type, DANHMUC AS category, REF_CODE AS ref_code, IS_DELETED AS is_deleted, DELETED_AT AS deleted_at FROM chitieu WHERE 1=1';
$params = [];

if ($trashMode !== 'all' && $trashMode !== 'trash' && $trashMode !== 'active') {
    $trashMode = 'active';
}

if ($trashMode === 'active') {
    $sql .= ' AND IFNULL(IS_DELETED, 0) = 0';
} elseif ($trashMode === 'trash') {
    $sql .= ' AND IFNULL(IS_DELETED, 0) = 1';
}

if ($month !== '') {
    if (!preg_match('/^\\d{4}-(0[1-9]|1[0-2])$/', $month)) {
        jsonResponse(400, ['success' => false, 'message' => 'month phai theo dinh dang YYYY-MM.', 'data' => null]);
    }
    $sql .= ' AND DATE_FORMAT(NGAYCHITIEU, "%Y-%m") = :month';
    $params['month'] = $month;
}

if ($type !== '') {
    $sql .= ' AND LOAI = :type';
    $params['type'] = $type;
}

if ($category !== '') {
    $sql .= ' AND DANHMUC = :category';
    $params['category'] = $category;
}

if ($employeeId > 0) {
    $sql .= ' AND IDNHANVIEN = :employee_id';
    $params['employee_id'] = $employeeId;
}

$sql .= ' ORDER BY NGAYCHITIEU DESC, MACT DESC';

try {
    $pdo = getPDO();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['expense_id'] = (int) ($row['expense_id'] ?? 0);
        $row['employee_id'] = isset($row['employee_id']) ? (int) $row['employee_id'] : null;
        $row['amount'] = (float) ($row['amount'] ?? 0);
        $row['status'] = isset($row['status']) ? (int) $row['status'] : null;
        $row['is_deleted'] = (int) ($row['is_deleted'] ?? 0);
    }
    unset($row);

    jsonResponse(200, [
        'success' => true,
        'message' => 'Fetched expenses successfully.',
        'data' => $rows,
    ]);
} catch (Throwable $e) {
    jsonResponse(500, [
        'success' => false,
        'message' => 'Failed to fetch expenses.',
        'error' => $e->getMessage(),
        'data' => null,
    ]);
}
