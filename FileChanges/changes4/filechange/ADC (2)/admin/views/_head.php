<?php
$title = $title ?? 'Admin';
$path = route_path();
$navOn = function (string $prefix) use ($path): string {
    if ($prefix === '/admin' && ($path === '/admin' || $path === '/admin/')) return ' nav-on';
    if ($prefix !== '/admin' && str_starts_with($path, $prefix)) return ' nav-on';
    return '';
};
$dark = str_starts_with($path, '/admin/transactions') || str_starts_with($path, '/admin/banks') || str_starts_with($path, '/admin/settlements');
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?=e($title)?> — YEEDIPAY</title>
    <link rel="stylesheet" href="<?=e(asset_url('app.css'))?>">
</head>
<body class="<?=$dark?'dark-admin':''?>">
<div class="top">
    <b>YEEDIPAY Admin</b>
    <?php if (Auth::adminOk()): ?>
    <nav class="nav-tabs">
        <a class="<?=trim($navOn('/admin'))?>" href="<?=e(url('/admin'))?>">Dashboard</a>
        <a class="<?=trim($navOn('/admin/banks'))?>" href="<?=e(url('/admin/banks'))?>">Banks &amp; Routing</a>
        <a class="<?=trim($navOn('/admin/customers'))?>" href="<?=e(url('/admin/customers'))?>">Customers</a>
        <a class="<?=trim($navOn('/admin/accounts'))?>" href="<?=e(url('/admin/accounts'))?>">Customer accounts</a>
        <a class="<?=trim($navOn('/admin/transactions'))?>" href="<?=e(url('/admin/transactions'))?>">Transactions</a>
        <a class="<?=trim($navOn('/admin/tickets'))?>" href="<?=e(url('/admin/tickets'))?>">Tickets</a>
        <a class="<?=trim($navOn('/admin/wallet'))?>" href="<?=e(url('/admin/wallet'))?>">Wallet</a>
        <a class="<?=trim($navOn('/admin/settlements'))?>" href="<?=e(url('/admin/settlements'))?>">Settlements</a>
        <a class="<?=trim($navOn('/admin/reconciliation'))?>" href="<?=e(url('/admin/reconciliation'))?>">Reconciliation</a>
        <a class="<?=trim($navOn('/admin/sms'))?>" href="<?=e(url('/admin/sms'))?>">SMS Gateway</a>
        <a class="<?=trim($navOn('/admin/bank-sms'))?>" href="<?=e(url('/admin/bank-sms'))?>">Bank SMS</a>
        <a class="<?=trim($navOn('/admin/audit'))?>" href="<?=e(url('/admin/audit'))?>">Audit</a>
        <a class="<?=trim($navOn('/admin/security'))?>" href="<?=e(url('/admin/security'))?>">Security</a>
        <a href="<?=e(pay_url('/'))?>" target="_blank" rel="noopener">Customer site</a>
        <a href="<?=e(url('/admin/logout'))?>">Logout</a>
        <a class="<?=trim($navOn('/admin/payout'))?>" href="<?=e(url('/admin/payout'))?>">Payout</a>
    </nav>
    <?php endif; ?>
</div>
<div class="wrap">
<?php foreach ($flashes ?? [] as $f): ?>
    <div class="<?=e($f['type'])?>"><?=e($f['message'])?></div>
<?php endforeach; ?>
