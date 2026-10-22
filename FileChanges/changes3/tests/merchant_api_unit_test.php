<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';
$errors=[];
$ts='1760000000';$method='POST';$path='/api/v1/payments';$body='{"order_id":"ORD1","amount":"10.00"}';$secret='secret123';
$expected=hash_hmac('sha256',$ts."\nPOST\n".$path."\n".hash('sha256',$body),$secret);
if(MerchantApiService::canonicalSignature($ts,$method,$path,$body,$secret)!==$expected)$errors[]='canonical API signature mismatch';
$payload='{"event":"payment.success"}';
if(WebhookService::sign($payload,$secret)!==hash_hmac('sha256',$payload,$secret))$errors[]='webhook signature mismatch';
if(!WebhookService::isAllowedWebhookUrl('https://merchant.example/webhook'))$errors[]='https webhook rejected';
if(WebhookService::isAllowedWebhookUrl('ftp://merchant.example/webhook'))$errors[]='non-http webhook accepted';
if($errors){fwrite(STDERR,"FAIL\n - ".implode("\n - ",$errors)."\n");exit(1);}echo "PASS\n";
