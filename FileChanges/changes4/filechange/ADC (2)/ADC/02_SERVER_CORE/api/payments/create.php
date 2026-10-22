<?php
require_once dirname(__DIR__, 2) . '/payment_core.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['ok'=>false,'error'=>'method_not_allowed'], 405);
$raw = file_get_contents('php://input');
if ($raw === false || strlen($raw) < 2 || strlen($raw) > 16384) json_response(['ok'=>false,'error'=>'invalid_body'], 400);
$client = authenticate_merchant_request($raw);
$data = json_decode($raw, true);
if (!is_array($data)) json_response(['ok'=>false,'error'=>'invalid_json'], 400);

$result = create_payment_order(
    (int)$client['id'],
    (string)($data['merchant_order_id'] ?? ''),
    (string)($data['amount'] ?? ''),
    (string)($data['preferred_bank'] ?? '')
);

if (!empty($result['ok'])) json_response($result, 201);
$code = in_array($result['error'] ?? '', ['amount_slot_busy','merchant_order_exists'], true) ? 409 : 422;
json_response($result, $code);
