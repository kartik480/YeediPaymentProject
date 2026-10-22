<?php
$base='https://pay.yeedipay.in';
$merchantId='MER0004';
$apiKey='YOUR_API_KEY';
$apiSecret='YOUR_API_SECRET';
$paymentId='YDP_REPLACE_ME';
$path='/api/v1/payments/'.$paymentId;
$timestamp=(string)time();
$canonical=$timestamp."\nGET\n".$path."\n".hash('sha256','');
$signature=hash_hmac('sha256',$canonical,$apiSecret);
$ch=curl_init($base.$path);
curl_setopt_array($ch,[CURLOPT_HTTPHEADER=>[
    'X-Merchant-Id: '.$merchantId,
    'X-Api-Key: '.$apiKey,
    'X-Timestamp: '.$timestamp,
    'X-Signature: '.$signature,
],CURLOPT_RETURNTRANSFER=>true]);
echo curl_exec($ch);curl_close($ch);
