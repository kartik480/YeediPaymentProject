<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';
$errors=[];
$ok=function(bool $v,string $m)use(&$errors){if(!$v)$errors[]=$m;};

$ok(BankSmsService::QR_TTL_SECONDS===300,'Payment QR window must be 5 minutes (300 seconds)');
$ok(!WebhookService::isAllowedWebhookUrl('https://127.0.0.1/hook'),'Webhook must reject loopback IPv4');
$ok(!WebhookService::isAllowedWebhookUrl('https://10.0.0.5/hook'),'Webhook must reject private IPv4');
$ok(!WebhookService::isAllowedWebhookUrl('https://169.254.169.254/latest'),'Webhook must reject link-local/metadata IPv4');
$ok(!WebhookService::isAllowedWebhookUrl('https://[::1]/hook'),'Webhook must reject loopback IPv6');
$ok(!WebhookService::isAllowedWebhookUrl('https://localhost/hook'),'Webhook must reject localhost');
$ok(WebhookService::isAllowedWebhookUrl('https://merchant.example/webhook'),'Webhook should accept normal HTTPS hostname');

$ok(method_exists(MerchantApiService::class,'assertIdempotentOrder'),'Merchant API idempotency consistency check missing');
if(method_exists(MerchantApiService::class,'assertIdempotentOrder')){
    try { MerchantApiService::assertIdempotentOrder(['amount'=>'850.00'],850.00); }
    catch(Throwable $e){ $errors[]='Same order_id + same amount should be idempotent'; }
    $thrown=false;
    try { MerchantApiService::assertIdempotentOrder(['amount'=>'850.00'],900.00); }
    catch(InvalidArgumentException $e){ $thrown=true; }
    catch(Throwable $e){ $errors[]='Amount mismatch must throw InvalidArgumentException'; $thrown=true; }
    $ok($thrown,'Same order_id + different amount must be rejected');
}

$runtime=(string)file_get_contents(dirname(__DIR__).'/app/Runtime.php');
$ok(!str_contains($runtime,"return 'b64:'.base64_encode(\$plain)"),'Crypto must not create insecure base64-only secret storage');
$bootstrap=(string)file_get_contents(dirname(__DIR__).'/app/bootstrap.php');
foreach(['session_set_cookie_params','httponly','samesite'] as $needle){$ok(str_contains(strtolower($bootstrap),strtolower($needle)),'Session hardening missing '.$needle);}
$ok(is_file(dirname(__DIR__).'/tools/preflight.php'),'Production preflight tool missing');

if($errors){fwrite(STDERR,"FAIL\n - ".implode("\n - ",$errors)."\n");exit(1);}echo "PASS final_hardening_test\n";
