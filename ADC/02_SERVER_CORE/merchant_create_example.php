<?php
// CLI example: signs a Create Payment request exactly like your YEEDI backend should.
// Usage: php merchant_create_example.php https://YOUR-DOMAIN/.../02_SERVER_CORE CLIENT_ID API_SECRET ORDER-1001 500.00 [ICICI]
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$base = rtrim((string)($argv[1] ?? ''), '/');
$clientId = (string)($argv[2] ?? '');
$secret = (string)($argv[3] ?? '');
$orderId = (string)($argv[4] ?? '');
$amount = (string)($argv[5] ?? '');
$bank = (string)($argv[6] ?? '');
if ($base === '' || $clientId === '' || $secret === '' || $orderId === '' || $amount === '') {
    fwrite(STDERR, "Usage: php merchant_create_example.php BASE_URL CLIENT_ID API_SECRET ORDER_ID AMOUNT [BANK]\n"); exit(1);
}
$body = json_encode(['merchant_order_id'=>$orderId,'amount'=>$amount,'preferred_bank'=>$bank], JSON_UNESCAPED_SLASHES);
$ts = (string)time();
$sig = hash_hmac('sha256', $ts . "\n" . $body, $secret);
$headers = [
    'Content-Type: application/json',
    'X-Client-Id: ' . $clientId,
    'X-Timestamp: ' . $ts,
    'X-Signature: ' . $sig,
];
$ctx = stream_context_create(['http'=>[
    'method'=>'POST','header'=>implode("\r\n", $headers),'content'=>$body,'timeout'=>20,'ignore_errors'=>true
]]);
$response = @file_get_contents($base . '/api/payments/create.php', false, $ctx);
echo ($response === false ? "Request failed\n" : $response . "\n");
