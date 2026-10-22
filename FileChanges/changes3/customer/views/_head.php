<?php $title=$title??'Payment'; $pay_ui=!empty($pay_ui); ?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?=e($title)?> — YEEDIPAY</title>
    <link rel="stylesheet" href="<?=e(asset_url('app.css'))?>">
</head>
<body class="<?=$pay_ui?'pay-dark':''?><?=!empty($dash_ui)?' pay-dash':''?>">
<?php if (!$pay_ui): ?>
<div class="top"><b><a href="<?=e(pay_url('/'))?>" style="color:#fff;text-decoration:none">YEEDIPAY</a></b> · <a href="<?=e(admin_url('/admin/login'))?>">Admin</a></div>
<?php endif; ?>
<div class="wrap">
<?php foreach ($flashes ?? [] as $f): ?>
    <div class="<?=e($f['type'])?>"><?=e($f['message'])?></div>
<?php endforeach; ?>
