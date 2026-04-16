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

    $existsStmt = $pdo->prepare('SELECT MACT FROM chitieu WHERE MACT = :expense_id LIMIT 1');
    $existsStmt->execute(['expense_id' => $expenseId]);
    if (!$existsStmt->fetch()) {
        jsonResponse(404, ['success' => false, 'message' => 'Khong tim thay chi tieu.', 'data' => null]);
    }

    $stmt = $pdo->prepare('UPDATE chitieu SET IS_DELETED = 0, DELETED_AT = NULL WHERE MACT = :expense_id');
    $stmt->execute(['expense_id' => $expenseId]);

    jsonResponse(200, [
        'success' => true,
        'message' => 'Da khoi phuc chi tieu tu thung rac.',
        'data' => ['expense_id' => $expenseId],
    ]);
} catch (Throwable $e) {
    jsonResponse(500, [
        'success' => false,
        'message' => 'Failed to restore expense.',
        'error' => $e->getMessage(),
        'data' => null,
    ]);
}
