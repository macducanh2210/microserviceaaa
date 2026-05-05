<?php

declare(strict_types=1);

// Endpoint: /api/customers/{id}/orders[...]
// Proxy sang order-service để xử lý đơn hàng của customer

require_once __DIR__ . '/../../../db.php';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
preg_match('/\/api\/customers\/(\d+)\/orders(?:\/(\d+))?/', $path, $matches);
$requestedCustomerId = isset($matches[1]) ? (int)$matches[1] : 0;
$orderId = isset($matches[2]) ? (int)$matches[2] : 0;

if (!$requestedCustomerId) {
    jsonResponse(400, ['success' => false, 'message' => 'Customer ID required']);
}

$customerId = requireCustomerSession($requestedCustomerId);

$method = $_SERVER['REQUEST_METHOD'];
$orderServiceUrl = '';
$internalKey = (string)getenv('INTERNAL_API_KEY');
if ($internalKey === '') {
    $internalKey = (string)($_ENV['INTERNAL_API_KEY'] ?? '');
}
if ($internalKey === '') {
    $internalKey = (string)($_SERVER['INTERNAL_API_KEY'] ?? '');
}
$internalKey = trim($internalKey);
$options = [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 3,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_HTTPHEADER     => [
        'Accept: application/json', 
        'Content-Type: application/json',
        'X-Internal-Key: ' . $internalKey
    ],
];

if ($method === 'GET') {
    if ($orderId) {
        $orderServiceUrl = "http://order-service/api/orders/detail.php?customer_id={$customerId}&order_id={$orderId}";
    } else {
        $orderServiceUrl = "http://order-service/api/orders/list.php?customer_id={$customerId}";
    }
} elseif ($method === 'POST') {
    if ($orderId) {
        // Cancel order (since HTML forms use POST or DELETE, assuming POST for cancellation if orderId is specified)
        // Wait, frontend uses POST to delete.php with JSON body
        $orderServiceUrl = "http://order-service/api/orders/delete.php";
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = json_encode(['customer_id' => $customerId, 'order_id' => $orderId]);
    } else {
        // Create order
        $orderServiceUrl = "http://order-service/api/orders/create.php";
        $input = getJsonInput();
        $input['customer_id'] = $customerId; // Enforce customer ID
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = json_encode($input);
    }
} elseif ($method === 'DELETE' && $orderId) {
    // Alternative cancel route
    $orderServiceUrl = "http://order-service/api/orders/delete.php";
    $options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
    $options[CURLOPT_POSTFIELDS] = json_encode(['customer_id' => $customerId, 'order_id' => $orderId]);
} else {
    jsonResponse(405, ['success' => false, 'message' => 'Method not allowed']);
}

$options[CURLOPT_URL] = $orderServiceUrl;

$ch = curl_init();
curl_setopt_array($ch, $options);

$response   = curl_exec($ch);
$statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError  = curl_error($ch);
curl_close($ch);

header('Content-Type: application/json');

if ($response === false || $curlError) {
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => 'Không thể kết nối order-service: ' . $curlError]);
    exit;
}

http_response_code($statusCode ?: 200);
echo $response;
exit;
