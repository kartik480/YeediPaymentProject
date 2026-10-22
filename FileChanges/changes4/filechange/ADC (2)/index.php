<?php
declare(strict_types=1);

/**
 * One document root for live hosts:
 *   superadmin.yeedipay.in → Super Admin
 *   admin.yeedipay.in      → Admin
 *   customer.yeedipay.in / pay.yeedipay.in → Customer / payment
 * Localhost still uses /admin, /super-admin, /p/ on a single port.
 */
define('YEEDIPAY_UNIFIED', true);

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$file = strtolower(basename($script));
$base = '';
if (in_array($file, ['index.php', 'router.php'], true)) {
    $base = rtrim(str_replace('\\', '/', dirname($script)), '/');
    if ($base === '.' || $base === '/') $base = '';
}
if ($base !== '' && str_starts_with($uri, $base)) {
    $uri = substr($uri, strlen($base)) ?: '/';
}
$uri = rtrim($uri, '/') ?: '/';

if (preg_match('#^/video/([^/]+\.mp4)$#i', $uri, $videoMatch)) {
    $videoName = basename(rawurldecode($videoMatch[1]));
    $videoFile = __DIR__ . '/video/' . $videoName;
    if (!is_file($videoFile)) {
        http_response_code(404);
        exit('Video not found');
    }
    $size = filesize($videoFile);
    $start = 0;
    $end = $size > 0 ? $size - 1 : 0;
    header('Content-Type: video/mp4');
    header('Accept-Ranges: bytes');
    header('Cache-Control: public, max-age=86400');
    header('X-Content-Type-Options: nosniff');
    $range = (string)($_SERVER['HTTP_RANGE'] ?? '');
    if ($range !== '' && preg_match('/bytes=(\d*)-(\d*)/', $range, $rangeMatch)) {
        if ($rangeMatch[1] !== '') $start = (int)$rangeMatch[1];
        if ($rangeMatch[2] !== '') $end = (int)$rangeMatch[2];
        if ($end >= $size) $end = $size - 1;
        if ($start > $end || $start >= $size) {
            header('Content-Range: bytes */'.$size);
            http_response_code(416);
            exit;
        }
        http_response_code(206);
        header('Content-Range: bytes '.$start.'-'.$end.'/'.$size);
        header('Content-Length: '.($end - $start + 1));
        $handle = fopen($videoFile, 'rb');
        if ($handle === false) {
            http_response_code(404);
            exit('Video not found');
        }
        fseek($handle, $start);
        $left = $end - $start + 1;
        while ($left > 0 && !feof($handle)) {
            $chunk = fread($handle, (int)min(8192, $left));
            if ($chunk === false || $chunk === '') break;
            echo $chunk;
            $left -= strlen($chunk);
        }
        fclose($handle);
        exit;
    }
    header('Content-Length: '.(string)$size);
    readfile($videoFile);
    exit;
}

if (preg_match('#^/image/yeedi\.png$#i', $uri)) {
    $png = __DIR__ . '/image/yeedi.png';
    if (is_file($png)) {
        header('Content-Type: image/png');
        header('Content-Length: '.(string)filesize($png));
        header('Cache-Control: public, max-age=86400');
        header('X-Content-Type-Options: nosniff');
        readfile($png);
        exit;
    }
}

$host = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')) ?? '');
$isSmsHost = str_contains($host, 'sms-demo.');
if ($isSmsHost || preg_match('#^/sms(?:/|$)#', $uri)) {
    $rel = $isSmsHost ? $uri : (preg_replace('#^/sms#', '', $uri) ?: '/');
    if ($rel === '/' || $rel === '') $rel = '/index.php';
    $file = __DIR__ . '/sms/public' . $rel;
    if (is_file($file)) {
        require $file;
        exit;
    }
    require __DIR__ . '/sms/public/index.php';
    exit;
}
if ($uri === '/api/bank-sms' || $uri === '/api/bank-relay/webhook_bank_sms.php') {
    define('YEEDIPAY_SURFACE', 'customer');
    require __DIR__ . '/customer/public/index.php';
    exit;
}
if (preg_match('#^/api/banks/#', $uri)) {
    define('YEEDIPAY_SURFACE', 'admin');
    require __DIR__ . '/admin/public/index.php';
    exit;
}

// IMPORTANT: check superadmin BEFORE admin — "superadmin." contains "admin."
$isSuperAdminHost = str_starts_with($host, 'superadmin.') || $host === 'superadmin.yeedipay.in';
$isAdminHost = !$isSuperAdminHost && (str_starts_with($host, 'admin.') || $host === 'admin.yeedipay.in');
$isCustomerHost = str_starts_with($host, 'customer.') || str_starts_with($host, 'pay.')
    || $host === 'customer.yeedipay.in' || $host === 'pay.yeedipay.in';

if ($isSuperAdminHost) {
    if ($uri === '/' || $uri === '/login' || preg_match('#^/admin(?:/|$)#', $uri)) {
        header('Location: /super-admin/login', true, 302);
        exit;
    }
    if (!preg_match('#^/super-admin(?:/|$)#', $uri)) {
        header('Location: /super-admin/login', true, 302);
        exit;
    }
    define('YEEDIPAY_SURFACE', 'super-admin');
    require __DIR__ . '/super-admin/public/index.php';
    exit;
}

if ($isAdminHost) {
    if (preg_match('#^/(p|c|pay)(/|$)#i', $uri)) {
        define('YEEDIPAY_SURFACE', 'customer');
        require __DIR__ . '/customer/public/index.php';
        exit;
    }
    if ($uri === '/') {
        header('Location: /admin/login', true, 302);
        exit;
    }
    define('YEEDIPAY_SURFACE', 'admin');
    require __DIR__ . '/admin/public/index.php';
    exit;
}

if ($isCustomerHost) {
    if (preg_match('#^/admin(?:/|$)#', $uri)) {
        header('Location: https://admin.yeedipay.in' . ($uri === '/' ? '/admin/login' : $uri), true, 302);
        exit;
    }
    if (preg_match('#^/super-admin(?:/|$)#', $uri)) {
        header('Location: https://superadmin.yeedipay.in' . ($uri === '/' ? '/super-admin/login' : $uri), true, 302);
        exit;
    }
    define('YEEDIPAY_SURFACE', 'customer');
    require __DIR__ . '/customer/public/index.php';
    exit;
}

if (preg_match('#^/admin(?:/|$)#', $uri)) {
    define('YEEDIPAY_SURFACE', 'admin');
    require __DIR__ . '/admin/public/index.php';
    exit;
}

if (preg_match('#^/super-admin(?:/|$)#', $uri)) {
    define('YEEDIPAY_SURFACE', 'super-admin');
    require __DIR__ . '/super-admin/public/index.php';
    exit;
}

define('YEEDIPAY_SURFACE', 'customer');
require __DIR__ . '/customer/public/index.php';
