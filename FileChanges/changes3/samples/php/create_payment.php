<?php
// Server-side sample only. Do not put API_SECRET in browser JavaScript.
$base = 'https://pay.yeedipay.in';
$merchantId = 'MER0004';
$apiKey = 'YOUR_API_KEY';
$apiSecret = 'YOUR_API_SECRET';
$path = '/api/v1/payments';
$body = json_encode([
    'order_id' => 'ORD10025',
    'amount' => '850.00',
    'customer_name' => 'Ramesh',
    'mobile' => '9876543210',
    'return_url' => 'https://merchant.example/payment-result',
], JSON_UNESCAPED_SLASHES);
$timestamp = (string)time();
$canonical = $timestamp."\nPOST\n".$path."\n".hash('sha256',$body);
$signature = hash_hmac('sha256',$canonical,$apiSecret);
$ch = curl_init($base.$path);
curl_setopt_array($ch,[
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-Merchant-Id: '.$merchantId,
        'X-Api-Key: '.$apiKey,
        'X-Timestamp: '.$timestamp,
        'X-Signature: '.$signature,
    ],
    CURLOPT_RETURNTRANSFER => true,
]);
$response = curl_exec($ch);
$code = curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
curl_close($ch);
header('Content-Type: text/plain');
echo "HTTP {$code}\n{$response}\n";
