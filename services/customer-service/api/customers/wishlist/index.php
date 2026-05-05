<?php

require_once __DIR__ . '/../../../db.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Parse customer ID from URL
preg_match('/\/api\/customers\/(\d+)\/wishlist/', $path, $matches);
$requestedCustomerId = isset($matches[1]) ? (int)$matches[1] : 0;

if (!$requestedCustomerId) {
    jsonResponse(400, ['success' => false, 'message' => 'Customer ID required']);
}

$customerId = requireCustomerSession($requestedCustomerId);

try {
    $pdo = getPDO();
    
    if ($method === 'GET') {
        // List wishlist items
        $stmt = $pdo->prepare('
            SELECT ID, IDCHITIETSANPHAM, CREATED_AT 
            FROM danh_sach_yeu_thich 
            WHERE IDKHACHHANG = ? 
            ORDER BY CREATED_AT DESC
        ');
        $stmt->execute([$customerId]);
        $items = $stmt->fetchAll();
        
        jsonResponse(200, [
            'success' => true,
            'data' => array_map(function($item) {
                return [
                    'id' => (int)$item['ID'],
                    'product_detail_id' => (int)$item['IDCHITIETSANPHAM'],
                    'added_at' => $item['CREATED_AT']
                ];
            }, $items)
        ]);
        
    } elseif ($method === 'POST') {
        // Add to wishlist
        $input = getJsonInput();
        
        if (empty($input['product_detail_id'])) {
            jsonResponse(400, ['success' => false, 'message' => 'product_detail_id required']);
        }
        
        $detailId = (int)$input['product_detail_id'];
        
        try {
            $stmt = $pdo->prepare('
                INSERT INTO danh_sach_yeu_thich (IDKHACHHANG, IDCHITIETSANPHAM) 
                VALUES (?, ?)
            ');
            $stmt->execute([$customerId, $detailId]);
            
            jsonResponse(201, [
                'success' => true,
                'message' => 'Added to wishlist',
                'data' => ['id' => (int)$pdo->lastInsertId()]
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                // Duplicate entry
                jsonResponse(409, ['success' => false, 'message' => 'Already in wishlist']);
            }
            throw $e;
        }
    } else {
        jsonResponse(405, ['success' => false, 'message' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    jsonResponse(500, ['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
