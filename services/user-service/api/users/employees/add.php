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
$fullName = trim((string) ($input['full_name'] ?? ''));
$email = trim((string) ($input['email'] ?? ''));
$phone = trim((string) ($input['phone'] ?? ''));
$address = trim((string) ($input['address'] ?? ''));
$position = trim((string) ($input['position'] ?? 'NHAN VIEN'));
$salary = (float) ($input['salary'] ?? 0);
$avatar = trim((string) ($input['avatar'] ?? 'default.png'));
$birthdayInput = trim((string) ($input['birthday'] ?? '2000-01-01'));
$gender = (int) ($input['gender'] ?? 1);
$passwordRaw = (string) ($input['password'] ?? '12345678');

if ($fullName === '' || $email === '') {
    jsonResponse(400, [
        'success' => false,
        'message' => 'full_name va email la bat buoc.',
        'data' => null,
    ]);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(400, [
        'success' => false,
        'message' => 'Email khong hop le.',
        'data' => null,
    ]);
}

if ($salary < 0) {
    jsonResponse(400, [
        'success' => false,
        'message' => 'Salary khong duoc am.',
        'data' => null,
    ]);
}

if ($gender !== 0 && $gender !== 1) {
    $gender = 1;
}

$birthdayDate = DateTime::createFromFormat('Y-m-d', $birthdayInput);
if (!$birthdayDate) {
    $birthdayDate = new DateTime('2000-01-01');
}
$birthday = $birthdayDate->format('Y-m-d 00:00:00');

if (strlen($passwordRaw) < 6) {
    jsonResponse(400, [
        'success' => false,
        'message' => 'Password toi thieu 6 ky tu.',
        'data' => null,
    ]);
}

$storedPassword = sha1($passwordRaw);

try {
    $pdo = getPDO();

    $existsStmt = $pdo->prepare('SELECT ID FROM nhanvien WHERE EMAIL = :email LIMIT 1');
    $existsStmt->execute(['email' => $email]);
    if ($existsStmt->fetch()) {
        jsonResponse(409, [
            'success' => false,
            'message' => 'Email da ton tai.',
            'data' => null,
        ]);
    }

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
    :position,
    :salary,
    :avatar,
    :password
)
SQL;

    $stmt = $pdo->prepare($insertSql);
    $stmt->execute([
        'full_name' => $fullName,
        'birthday' => $birthday,
        'gender' => $gender,
        'address' => $address !== '' ? $address : 'N/A',
        'phone' => $phone !== '' ? $phone : 'N/A',
        'email' => $email,
        'position' => $position,
        'salary' => $salary,
        'avatar' => $avatar !== '' ? $avatar : 'default.png',
        'password' => $storedPassword,
    ]);

    $id = (int) $pdo->lastInsertId();

    jsonResponse(201, [
        'success' => true,
        'message' => 'Created employee in user_db successfully.',
        'data' => [
            'id' => $id,
            'full_name' => $fullName,
            'email' => $email,
            'phone' => $phone,
            'address' => $address,
            'position' => $position,
            'salary' => $salary,
            'avatar' => $avatar,
            'birthday' => substr($birthday, 0, 10),
            'gender' => $gender,
        ],
    ]);
} catch (Throwable $e) {
    jsonResponse(500, [
        'success' => false,
        'message' => 'Failed to create employee.',
        'error' => $e->getMessage(),
        'data' => null,
    ]);
}
