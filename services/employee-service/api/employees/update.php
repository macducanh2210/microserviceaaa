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
$id = isset($input['id']) ? (int) $input['id'] : 0;

if ($id <= 0) {
    jsonResponse(400, [
        'success' => false,
        'message' => 'id is required.',
        'data' => null,
    ]);
}

$allowedFields = [
    'full_name',
    'email',
    'phone',
    'position',
    'role_level',
    'salary',
    'status',
];

$updates = [];
$params = ['id' => $id];

foreach ($allowedFields as $field) {
    if (!array_key_exists($field, $input)) {
        continue;
    }

    $value = $input[$field];

    if ($field === 'full_name' || $field === 'email' || $field === 'phone' || $field === 'position' || $field === 'status') {
        $value = trim((string) $value);
    }

    if ($field === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(400, [
            'success' => false,
            'message' => 'Invalid email format.',
            'data' => null,
        ]);
    }

    if ($field === 'role_level') {
        $value = (int) $value;
        if ($value <= 0) {
            jsonResponse(400, [
                'success' => false,
                'message' => 'role_level must be greater than 0.',
                'data' => null,
            ]);
        }
    }

    if ($field === 'salary') {
        $value = (float) $value;
        if ($value < 0) {
            jsonResponse(400, [
                'success' => false,
                'message' => 'salary must be >= 0.',
                'data' => null,
            ]);
        }
    }

    if ($field === 'status') {
        if ($value !== 'active' && $value !== 'inactive') {
            jsonResponse(400, [
                'success' => false,
                'message' => "status must be 'active' or 'inactive'.",
                'data' => null,
            ]);
        }
    }

    $updates[] = $field . ' = :' . $field;
    $params[$field] = $value;
}

if (count($updates) === 0) {
    jsonResponse(400, [
        'success' => false,
        'message' => 'No fields to update.',
        'data' => null,
    ]);
}

try {
    $pdo = getPDO();

    $existsStmt = $pdo->prepare('SELECT id FROM employees WHERE id = :id LIMIT 1');
    $existsStmt->execute(['id' => $id]);
    if (!$existsStmt->fetch()) {
        jsonResponse(404, [
            'success' => false,
            'message' => 'Employee not found.',
            'data' => null,
        ]);
    }

    $sql = 'UPDATE employees SET ' . implode(', ', $updates) . ' WHERE id = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    jsonResponse(200, [
        'success' => true,
        'message' => 'Employee updated successfully.',
        'data' => [
            'id' => $id,
            'updated_fields' => array_keys(array_diff_key($params, ['id' => true])),
        ],
    ]);
} catch (Throwable $e) {
    $message = $e->getMessage();
    $statusCode = stripos($message, 'Duplicate') !== false ? 409 : 500;

    jsonResponse($statusCode, [
        'success' => false,
        'message' => 'Failed to update employee.',
        'error' => $message,
        'data' => null,
    ]);
}
