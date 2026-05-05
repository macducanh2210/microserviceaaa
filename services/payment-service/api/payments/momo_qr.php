<?php

declare(strict_types=1);

require_once __DIR__ . '/../../db.php';

$configPath = __DIR__ . '/../../config_momo.json';
if (!file_exists($configPath)) {
    jsonResponse(500, ['success' => false, 'message' => 'Missing MoMo config file', 'path' => $configPath]);
}

$config = json_decode(file_get_contents($configPath), true);
if (!is_array($config)) {
    jsonResponse(500, ['success' => false, 'message' => 'Invalid MoMo config file']);
}

function execPostRequest(string $url, string $data): string
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($data),
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    $result = curl_exec($ch);
    curl_close($ch);
    return (string)$result;
}

$endpoint = 'https://test-payment.momo.vn/v2/gateway/api/create';

$partnerCode = $config['partnerCode'] ?? '';
$accessKey = $config['accessKey'] ?? '';
$secretKey = $config['secretKey'] ?? '';

$orderInfo = 'Thanh toán đơn hàng qua MoMo QR';
$amount = $_REQUEST['amount'] ?? '10000';
$orderId = $_REQUEST['order_id'] ?? (string)time();
$redirectUrl = 'http://localhost:8080/api/payments/callback.php';
$ipnUrl = 'http://localhost:8080/api/payments/callback.php';
$extraData = '';

$requestId = (string)time();
$requestType = 'captureWallet';

$rawHash = sprintf(
    'accessKey=%s&amount=%s&extraData=%s&ipnUrl=%s&orderId=%s&orderInfo=%s&partnerCode=%s&redirectUrl=%s&requestId=%s&requestType=%s',
    $accessKey,
    $amount,
    $extraData,
    $ipnUrl,
    $orderId,
    $orderInfo,
    $partnerCode,
    $redirectUrl,
    $requestId,
    $requestType
);

$signature = hash_hmac('sha256', $rawHash, $secretKey);

$payload = [
    'partnerCode' => $partnerCode,
    'partnerName' => 'Test',
    'storeId' => 'MomoTestStore',
    'requestId' => $requestId,
    'amount' => $amount,
    'orderId' => $orderId,
    'orderInfo' => $orderInfo,
    'redirectUrl' => $redirectUrl,
    'ipnUrl' => $ipnUrl,
    'lang' => 'vi',
    'extraData' => $extraData,
    'requestType' => $requestType,
    'signature' => $signature,
];

$result = execPostRequest($endpoint, json_encode($payload));
$jsonResult = json_decode($result, true);

if (!is_array($jsonResult) || !isset($jsonResult['payUrl'])) {
    jsonResponse(500, ['success' => false, 'message' => 'MoMo API response invalid', 'raw' => $result]);
}

jsonResponse(200, ['success' => true, 'payUrl' => $jsonResult['payUrl']]);
exit;
