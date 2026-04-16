<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'service' => 'attendance-service',
    'message' => 'Attendance service is running.',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
