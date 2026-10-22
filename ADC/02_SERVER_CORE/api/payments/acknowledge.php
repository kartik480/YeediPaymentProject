<?php
require_once dirname(__DIR__, 2) . '/payment_core.php';
apply_public_api_cors();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['ok'=>false,'error'=>'method_not_allowed'], 405);
$data = json_body();
$result = acknowledge_customer_paid((string)($data['payment_id'] ?? ''), (string)($data['token'] ?? ''));
json_response($result, !empty($result['ok']) ? 200 : 404);
