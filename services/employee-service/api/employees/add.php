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

$fullName = trim((string) ($input['full_name'] ?? ''));
$email = trim((string) ($input['email'] ?? ''));
$phone = trim((string) ($input['phone'] ?? ''));
$position = trim((string) ($input['position'] ?? ''));
$roleLevel = isset($input['role_level']) ? (int) $input['role_level'] : 0;
$salary = isset($input['salary']) ? (float) $input['salary'] : -1;
$status = trim((string) ($input['status'] ?? 'active'));

if ($fullName === '' || $email === '' || $phone === '' || $position === '' || $roleLevel <= 0 || $salary < 0) {
    jsonResponse(400, [
        'success' => false,
        'message' => 'Missing or invalid required fields.',
        'data' => null,
    ]);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(400, [
        'success' => false,
        'message' => 'Invalid email format.',
        'data' => null,
    ]);
}

if ($status !== 'active' && $status !== 'inactive') {
    $status = 'active';
}

try {
    $pdo = getPDO();

    $stmt = $pdo->prepare(
        'INSERT INTO employees (full_name, email, phone, position, role_level, salary, status) VALUES (:full_name, :email, :phone, :position, :role_level, :salary, :status)'
    );

    $stmt->execute([
        'full_name' => $fullName,
        'email' => $email,
        'phone' => $phone,
        'position' => $position,
        'role_level' => $roleLevel,
        'salary' => $salary,
        'status' => $status,
    ]);

    jsonResponse(201, [
        'success' => true,
        'message' => 'Employee added successfully.',
        'data' => [
            'id' => (int) $pdo->lastInsertId(),
            'full_name' => $fullName,
            'email' => $email,
            'phone' => $phone,
            'position' => $position,
            'role_level' => $roleLevel,
            'salary' => $salary,
            'status' => $status,
        ],
    ]);
} catch (Throwable $e) {
    $message = $e->getMessage();
    $statusCode = stripos($message, 'Duplicate') !== false ? 409 : 500;

    jsonResponse($statusCode, [
        'success' => false,
        'message' => 'Failed to add employee.',
        'error' => $message,
        'data' => null,
    ]);
}
