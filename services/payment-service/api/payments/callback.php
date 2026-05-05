<?php

declare(strict_types=1);

require_once __DIR__ . '/../../db.php';

$configPath = __DIR__ . '/../../config_momo.json';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Missing MoMo config file', 'path' => $configPath]);
    exit;
}

$config = json_decode(file_get_contents($configPath), true);
if (!is_array($config)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Invalid MoMo config file']);
    exit;
}

// Handle callback from MoMo
$partnerCode = $_POST['partnerCode'] ?? '';
$orderId = $_POST['orderId'] ?? '';
$requestId = $_POST['requestId'] ?? '';
$amount = $_POST['amount'] ?? '';
$orderInfo = $_POST['orderInfo'] ?? '';
$orderType = $_POST['orderType'] ?? '';
$transId = $_POST['transId'] ?? '';
$resultCode = $_POST['resultCode'] ?? '';
$message = $_POST['message'] ?? '';
$payType = $_POST['payType'] ?? '';
$responseTime = $_POST['responseTime'] ?? '';
$extraData = $_POST['extraData'] ?? '';
$signature = $_POST['signature'] ?? '';

$secretKey = $config['secretKey'];
$accessKey = $config['accessKey'];

$rawHash = "accessKey=" . $accessKey . "&amount=" . $amount . "&extraData=" . $extraData . "&message=" . $message . "&orderId=" . $orderId . "&orderInfo=" . $orderInfo . "&orderType=" . $orderType . "&partnerCode=" . $partnerCode . "&payType=" . $payType . "&requestId=" . $requestId . "&responseTime=" . $responseTime . "&resultCode=" . $resultCode . "&transId=" . $transId;

$partnerSignature = hash_hmac('sha256', $rawHash, $secretKey);

if ($signature == $partnerSignature) {
    if ($resultCode == '0') {
        // Payment successful
        // Since we treat as always successful, just log
        error_log("MoMo payment successful for orderId: $orderId");
    } else {
        // Payment failed, but per user, treat as successful
        error_log("MoMo payment failed for orderId: $orderId, but treating as successful");
    }
} else {
    error_log("Invalid signature for MoMo callback");
}

// Always respond with success
echo "success";
exit;