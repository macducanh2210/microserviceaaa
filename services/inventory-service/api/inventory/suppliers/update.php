<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../db.php';

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
if ($supplierId <= 0) {
    jsonResponse(400, [
        'success' => false,
        'message' => 'supplier_id is required.',
        'data' => null,
    ]);
}

$fields = [];
$params = ['id' => $supplierId];

if (array_key_exists('supplier_name', $input) || array_key_exists('name', $input)) {
    $supplierName = trim((string) ($input['supplier_name'] ?? $input['name'] ?? ''));
    if ($supplierName === '') {
        jsonResponse(400, [
            'success' => false,
            'message' => 'supplier_name cannot be empty.',
            'data' => null,
        ]);
    }
    $fields[] = 'supplier_name = :supplier_name';
    $params['supplier_name'] = $supplierName;
}

if (array_key_exists('contact_name', $input)) {
    $fields[] = 'contact_name = :contact_name';
    $contact = trim((string) $input['contact_name']);
    $params['contact_name'] = $contact !== '' ? $contact : null;
}

if (array_key_exists('phone', $input)) {
    $fields[] = 'phone = :phone';
    $phone = trim((string) $input['phone']);
    $params['phone'] = $phone !== '' ? $phone : null;
}

if (array_key_exists('email', $input)) {
    $fields[] = 'email = :email';
    $email = trim((string) $input['email']);
    $params['email'] = $email !== '' ? $email : null;
}

if (array_key_exists('address', $input)) {
    $fields[] = 'address = :address';
    $address = trim((string) $input['address']);
    $params['address'] = $address !== '' ? $address : null;
}

if (array_key_exists('is_active', $input)) {
    $fields[] = 'is_active = :is_active';
    $params['is_active'] = (int) ((int) $input['is_active'] > 0);
}

if (count($fields) === 0) {
    jsonResponse(400, [
        'success' => false,
        'message' => 'No fields to update.',
        'data' => null,
    ]);
}

try {
    $pdo = getPDO();

    $existsStmt = $pdo->prepare('SELECT id FROM suppliers WHERE id = :id LIMIT 1');
    $existsStmt->execute(['id' => $supplierId]);
    if (!$existsStmt->fetch()) {
        jsonResponse(404, [
            'success' => false,
            'message' => 'Supplier not found.',
            'data' => null,
        ]);
    }

    $sql = 'UPDATE suppliers SET ' . implode(', ', $fields) . ' WHERE id = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    jsonResponse(200, [
        'success' => true,
        'message' => 'Supplier updated successfully.',
        'data' => [
            'supplier_id' => $supplierId,
            'updated_rows' => $stmt->rowCount(),
        ],
    ]);
} catch (Throwable $e) {
    jsonResponse(500, [
        'success' => false,
        'message' => 'Internal server error when updating supplier.',
        'error' => $e->getMessage(),
        'data' => null,
    ]);
}
