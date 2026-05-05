<?php

require_once __DIR__ . '/../../db.php';

session_start();

// Clear session
session_destroy();

jsonResponse(200, [
    'success' => true,
    'message' => 'Logout successful'
]);
