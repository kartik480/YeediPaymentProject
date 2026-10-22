<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$errors=[];
$required=[
  'app/MerchantApiService.php',
  'app/WebhookService.php',
  'tools/create_merchant.php',
  'docs/MERCHANT_API_GUIDE.md',
  'docs/PATCH_INSTRUCTIONS_TELUGU.md',
  'docs/CUSTOMER_DELIVERY_KIT.md',
  'database/merchant_api_patch.sql',
];
foreach($required as $f){if(!is_file($root.'/'.$f))$errors[]='missing '.$f;}
$bootstrap=(string)@file_get_contents($root.'/app/bootstrap.php');
foreach(['MerchantApiService.php','WebhookService.php'] as $f){if(!str_contains($bootstrap,$f))$errors[]='bootstrap missing '.$f;}
$idx=(string)@file_get_contents($root.'/customer/public/index.php');
foreach(['/api/v1/payments','MerchantApiService::handleCreate','MerchantApiService::handleStatus'] as $needle){if(!str_contains($idx,$needle))$errors[]='route missing '.$needle;}
$schema=(string)@file_get_contents($root.'/database/schema.sql');
foreach(['merchant_accounts','merchant_orders','merchant_webhook_deliveries'] as $table){if(!str_contains($schema,$table))$errors[]='schema missing '.$table;}
$recon=(string)@file_get_contents($root.'/app/ReconciliationService.php');
if(!str_contains($recon,'MerchantApiService::onTransactionStatusChanged'))$errors[]='final status is not connected to merchant webhook queue';
if($errors){fwrite(STDERR,"FAIL\n - ".implode("\n - ",$errors)."\n");exit(1);} echo "PASS\n";
