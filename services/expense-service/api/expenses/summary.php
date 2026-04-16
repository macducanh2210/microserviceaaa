<?php

declare(strict_types=1);

require_once __DIR__ . '/../../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResponse(200, ['success' => true, 'message' => 'Preflight OK', 'data' => null]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(405, ['success' => false, 'message' => 'Method not allowed. Use GET.', 'data' => null]);
}

$month = trim((string) ($_GET['month'] ?? date('Y-m')));
$trashMode = strtolower(trim((string) ($_GET['trash_mode'] ?? 'active')));
if (!preg_match('/^\\d{4}-(0[1-9]|1[0-2])$/', $month)) {
    jsonResponse(400, ['success' => false, 'message' => 'month phai theo dinh dang YYYY-MM.', 'data' => null]);
}

if ($trashMode !== 'all' && $trashMode !== 'trash' && $trashMode !== 'active') {
    $trashMode = 'active';
}

$trashWhere = '';
if ($trashMode === 'active') {
    $trashWhere = ' AND IFNULL(IS_DELETED, 0) = 0';
} elseif ($trashMode === 'trash') {
    $trashWhere = ' AND IFNULL(IS_DELETED, 0) = 1';
}

try {
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT LOAI AS type, COUNT(*) AS total_rows, SUM(SOTIEN) AS total_amount FROM chitieu WHERE DATE_FORMAT(NGAYCHITIEU, "%Y-%m") = :month' . $trashWhere . ' GROUP BY LOAI');
    $stmt->execute(['month' => $month]);
    $rows = $stmt->fetchAll();

    $byType = [];
    foreach ($rows as $row) {
        $type = (string) ($row['type'] ?? 'other');
        $byType[$type] = [
            'type' => $type,
            'total_rows' => (int) ($row['total_rows'] ?? 0),
            'total_amount' => (float) ($row['total_amount'] ?? 0),
        ];
    }

    $allStmt = $pdo->prepare('SELECT COUNT(*) AS total_rows, SUM(SOTIEN) AS total_amount FROM chitieu WHERE DATE_FORMAT(NGAYCHITIEU, "%Y-%m") = :month' . $trashWhere);
    $allStmt->execute(['month' => $month]);
    $all = $allStmt->fetch() ?: ['total_rows' => 0, 'total_amount' => 0];

    jsonResponse(200, [
        'success' => true,
        'message' => 'Fetched expense summary successfully.',
        'data' => [
            'month' => $month,
            'total_rows' => (int) ($all['total_rows'] ?? 0),
            'total_amount' => (float) ($all['total_amount'] ?? 0),
            'by_type' => array_values($byType),
        ],
    ]);
} catch (Throwable $e) {
    jsonResponse(500, [
        'success' => false,
        'message' => 'Failed to fetch expense summary.',
        'error' => $e->getMessage(),
        'data' => null,
    ]);
}
