<?php
$code = strtoupper((string)($c['code'] ?? ''));
$chip = isset($c) ? Portal::chip((string)$c['name']) : '';
?>
<div class="yp-tools">
    <?php if ($code !== ''): ?>
    <a class="yp-bell" href="<?=e(url('/c/'.$code.'/tickets'))?>" aria-label="<?=e(Portal::t('tickets'))?>"><?=yp_icon('bell')?></a>
    <details class="yp-user">
        <summary><?=e($chip)?></summary>
        <div class="yp-user-menu">
            <a href="<?=e(url('/c/'.$code.'/settings'))?>"><?=e(Portal::t('settings'))?></a>
            <a href="<?=e(url('/c/'.$code.'/logout'))?>"><?=e(Portal::t('logout'))?></a>
        </div>
    </details>
    <?php endif; ?>
</div>
