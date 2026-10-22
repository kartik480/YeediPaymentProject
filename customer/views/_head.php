<?php $title=$title??'Payment'; $pay_ui=!empty($pay_ui); ?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?=e($title)?> — YEEDIPAY</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap">
    <link rel="icon" type="image/png" href="<?=e(asset_url('favicon.png'))?>">
    <link rel="stylesheet" href="<?=e(asset_url('app.css'))?>">
    <link rel="stylesheet" href="<?=e(asset_url('brand.css'))?>">
    <link rel="stylesheet" href="<?=e(asset_url('responsive.css'))?>">
</head>
<body class="<?=$pay_ui?'pay-dark':''?><?=!empty($dash_ui)?' pay-dash':''?>">
<?php if (!$pay_ui): ?>
<div class="top"><a href="<?=e(pay_url('/'))?>"><img src="<?=e(brand_logo_url())?>" alt="YEEDI" style="height:36px;width:auto;background:#fff;border-radius:8px;vertical-align:middle"></a> · <a href="<?=e(admin_url('/admin'))?>">Admin</a></div>
<?php endif; ?>
<div class="wrap">
<?php if ($pay_ui): ?>
<p class="brand-lockup"><img src="<?=e(brand_logo_url())?>" alt="YEEDI Payment Solution Limited"></p>
<?php endif; ?>
<?php foreach ($flashes ?? [] as $f): ?>
    <div class="<?=e($f['type'])?>"><?=e($f['message'])?></div>
<?php endforeach; ?>
