<?php
declare(strict_types=1);

$config = require dirname(__DIR__) . '/config.php';
date_default_timezone_set($config['timezone'] ?? 'Asia/Kolkata');

header('Content-Type: application/json; charset=utf-8');

function respond(int $code, array $body): never {
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['success'=>false,'error'=>'POST required']);
}

$auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
$expected = 'Bearer ' . ($config['token'] ?? '');
if ($auth === '' || !hash_equals($expected, $auth)) {
    respond(401, ['success'=>false,'error'=>'Invalid demo token']);
}

$contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
$payload = [];

if (str_contains($contentType, 'application/json')) {
    $raw = file_get_contents('php://input') ?: '';
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        respond(400, ['success'=>false,'error'=>'Invalid JSON']);
    }
} else {
    $payload = $_POST;
}

$phone = trim((string)($payload['phone'] ?? ''));
$message = trim((string)($payload['message'] ?? ''));
$senderId = trim((string)($payload['sender_id'] ?? ''));
$templateId = trim((string)($payload['template_id'] ?? ''));

if ($phone === '' || $message === '') {
    respond(422, ['success'=>false,'error'=>'phone and message are required']);
}

$digits = preg_replace('/\D+/', '', $phone) ?? '';
if (strlen($digits) < 8 || strlen($digits) > 15) {
    respond(422, ['success'=>false,'error'=>'Invalid phone format']);
}

$record = [
    'message_id' => 'DEMO-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3))),
    'created_at' => date(DATE_ATOM),
    'phone' => $phone,
    'message' => $message,
    'sender_id' => $senderId,
    'template_id' => $templateId,
    'delivery_status' => 'DEMO_ACCEPTED',
    'delivered_to_mobile' => false,
];

$storageDir = dirname((string)$config['storage_file']);
if (!is_dir($storageDir) && !@mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
    respond(500, ['success'=>false,'error'=>'Cannot create demo SMS storage']);
}
$line = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
if (@file_put_contents($config['storage_file'], $line, FILE_APPEND | LOCK_EX) === false) {
    respond(500, ['success'=>false,'error'=>'Cannot write demo SMS log']);
}

respond(200, [
    'success' => true,
    'status' => 'ACCEPTED',
    'message_id' => $record['message_id'],
    'demo' => true,
    'delivered_to_mobile' => false,
    'note' => 'Demo gateway only: message logged on server, not sent to handset.'
]);
