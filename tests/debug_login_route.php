<?php
declare(strict_types=1);

$_SERVER['REQUEST_URI'] = '/login';
$_SERVER['SCRIPT_NAME'] = '/router.php';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost:8080';

define('YEEDIPAY_UNIFIED', true);
define('YEEDIPAY_SURFACE', 'customer');

ob_start();
require dirname(__DIR__) . '/customer/public/index.php';
$out = ob_get_clean();

echo 'LEN=' . strlen($out) . "\n";
echo substr($out, 0, 300) . "\n";
