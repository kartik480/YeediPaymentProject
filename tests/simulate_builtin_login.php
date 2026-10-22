<?php
declare(strict_types=1);

// Mimic php -S after router.php requires index.php (common SCRIPT_NAME values).
foreach (['/router.php', '/index.php', '/router.php/index.php'] as $scriptName) {
    $_SERVER = [
        'REQUEST_URI' => '/login',
        'SCRIPT_NAME' => $scriptName,
        'REQUEST_METHOD' => 'GET',
        'HTTP_HOST' => 'localhost:8080',
        'REMOTE_ADDR' => '127.0.0.1',
    ];
    if (!defined('YEEDIPAY_UNIFIED')) {
        define('YEEDIPAY_UNIFIED', true);
    }
    define('YEEDIPAY_SURFACE', 'customer');
    require dirname(__DIR__) . '/app/bootstrap.php';
    echo "SCRIPT_NAME=$scriptName route_path=" . route_path() . " method=" . request_method() . "\n";
}
