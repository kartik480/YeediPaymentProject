<?php
$title = $title ?? 'Super Admin';
$path = route_path();
$navOn = static function (string $prefix) use ($path): string {
    if ($prefix === '/super-admin' && ($path === '/super-admin' || $path === '/super-admin/')) return ' sa-on';
    if ($prefix !== '/super-admin' && str_starts_with($path, $prefix)) return ' sa-on';
    return '';
};
$user = Auth::superAdminUsername();
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap">
    <title><?=e($title)?> — YEEDIPAY Super Admin</title>
    <link rel="stylesheet" href="<?=e(asset_url('brand.css'))?>">
    <link rel="stylesheet" href="<?=e(asset_url('super.css'))?>">
    <link rel="stylesheet" href="<?=e(asset_url('responsive.css'))?>">
</head>
<body class="sa-body">
<?php if (Auth::superAdminOk()): ?>
<div class="sa-top">
    <div class="sa-top-bar">
        <div class="sa-brand">YEEDIPAY <span>Super Admin</span></div>
        <button type="button" class="sa-menu-btn" aria-expanded="false" aria-controls="saNav">Menu</button>
    </div>
    <nav id="saNav" class="sa-nav">
        <a class="<?=trim($navOn('/super-admin'))?>" href="<?=e(url('/super-admin'))?>">Dashboard</a>
        <a class="<?=trim($navOn('/super-admin/transactions'))?>" href="<?=e(url('/super-admin/transactions'))?>">Transactions</a>
        <a class="<?=trim($navOn('/super-admin/customer-accounts'))?>" href="<?=e(url('/super-admin/customer-accounts'))?>">Customer Accounts</a>
        <a class="<?=trim($navOn('/super-admin/banks'))?>" href="<?=e(url('/super-admin/banks'))?>">Banks</a>
        <a class="<?=trim($navOn('/super-admin/admin-users'))?>" href="<?=e(url('/super-admin/admin-users'))?>">Admin Users</a>
        <a class="<?=trim($navOn('/super-admin/billing'))?>" href="<?=e(url('/super-admin/billing'))?>">Billing</a>
        <a class="<?=trim($navOn('/super-admin/gst'))?>" href="<?=e(url('/super-admin/gst'))?>">GST</a>
        <a class="<?=trim($navOn('/super-admin/reports'))?>" href="<?=e(url('/super-admin/reports'))?>">Reports</a>
        <a class="<?=trim($navOn('/super-admin/tickets'))?>" href="<?=e(url('/super-admin/tickets'))?>">Tickets</a>
        <a class="<?=trim($navOn('/super-admin/wallet'))?>" href="<?=e(url('/super-admin/wallet'))?>">Wallet</a>
    </nav>
    <div class="sa-user"><?=e(ucfirst($user))?> (SUPERADMIN) <a href="<?=e(url('/super-admin/logout'))?>">Logout</a></div>
</div>
<?php endif; ?>
<div class="sa-wrap">
<?php foreach ($flashes ?? [] as $f): ?>
    <div class="<?=e($f['type'] === 'ok' ? 'sa-ok' : 'sa-error')?>"><?=e($f['message'])?></div>
<?php endforeach; ?>
