<?php
require_once dirname(__DIR__, 2) . '/payment_core.php';
apply_public_api_cors();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['ok'=>false,'error'=>'method_not_allowed'], 405);

$data = json_body();
$result = create_public_payment_order(
    (string)($data['client_id'] ?? ''),
    (string)($data['amount'] ?? ''),
    (string)($data['customer_name'] ?? ''),
    (string)($data['customer_id'] ?? ''),
    (string)($data['preferred_bank'] ?? '')
);

if (!empty($result['ok'])) json_response($result, 201);
$code = in_array($result['error'] ?? '', ['amount_slot_busy'], true) ? 409 : 422;
json_response($result, $code);
