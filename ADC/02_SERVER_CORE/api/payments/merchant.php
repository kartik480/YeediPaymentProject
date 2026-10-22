<?php
require_once dirname(__DIR__, 2) . '/payment_core.php';
apply_public_api_cors();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $clientId = trim((string)($_GET['client_id'] ?? ''));
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_body();
    $clientId = trim((string)($data['client_id'] ?? ''));
} else {
    json_response(['ok'=>false,'error'=>'method_not_allowed'], 405);
}

$result = get_merchant_info($clientId);
json_response($result, !empty($result['ok']) ? 200 : 404);
