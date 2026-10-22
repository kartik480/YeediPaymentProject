<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/db.php';

$name = trim((string)($argv[1] ?? 'YEEDI CUSTOMER DASHBOARD'));
$clientId = 'YMC-' . strtoupper(bin2hex(random_bytes(4)));
$secret = bin2hex(random_bytes(32));
$q = yeedi_db()->prepare('INSERT INTO merchant_clients (client_id, client_name, api_secret, active) VALUES (?, ?, ?, 1)');
$q->execute([$clientId, $name, $secret]);
echo "CLIENT ID: {$clientId}\n";
echo "API SECRET: {$secret}\n";
echo "Use only on the merchant/server side. Never expose this secret in browser JavaScript.\n";
