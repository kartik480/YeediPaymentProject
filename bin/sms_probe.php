<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}
$root = dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
$db = Database::pdo();
SmsService::applySmslogin($db);
$s = SmsService::settings($db);
$phone = $argv[1] ?? '9398907446';
$msg = $argv[2] ?? 'Hello World';
echo 'enabled='.(int)$s['enabled'].' gateway='.$s['gateway_name'].' sender='.$s['sender_id'].PHP_EOL;
echo 'success_template_id='.(string)$s['success_template_id'].PHP_EOL;
echo 'phone='.SmsService::normalizeMobile($phone).PHP_EOL;
try {
    $r = SmsService::sendTest($db, $phone, $msg);
    echo 'http='.$r['status_code'].PHP_EOL;
    echo 'body='.substr((string)$r['body'], 0, 800).PHP_EOL;
} catch (Throwable $e) {
    echo 'error='.$e->getMessage().PHP_EOL;
}
