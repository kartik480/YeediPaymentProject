<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/db.php';

$label = trim($argv[1] ?? 'YEEDI BANK PHONE');
$deviceId = 'YEEDI-' . strtoupper(bin2hex(random_bytes(4)));
$secret = bin2hex(random_bytes(32));

$pdo = yeedi_db();
$stmt = $pdo->prepare(
    'INSERT INTO sms_relay_devices (device_id, device_label, api_secret, active)
     VALUES (?, ?, ?, 1)'
);
$stmt->execute([$deviceId, $label, $secret]);

echo "DEVICE ID: " . $deviceId . PHP_EOL;
echo "API SECRET: " . $secret . PHP_EOL;
echo "Enter both values in the Android app. Store the secret securely." . PHP_EOL;
