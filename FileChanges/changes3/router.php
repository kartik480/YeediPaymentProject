<?php
declare(strict_types=1);

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$local = __DIR__.str_replace('/', DIRECTORY_SEPARATOR, $uri);
if ($uri !== '/' && is_file($local)) {
    return false;
}
require __DIR__.'/index.php';
