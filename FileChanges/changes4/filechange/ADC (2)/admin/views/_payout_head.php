<?php
$title = $title ?? 'Payout';
$path = route_path();
$poOn = function (string $href, bool $exact = false) use ($path): string {
    if ($exact) return ($path === $href || $path === $href.'/') ? ' is-on' : '';
    return str_starts_with($path, $href) ? ' is-on' : '';
};
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?=e($title)?> — YEEDIPAY Payout</title>
    <link rel="stylesheet" href="<?=e(asset_url('app.css'))?>">
    <link rel="stylesheet" href="<?=e(asset_url('payout.css'))?>">
</head>
<body class="payout-shell">
<aside class="po-side">
    <div class="po-brand"><span>YP</span> YEEDIPAY</div>
    <div class="po-sec">OVERVIEW</div>
    <a class="po-link<?=$poOn('/admin/payout', true)?>" href="<?=e(url('/admin/payout'))?>"><span class="po-ico hm">HM</span> Home</a>
    <div class="po-sec">PLATFORM</div>
    <a class="po-link<?=$poOn('/admin/payout/tenants')?>" href="<?=e(url('/admin/payout/tenants'))?>"><span class="po-ico tn">TN</span> Tenant Companies</a>
    <a class="po-link<?=$poOn('/admin/payout/pricing')?>" href="<?=e(url('/admin/payout/pricing'))?>"><span class="po-ico pr">PR</span> Pricing Rules</a>
    <a class="po-link<?=$poOn('/admin/payout/connectors')?>" href="<?=e(url('/admin/payout/connectors'))?>"><span class="po-ico cn">CN</span> Connectors</a>
    <a class="po-link<?=$poOn('/admin/payout/bank-verification')?>" href="<?=e(url('/admin/payout/bank-verification'))?>"><span class="po-ico bv">BV</span> Bank Verification</a>
    <a class="po-link<?=$poOn('/admin/payout/kyb')?>" href="<?=e(url('/admin/payout/kyb'))?>"><span class="po-ico ky">KY</span> KYB Review</a>
    <a class="po-link<?=$poOn('/admin/payout/support')?>" href="<?=e(url('/admin/payout/support'))?>"><span class="po-ico sp">SP</span> Support</a>
    <div class="po-side-foot"><a href="<?=e(url('/admin'))?>">← YEEDIPAY Admin</a></div>
</aside>
<div class="po-main">
<?php foreach ($flashes ?? [] as $f): ?>
    <div class="po-flash <?=e($f['type'])?>"><?=e($f['message'])?></div>
<?php endforeach; ?>
