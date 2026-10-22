<?php
require_once dirname(__DIR__, 2) . '/payment_core.php';
apply_public_api_cors();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_body();
    $paymentId = (string)($data['payment_id'] ?? '');
    $token = (string)($data['token'] ?? '');
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // GET retained for compatibility/testing. Production dashboard uses POST.
    $paymentId = (string)($_GET['payment_id'] ?? '');
    $token = (string)($_GET['token'] ?? '');
} else {
    json_response(['ok'=>false,'error'=>'method_not_allowed'], 405);
}

$result = get_public_payment_status($paymentId, $token);
json_response($result, !empty($result['ok']) ? 200 : 404);
