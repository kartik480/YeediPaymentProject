<?php
$webhookSecret='YOUR_WEBHOOK_SECRET';
$raw=(string)file_get_contents('php://input');
$given=strtolower(trim((string)($_SERVER['HTTP_X_YEEDIPAY_SIGNATURE']??'')));
$expected=hash_hmac('sha256',$raw,$webhookSecret);
if($given===''||!hash_equals($expected,$given)){
    http_response_code(401);exit('bad signature');
}
$data=json_decode($raw,true);
if(!is_array($data)){http_response_code(400);exit('invalid json');}
// IMPORTANT: Save event_id and ignore duplicates before fulfilling an order.
// if (($data['event'] ?? '') === 'payment.success') { mark order paid here; }
http_response_code(200);echo 'ok';
