<?php

declare(strict_types=1);

require_once __DIR__ . '/../../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResponse(200, ['success' => true, 'message' => 'Preflight OK', 'data' => null]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, [
        'success' => false,
        'message' => 'Method not allowed. Use POST.',
        'data' => null,
    ]);
}

$input = getJsonInput();
$supplierId = isset($input['supplier_id']) ? (int) $input['supplier_id'] : 0;
$employeeId = isset($input['employee_id']) ? (int) $input['employee_id'] : 0;
$items = $input['items'] ?? null;
$note = trim((string) ($input['note'] ?? ''));

if ($supplierId <= 0) {
    jsonResponse(400, [
        'success' => false,
        'message' => 'supplier_id is required.',
        'data' => null,
    ]);
}

if (!is_array($items) || count($items) === 0) {
    jsonResponse(400, [
        'success' => false,
        'message' => 'items must be a non-empty array.',
        'data' => null,
    ]);
}

try {
    $pdo = getPDO();

    $supplierStmt = $pdo->prepare('SELECT id FROM suppliers WHERE id = :id AND is_active = 1 LIMIT 1');
    $supplierStmt->execute(['id' => $supplierId]);
    if (!$supplierStmt->fetch()) {
        jsonResponse(404, [
            'success' => false,
            'message' => 'Supplier not found or inactive.',
            'data' => null,
        ]);
    }

    $normalizedItems = [];
    $totalAmount = 0.0;

    foreach ($items as $idx => $item) {
        if (!is_array($item)) {
            throw new InvalidArgumentException('Item at index ' . $idx . ' is invalid.');
        }

        $productId = isset($item['product_id']) ? (int) $item['product_id'] : 0;
        $quantity = isset($item['quantity']) ? (int) $item['quantity'] : 0;
        $importPrice = isset($item['import_price']) ? (float) $item['import_price'] : -1;

        if ($productId <= 0 || $quantity <= 0 || $importPrice < 0) {
            throw new InvalidArgumentException('Invalid item at index ' . $idx . '.');
        }

        $lineTotal = $quantity * $importPrice;
        $normalizedItems[] = [
            'product_id' => $productId,
            'quantity' => $quantity,
            'import_price' => $importPrice,
            'line_total' => $lineTotal,
        ];

        $totalAmount += $lineTotal;
    }

    $pdo->beginTransaction();

    $orderStmt = $pdo->prepare('INSERT INTO purchase_orders (supplier_id, total_amount, note) VALUES (:supplier_id, :total_amount, :note)');
    $orderStmt->execute([
        'supplier_id' => $supplierId,
        'total_amount' => $totalAmount,
        'note' => $note !== '' ? $note : null,
    ]);
    $purchaseOrderId = (int) $pdo->lastInsertId();

    $detailStmt = $pdo->prepare('INSERT INTO purchase_order_details (purchase_order_id, product_id, quantity, import_price, line_total) VALUES (:purchase_order_id, :product_id, :quantity, :import_price, :line_total)');
    foreach ($normalizedItems as $line) {
        $detailStmt->execute([
            'purchase_order_id' => $purchaseOrderId,
            'product_id' => $line['product_id'],
            'quantity' => $line['quantity'],
            'import_price' => $line['import_price'],
            'line_total' => $line['line_total'],
        ]);
    }

    $stockResult = iv_callUpdateStock(array_map(static function (array $line): array {
        return [
            'product_id' => (int) $line['product_id'],
            'quantity' => (int) $line['quantity'],
            'import_price' => (float) $line['import_price'],
        ];
    }, $normalizedItems));

    $pdo->commit();

    $expenseLog = null;
    $expenseError = null;
    try {
        $expenseLog = iv_logImportExpense($purchaseOrderId, $employeeId, $totalAmount, $supplierId, $note);
    } catch (Throwable $expenseEx) {
        $expenseError = $expenseEx->getMessage();
    }

    jsonResponse(201, [
        'success' => true,
        'message' => 'Purchase order created successfully.',
        'data' => [
            'purchase_order_id' => $purchaseOrderId,
            'supplier_id' => $supplierId,
            'total_amount' => $totalAmount,
            'items_count' => count($normalizedItems),
            'stock_update' => $stockResult,
            'expense_log' => $expenseLog,
            'expense_warning' => $expenseError,
        ],
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $statusCode = $e instanceof InvalidArgumentException ? 400 : 500;
    jsonResponse($statusCode, [
        'success' => false,
        'message' => 'Failed to create purchase order.',
        'error' => $e->getMessage(),
        'data' => null,
    ]);
}
