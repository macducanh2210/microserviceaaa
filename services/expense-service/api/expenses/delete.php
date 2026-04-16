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

try {
    $pdo = getPDO();

    $existsStmt = $pdo->prepare('SELECT MACT, IFNULL(IS_DELETED, 0) AS is_deleted FROM chitieu WHERE MACT = :expense_id LIMIT 1');
    $existsStmt->execute(['expense_id' => $expenseId]);
    $row = $existsStmt->fetch();

    if (!$row) {
        jsonResponse(404, ['success' => false, 'message' => 'Khong tim thay chi tieu.', 'data' => null]);
    }

    if ((int) ($row['is_deleted'] ?? 0) === 1) {
        jsonResponse(200, ['success' => true, 'message' => 'Chi tieu da nam trong thung rac.', 'data' => ['expense_id' => $expenseId]]);
    }

    $stmt = $pdo->prepare('UPDATE chitieu SET IS_DELETED = 1, DELETED_AT = NOW() WHERE MACT = :expense_id');
    $stmt->execute(['expense_id' => $expenseId]);

    jsonResponse(200, [
        'success' => true,
        'message' => 'Da chuyen chi tieu vao thung rac.',
        'data' => ['expense_id' => $expenseId],
    ]);
} catch (Throwable $e) {
    jsonResponse(500, [
        'success' => false,
        'message' => 'Failed to move expense to trash.',
        'error' => $e->getMessage(),
        'data' => null,
    ]);
}
