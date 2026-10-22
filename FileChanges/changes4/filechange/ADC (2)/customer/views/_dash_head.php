<?php
$title = $title ?? 'Dashboard';
$nav = $nav ?? '';
$code = strtoupper((string)($c['code'] ?? ''));
$adminPanel = !empty($admin_panel);
$custBase = $custBase ?? ($code !== '' ? '/c/'.$code : '');
$accountCustomers = $account_customers ?? [];
$custNavOn = static fn(string $key): string => ($nav === $key) ? ' is-on' : '';
$css = $adminPanel ? url('/customer/public/assets/app.css') : asset_url('app.css');
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?=e($title)?> — YEEDIPAY</title>
    <link rel="stylesheet" href="<?=e($css)?>">
</head>
<body class="pay-dark pay-dash cust-shell">
<?php if ($adminPanel): ?>
<div class="acct-adminbar">
    <b>YEEDIPAY Admin</b>
    <a href="<?=e(url('/admin'))?>">Overview</a>
    <a href="<?=e(url('/admin/customers'))?>">Customers</a>
    <a href="<?=e(url('/admin/accounts'))?>">Customer accounts</a>
    <a href="<?=e(url('/admin/logout'))?>">Logout</a>
</div>
<?php endif; ?>
<div class="cust-layout">
    <aside class="cust-side">
        <div class="cust-brand">YEEDIPAY</div>
        <?php if ($adminPanel && $accountCustomers): ?>
        <form class="acct-pick" method="get" action="<?=e(url('/admin/accounts'))?>">
            <input type="hidden" name="go" value="<?=e($nav !== '' ? $nav : 'dashboard')?>">
            <label>Customer
                <select name="code" onchange="this.form.submit()">
                    <?php foreach ($accountCustomers as $row): ?>
                    <option value="<?=e($row['code'])?>" <?=strtoupper((string)$row['code']) === $code ? 'selected' : ''?>><?=e($row['code'].' — '.$row['name'])?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </form>
        <?php endif; ?>
        <nav class="cust-nav">
            <?php if ($adminPanel): ?>
            <a class="cust-nav-link<?=$custNavOn('dashboard')?>" href="<?=e(url($custBase))?>">Dashboard</a>
            <a class="cust-nav-link<?=$custNavOn('statement')?>" href="<?=e(url($custBase.'/statement'))?>">Account Statement</a>
            <a class="cust-nav-link<?=$custNavOn('billing')?>" href="<?=e(url($custBase.'/billing'))?>">Billing</a>
            <a class="cust-nav-link<?=$custNavOn('wallet')?>" href="<?=e(url($custBase.'/wallet'))?>">Wallet</a>
            <?php else: ?>
            <a class="cust-nav-link<?=$custNavOn('merchant_qr')?>" href="<?=e(url('/c/'.$code.'/qr'))?>">Merchant QR</a>
            <?php endif; ?>
        </nav>
    </aside>
    <main class="cust-main">
<?php foreach ($flashes ?? [] as $f): ?>
        <div class="<?=e($f['type'])?>"><?=e($f['message'])?></div>
<?php endforeach; ?>
