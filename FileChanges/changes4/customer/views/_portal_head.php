<?php
$portalLayout = $portalLayout ?? 'sidebar';
$nav = $nav ?? '';
$code = strtoupper((string)($c['code'] ?? ''));
$lang = Portal::lang();
$chip = isset($c) ? Portal::chip((string)$c['name']) : '';
if (!function_exists('yp_icon')) {
    function yp_icon(string $name): string {
        $paths = [
            'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
            'card' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
            'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="9.5" cy="7" r="3"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a3 3 0 0 1 0 5.75"/>',
            'qr' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM18 18h3v3h-3zM14 18h2"/>',
            'out' => '<path d="M12 19V5"/><path d="M6 11l6-6 6 6"/><path d="M5 21h14"/>',
            'chart' => '<path d="M4 19V5"/><path d="M4 19h16"/><path d="M8 16v-5"/><path d="M12 16V8"/><path d="M16 16v-3"/>',
            'gear' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9c.3.7.9 1.2 1.6 1.3H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
            'life' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/><path d="M12 3v2M12 19v2M3 12h2M19 12h2"/>',
            'bell' => '<path d="M6 8a6 6 0 1 1 12 0c0 7 3 7 3 9H3c0-2 3-2 3-9"/><path d="M10 20a2 2 0 0 0 4 0"/>',
            'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
        ];
        $d = $paths[$name] ?? '';
        return '<svg class="yp-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$d.'</svg>';
    }
}
$items = $code === '' ? [] : [
    'dashboard' => [Portal::t('dashboard'), '/c/'.$code, 'grid'],
    'payments' => [Portal::t('payments'), '/c/'.$code.'/statement', 'card'],
    'customers' => [Portal::t('customers'), '/c/'.$code.'/customers', 'users'],
    'merchants' => [Portal::t('merchants'), '/c/'.$code.'/qr', 'qr'],
    'withdrawals' => [Portal::t('withdrawals'), '/c/'.$code.'/withdrawals', 'out'],
    'reports' => [Portal::t('reports'), '/c/'.$code.'/reports', 'chart'],
    'settings' => [Portal::t('settings'), '/c/'.$code.'/settings', 'gear'],
    'support' => [Portal::t('support'), '/c/'.$code.'/tickets', 'life'],
];
?>
<!doctype html>
<html lang="<?=e($lang === 'te' ? 'te' : 'en')?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?=e($title ?? 'YEEDIPAY')?> — YEEDIPAY</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap">
    <link rel="icon" type="image/png" href="<?=e(asset_url('favicon.png'))?>">
    <link rel="stylesheet" href="<?=e(asset_url('portal.css'))?>">
    <?php if (!empty($pay_ui)): ?><link rel="stylesheet" href="<?=e(asset_url('app.css'))?>"><?php endif; ?>
    <link rel="stylesheet" href="<?=e(asset_url('brand.css'))?>">
</head>
<body class="yp yp-<?=e($portalLayout)?><?=!empty($print) ? ' yp-printing' : ''?>">
<?php if ($portalLayout === 'sidebar'): ?>
<div class="yp-app">
    <aside class="yp-side">
        <a class="yp-brand" href="<?=e(url('/c/'.$code))?>"><img src="<?=e(brand_logo_url())?>" alt="YEEDI Payment Solution Limited"></a>
        <nav class="yp-nav">
            <?php foreach ($items as $key => [$label, $href, $icon]): ?>
            <a class="<?=$nav === $key ? 'is-on' : ''?>" href="<?=e(url($href))?>"><?=yp_icon($icon)?><span><?=e($label)?></span></a>
            <?php endforeach; ?>
        </nav>
        <a class="yp-logout" href="<?=e(url('/c/'.$code.'/logout'))?>"><?=yp_icon('logout')?><span><?=e(Portal::t('logout'))?></span></a>
    </aside>
    <div class="yp-main">
        <header class="yp-top">
            <div class="yp-top-spacer"></div>
            <?php include __DIR__.'/_portal_tools.php'; ?>
        </header>
        <div class="yp-body">
<?php elseif ($portalLayout === 'top'): ?>
<header class="yp-top yp-top-full">
    <a class="yp-brand yp-brand-light" href="<?=e($code !== '' ? url('/c/'.$code) : url('/login'))?>"><img src="<?=e(brand_logo_url())?>" alt="YEEDI Payment Solution Limited"></a>
    <?php if ($code !== ''): ?>
    <nav class="yp-toplinks">
        <a href="<?=e(url('/c/'.$code))?>"><?=e(Portal::t('dashboard'))?></a>
        <a href="<?=e(url('/c/'.$code.'/statement'))?>"><?=e(Portal::t('statement'))?></a>
        <a href="<?=e(url('/c/'.$code.'/qr'))?>"><?=e(Portal::t('merchants'))?></a>
        <a href="<?=e(url('/c/'.$code.'/tickets'))?>"><?=e(Portal::t('tickets'))?></a>
    </nav>
    <?php endif; ?>
    <?php include __DIR__.'/_portal_tools.php'; ?>
</header>
<div class="yp-body yp-body-top">
<?php else: ?>
<div class="yp-body yp-body-bare">
<?php endif; ?>
<?php foreach ($flashes ?? [] as $f): ?>
    <div class="yp-flash"><?=e($f['message'])?></div>
<?php endforeach; ?>
