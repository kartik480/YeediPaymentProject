<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';
$errors=[];
$ok=function(bool $condition,string $message)use(&$errors): void {if(!$condition)$errors[]=$message;};
$timestamp='1760000000';$body='{"order_id":"ORD1","amount":"10.00"}';$secret='secret123';
$expected=hash_hmac('sha256',$timestamp."\nPOST\n/api/v1/payments\n".hash('sha256',$body),$secret);
$ok(MerchantApiService::canonicalSignature($timestamp,'POST','/api/v1/payments',$body,$secret)===$expected,'API signature mismatch');
$ok(WebhookService::sign($body,$secret)===hash_hmac('sha256',$body,$secret),'Webhook signature mismatch');
$ok(WebhookService::isAllowedWebhookUrl('https://merchant.example/webhook'),'Valid HTTPS webhook rejected');
foreach(['https://127.0.0.1/hook','https://10.0.0.5/hook','https://169.254.169.254/latest','https://[::1]/hook','https://localhost/hook'] as $url)$ok(!WebhookService::isAllowedWebhookUrl($url),'Unsafe webhook accepted: '.$url);
$ok(BankSmsService::QR_TTL_SECONDS===300,'Default QR TTL is not 300 seconds');
$ok(BankService::DEFAULT_ROTATION_MINUTES===30,'Default rotation is not 30 minutes');
try{MerchantApiService::assertIdempotentOrder(['amount'=>'850.00'],850.00);}catch(Throwable $e){$errors[]='Equal idempotent request was rejected';}
$rejected=false;try{MerchantApiService::assertIdempotentOrder(['amount'=>'850.00'],900.00);}catch(InvalidArgumentException $e){$rejected=true;}
$ok($rejected,'Different amount for same order was not rejected');
$ok(is_file(dirname(__DIR__).'/tools/preflight.php'),'Preflight tool missing');
$ok(is_file(dirname(__DIR__).'/tools/process_webhooks.php'),'Webhook worker missing');
if($errors){fwrite(STDERR,"FAIL\n - ".implode("\n - ",$errors)."\n");exit(1);}
echo "PASS final_v2_integration_test\n";
