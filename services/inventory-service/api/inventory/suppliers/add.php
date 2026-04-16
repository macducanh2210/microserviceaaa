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
$supplierName = trim((string) ($input['supplier_name'] ?? $input['name'] ?? ''));
$contactName = trim((string) ($input['contact_name'] ?? ''));
$phone = trim((string) ($input['phone'] ?? ''));
$email = trim((string) ($input['email'] ?? ''));
$address = trim((string) ($input['address'] ?? ''));

if ($supplierName === '') {
    jsonResponse(400, [
        'success' => false,
        'message' => 'supplier_name is required.',
        'data' => null,
    ]);
}

try {
    $pdo = getPDO();

    $stmt = $pdo->prepare('INSERT INTO suppliers (supplier_name, contact_name, phone, email, address, is_active) VALUES (:supplier_name, :contact_name, :phone, :email, :address, 1)');
    $stmt->execute([
        'supplier_name' => $supplierName,
        'contact_name' => $contactName !== '' ? $contactName : null,
        'phone' => $phone !== '' ? $phone : null,
        'email' => $email !== '' ? $email : null,
        'address' => $address !== '' ? $address : null,
    ]);

    jsonResponse(201, [
        'success' => true,
        'message' => 'Supplier created successfully.',
        'data' => [
            'supplier_id' => (int) $pdo->lastInsertId(),
            'supplier_name' => $supplierName,
        ],
    ]);
} catch (Throwable $e) {
    jsonResponse(500, [
        'success' => false,
        'message' => 'Internal server error when creating supplier.',
        'error' => $e->getMessage(),
        'data' => null,
    ]);
}
