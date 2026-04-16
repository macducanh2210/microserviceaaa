<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResponse(200, [
        'success' => true,
        'message' => 'Preflight OK',
        'data' => null,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, [
        'success' => false,
        'message' => 'Method not allowed. Use POST.',
        'data' => null,
    ]);
}

$input = getJsonInput();
$id = (int) ($input['id'] ?? 0);

if ($id <= 0) {
    jsonResponse(400, [
        'success' => false,
        'message' => 'id khong hop le.',
        'data' => null,
    ]);
}

$allowedMap = [
    'full_name' => 'HOTEN',
    'birthday' => 'NGAYSINH',
    'gender' => 'GIOITINH',
    'address' => 'DIACHI',
    'phone' => 'SODIENTHOAI',
    'email' => 'EMAIL',
    'position' => 'CHUCVU',
    'salary' => 'MUCLUONG',
    'avatar' => 'ANHDAIDIEN',
];

$updates = [];
$params = ['id' => $id];

foreach ($allowedMap as $key => $column) {
    if (!array_key_exists($key, $input)) {
        continue;
    }

    $value = $input[$key];

    if ($key === 'email') {
        $email = trim((string) $value);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(400, [
                'success' => false,
                'message' => 'Email khong hop le.',
                'data' => null,
            ]);
        }
        $value = $email;
    }

    if ($key === 'salary') {
        $salary = (float) $value;
        if ($salary < 0) {
            jsonResponse(400, [
                'success' => false,
                'message' => 'Salary khong duoc am.',
                'data' => null,
            ]);
        }
        $value = $salary;
    }

    if ($key === 'gender') {
        $gender = (int) $value;
        if ($gender !== 0 && $gender !== 1) {
            jsonResponse(400, [
                'success' => false,
                'message' => 'gender chi nhan 0 hoac 1.',
                'data' => null,
            ]);
        }
        $value = $gender;
    }

    if ($key === 'birthday') {
        $birthdayRaw = trim((string) $value);
        $birthdayDate = DateTime::createFromFormat('Y-m-d', $birthdayRaw);
        if (!$birthdayDate) {
            jsonResponse(400, [
                'success' => false,
                'message' => 'birthday phai co dang YYYY-MM-DD.',
                'data' => null,
            ]);
        }
        $value = $birthdayDate->format('Y-m-d 00:00:00');
    }

    if (in_array($key, ['full_name', 'address', 'phone', 'position', 'avatar'], true)) {
        $value = trim((string) $value);
    }

    $paramKey = 'p_' . $key;
    $updates[] = $column . ' = :' . $paramKey;
    $params[$paramKey] = $value;
}

if (array_key_exists('password', $input)) {
    $passwordRaw = (string) $input['password'];
    if ($passwordRaw !== '') {
        if (strlen($passwordRaw) < 6) {
            jsonResponse(400, [
                'success' => false,
                'message' => 'Password toi thieu 6 ky tu.',
                'data' => null,
            ]);
        }

        $updates[] = 'MATKHAU = :p_password';
        $params['p_password'] = sha1($passwordRaw);
    }
}

if (!$updates) {
    jsonResponse(400, [
        'success' => false,
        'message' => 'Khong co truong nao de cap nhat.',
        'data' => null,
    ]);
}

try {
    $pdo = getPDO();

    $existsStmt = $pdo->prepare('SELECT ID FROM nhanvien WHERE ID = :id LIMIT 1');
    $existsStmt->execute(['id' => $id]);
    if (!$existsStmt->fetch()) {
        jsonResponse(404, [
            'success' => false,
            'message' => 'Employee khong ton tai.',
            'data' => null,
        ]);
    }

    if (array_key_exists('p_email', $params)) {
        $dupStmt = $pdo->prepare('SELECT ID FROM nhanvien WHERE EMAIL = :email AND ID <> :id LIMIT 1');
        $dupStmt->execute([
            'email' => $params['p_email'],
            'id' => $id,
        ]);

        if ($dupStmt->fetch()) {
            jsonResponse(409, [
                'success' => false,
                'message' => 'Email da duoc su dung boi nhan vien khac.',
                'data' => null,
            ]);
        }
    }

    $sql = 'UPDATE nhanvien SET ' . implode(', ', $updates) . ' WHERE ID = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    jsonResponse(200, [
        'success' => true,
        'message' => 'Updated employee in user_db successfully.',
        'data' => [
            'id' => $id,
        ],
    ]);
} catch (Throwable $e) {
    jsonResponse(500, [
        'success' => false,
        'message' => 'Failed to update employee.',
        'error' => $e->getMessage(),
        'data' => null,
    ]);
}
