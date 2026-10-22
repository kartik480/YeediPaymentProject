<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$errors=[];
$requiredViews=['dashboard.php','merchant_qr.php','permanent_qr.php','statement.php','billing.php','tickets.php','ticket_view.php','wallet.php','wallet_recharge.php','wallet_recharge_pay.php','wallet_withdraw.php','login.php','checkout.php'];
foreach($requiredViews as $f){if(!is_file($root.'/customer/views/'.$f))$errors[]='missing view '.$f;}
$requiredServices=['BillingService.php','OtpService.php','StatementService.php','TicketService.php','WalletService.php'];
foreach($requiredServices as $f){if(!is_file($root.'/app/'.$f))$errors[]='missing service '.$f;}
$idx=(string)@file_get_contents($root.'/customer/public/index.php');
if(str_contains($idx,"/(statement|billing|tickets|wallet)(\\.|/|$)"))$errors[]='customer features are still redirected to QR';
if(str_contains($idx,"&&\$method==='NEVER'"))$errors[]='dashboard route is disabled';
$rootIdx=(string)@file_get_contents($root.'/index.php');
if(str_contains($rootIdx,"require __DIR__ . '/admin/public/index.php'"))$errors[]='customer-only package still hard-requires missing admin';
if(str_contains($rootIdx,"require __DIR__ . '/super-admin/public/index.php'"))$errors[]='customer-only package still hard-requires missing super-admin';
$foot=(string)@file_get_contents($root.'/customer/views/_foot.php');
if(trim($foot)==='')$errors[]='_foot.php is empty';
$schema=(string)@file_get_contents($root.'/database/schema.sql');
if(str_contains($schema,'CREATE DATABASE IF NOT EXISTS yeedipay_cust0003'))$errors[]='schema hardcodes a DB name that can mismatch BigRock';
if($errors){fwrite(STDERR,"FAIL\n - ".implode("\n - ",$errors)."\n");exit(1);}echo "PASS\n";
