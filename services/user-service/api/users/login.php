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
$email = trim((string)($input['email'] ?? ''));
$password = (string)($input['password'] ?? '');

if ($email === '' || $password === '') {
    jsonResponse(400, [
        'success' => false,
        'message' => 'Email và password là bắt buộc.',
        'data' => null,
    ]);
}

try {
    $pdo = getPDO();

    $stmt = $pdo->prepare('SELECT ID, HOTEN, EMAIL, MATKHAU, CHUCVU FROM nhanvien WHERE EMAIL = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user) {
        jsonResponse(401, [
            'success' => false,
            'message' => 'Email hoặc password không đúng.',
            'data' => null,
        ]);
    }

    $storedPassword = (string)$user['MATKHAU'];
    $validPassword = false;

    // Backward-compatible verification for current schema data.
    if (strpos($storedPassword, '$2y$') === 0 || strpos($storedPassword, '$argon2') === 0) {
        $validPassword = password_verify($password, $storedPassword);
    } elseif (strlen($storedPassword) === 40) {
        $validPassword = hash_equals($storedPassword, sha1($password));
    } else {
        $validPassword = hash_equals($storedPassword, $password);
    }

    if (!$validPassword) {
        jsonResponse(401, [
            'success' => false,
            'message' => 'Email hoặc password không đúng.',
            'data' => null,
        ]);
    }

    $role = normalizeUserRole((string) ($user['CHUCVU'] ?? 'USER'));

    jsonResponse(200, [
        'success' => true,
        'message' => 'Đăng nhập thành công.',
        'data' => [
            'user_id' => (int)$user['ID'],
            'full_name' => $user['HOTEN'],
            'email' => $user['EMAIL'],
            'role' => $role,
            'position_raw' => (string) ($user['CHUCVU'] ?? 'USER'),
        ],
        'frontend_hint' => [
            'local_storage_key' => 'fashion_user_id',
            'example' => "localStorage.setItem('fashion_user_id', String(response.data.user_id));",
        ],
    ]);
} catch (Throwable $e) {
    jsonResponse(500, [
        'success' => false,
        'message' => 'Lỗi máy chủ khi đăng nhập.',
        'error' => $e->getMessage(),
        'data' => null,
    ]);
}
