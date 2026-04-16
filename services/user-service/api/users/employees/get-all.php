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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(405, [
        'success' => false,
        'message' => 'Method not allowed. Use GET.',
        'data' => null,
    ]);
}

try {
    $pdo = getPDO();

    $sql = <<<SQL
SELECT
    ID AS id,
    HOTEN AS full_name,
    NGAYSINH AS birthday,
    GIOITINH AS gender,
    DIACHI AS address,
    SODIENTHOAI AS phone,
    EMAIL AS email,
    CHUCVU AS position,
    MUCLUONG AS salary,
    ANHDAIDIEN AS avatar
FROM nhanvien
ORDER BY ID DESC
SQL;

    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['id'] = (int) ($row['id'] ?? 0);
        $row['gender'] = (int) ($row['gender'] ?? 1);
        $row['salary'] = (float) ($row['salary'] ?? 0);
    }
    unset($row);

    jsonResponse(200, [
        'success' => true,
        'message' => 'Fetched employees from user_db successfully.',
        'data' => $rows,
    ]);
} catch (Throwable $e) {
    jsonResponse(500, [
        'success' => false,
        'message' => 'Failed to fetch employees.',
        'error' => $e->getMessage(),
        'data' => null,
    ]);
}
