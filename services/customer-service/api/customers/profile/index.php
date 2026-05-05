<?php

require_once __DIR__ . '/../../../db.php';

// Get customer ID from URL or parameter
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
preg_match('/\/api\/customers\/(\d+)\/profile/', $path, $matches);
$requestedCustomerId = isset($matches[1]) ? (int)$matches[1] : 0;

if (!$requestedCustomerId) {
    jsonResponse(400, ['success' => false, 'message' => 'Customer ID required']);
}

$customerId = requireCustomerSession($requestedCustomerId);

try {
    $pdo = getPDO();
    
    if ($method === 'GET') {
        // Get profile
        $stmt = $pdo->prepare('
            SELECT ID, HOTEN, EMAIL, SODIENTHOAI, DIACHI, NGAYSINH, GIOITINH, TICHDIEM, EMAIL_VERIFIED, CREATED_AT 
            FROM khachhang 
            WHERE ID = ?
        ');
        $stmt->execute([$customerId]);
        $customer = $stmt->fetch();
        
        if (!$customer) {
            jsonResponse(404, ['success' => false, 'message' => 'Customer not found']);
        }
        
        jsonResponse(200, [
            'success' => true,
            'data' => [
                'id' => (int)$customer['ID'],
                'full_name' => $customer['HOTEN'],
                'email' => $customer['EMAIL'],
                'phone' => $customer['SODIENTHOAI'],
                'address' => $customer['DIACHI'],
                'dob' => $customer['NGAYSINH'],
                'gender' => (int)$customer['GIOITINH'],
                'loyalty_points' => (int)$customer['TICHDIEM'],
                'email_verified' => (bool)$customer['EMAIL_VERIFIED'],
                'created_at' => $customer['CREATED_AT']
            ]
        ]);
        
    } elseif ($method === 'PUT') {
        // Update profile
        $input = getJsonInput();
        
        // Allow updates to: HOTEN, SODIENTHOAI, DIACHI, NGAYSINH, GIOITINH
        $updates = [];
        $params = [];
        
        if (!empty($input['full_name'])) {
            $updates[] = 'HOTEN = ?';
            $params[] = trim($input['full_name']);
        }
        if (!empty($input['phone'])) {
            $updates[] = 'SODIENTHOAI = ?';
            $params[] = trim($input['phone']);
        }
        if (!empty($input['address'])) {
            $updates[] = 'DIACHI = ?';
            $params[] = trim($input['address']);
        }
        if (isset($input['dob']) && $input['dob']) {
            $updates[] = 'NGAYSINH = ?';
            $params[] = trim($input['dob']);
        }
        if (isset($input['gender'])) {
            $updates[] = 'GIOITINH = ?';
            $params[] = (int)$input['gender'];
        }
        
        if (empty($updates)) {
            jsonResponse(400, ['success' => false, 'message' => 'No fields to update']);
        }
        
        $params[] = $customerId;
        $sql = 'UPDATE khachhang SET ' . implode(', ', $updates) . ' WHERE ID = ?';
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        jsonResponse(200, [
            'success' => true,
            'message' => 'Profile updated successfully'
        ]);
    } else {
        jsonResponse(405, ['success' => false, 'message' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    jsonResponse(500, ['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
