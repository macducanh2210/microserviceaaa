<?php

declare(strict_types=1);

require_once __DIR__ . '/../../db.php';

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
$fullName = trim((string)($input['full_name'] ?? ''));
$email = trim((string)($input['email'] ?? ''));
$password = (string)($input['password'] ?? '');
$phone = trim((string)($input['phone'] ?? ''));
$address = trim((string)($input['address'] ?? ''));

if ($fullName === '' || $email === '' || $password === '') {
    jsonResponse(400, [
        'success' => false,
        'message' => 'full_name, email, password là bắt buộc.',
        'data' => null,
    ]);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(400, [
        'success' => false,
        'message' => 'Email không hợp lệ.',
        'data' => null,
    ]);
}

if (strlen($password) < 6) {
    jsonResponse(400, [
        'success' => false,
        'message' => 'Password phải có ít nhất 6 ký tự.',
        'data' => null,
    ]);
}

if (strlen($password) > 50) {
    jsonResponse(400, [
        'success' => false,
        'message' => 'Password tối đa 50 ký tự theo cấu trúc CSDL hiện tại.',
        'data' => null,
    ]);
}

try {
    $pdo = getPDO();

    $checkStmt = $pdo->prepare('SELECT ID FROM nhanvien WHERE EMAIL = :email LIMIT 1');
    $checkStmt->execute(['email' => $email]);

    if ($checkStmt->fetch()) {
        jsonResponse(409, [
            'success' => false,
            'message' => 'Email đã tồn tại.',
            'data' => null,
        ]);
    }

    // Column MATKHAU in current schema is varchar(50), so SHA-1 (40 chars) fits.
    // Keep this until schema is widened to store password_hash output.
    $storedPassword = sha1($password);

    $insertSql = <<<SQL
INSERT INTO nhanvien (
    HOTEN,
    NGAYSINH,
    GIOITINH,
    DIACHI,
    SODIENTHOAI,
    EMAIL,
    CHUCVU,
    MUCLUONG,
    ANHDAIDIEN,
    MATKHAU
) VALUES (
    :full_name,
    :birthday,
    :gender,
    :address,
    :phone,
    :email,
    :role,
    :salary,
    :avatar,
    :password
)
SQL;

    $defaultPosition = 'USER';
    $insertStmt = $pdo->prepare($insertSql);
    $insertStmt->execute([
        'full_name' => $fullName,
        'birthday' => '2000-01-01 00:00:00',
        'gender' => 1,
        'address' => $address !== '' ? $address : 'N/A',
        'phone' => $phone !== '' ? $phone : 'N/A',
        'email' => $email,
        'role' => $defaultPosition,
        'salary' => 0,
        'avatar' => 'default.png',
        'password' => $storedPassword,
    ]);

    $newUserId = (int)$pdo->lastInsertId();

    jsonResponse(201, [
        'success' => true,
        'message' => 'Đăng ký tài khoản thành công.',
        'data' => [
            'user_id' => $newUserId,
            'email' => $email,
            'full_name' => $fullName,
            'role' => normalizeUserRole($defaultPosition),
            'position_raw' => $defaultPosition,
        ],
    ]);
} catch (Throwable $e) {
    jsonResponse(500, [
        'success' => false,
        'message' => 'Lỗi máy chủ khi đăng ký tài khoản.',
        'error' => $e->getMessage(),
        'data' => null,
    ]);
}
