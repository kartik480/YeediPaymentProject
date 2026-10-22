<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}
$root = dirname(__DIR__);
require_once $root.'/app/Env.php';
Env::load($root.'/.env');
require_once $root.'/app/Database.php';
require_once $root.'/app/Helpers.php';
require_once $root.'/app/Crypto.php';
require_once $root.'/app/SmsService.php';
if (!defined('YEEDIPAY_ROOT')) define('YEEDIPAY_ROOT', $root);
$db = Database::pdo();
SmsService::applySmslogin($db);
$s = SmsService::settings($db);
echo 'gateway='.$s['gateway_name'].PHP_EOL;
echo 'method='.$s['http_method'].' '.$s['content_type'].PHP_EOL;
echo 'enabled='.$s['enabled'].PHP_EOL;
echo 'sender='.$s['sender_id'].PHP_EOL;
echo "ok\n";
