<?php
declare(strict_types=1);

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (preg_match('#^/storage(?:/|$)#i', $uri)) {
    require __DIR__ . '/index.php';
    return true;
}
$file = __DIR__ . $uri;
if ($uri !== '/' && is_file($file)) {
    return false;
}
if ($uri !== '/' && is_dir($file)) {
    $index = rtrim($file, '/\\').DIRECTORY_SEPARATOR.'index.html';
    if (is_file($index)) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($index);
        return true;
    }
}

require __DIR__ . '/index.php';
