<?php
$cards = $cards ?? [];
$labels = ['total' => Portal::t('total'), 'success' => Portal::t('success'), 'pending' => Portal::t('pending'), 'failed' => Portal::t('failed')];
?>
<div class="yp-stats">
<?php foreach ($cards as $card):
    $key = (string)$card['key'];
    $pct = (float)$card['pct'];
    $up = $pct >= 0;
?>
    <article class="yp-stat yp-stat-<?=e($key)?>">
        <div class="yp-stat-ico" aria-hidden="true">
            <?php if ($key === 'success'): ?>
                <svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
            <?php elseif ($key === 'pending'): ?>
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><path d="M12 8v5l3 2"/></svg>
            <?php elseif ($key === 'failed'): ?>
                <svg viewBox="0 0 24 24"><path d="M7 7l10 10M17 7L7 17"/></svg>
            <?php else: ?>
                <span class="yp-rupee">₹</span>
            <?php endif; ?>
        </div>
        <div class="yp-stat-copy">
            <div class="yp-stat-label"><?=e($labels[$key] ?? $key)?></div>
            <div class="yp-stat-amt">₹<?=e(Portal::money((float)$card['amount'], 0))?></div>
            <div class="yp-stat-today">
                <span><?=e(Portal::t('today'))?>: ₹<?=e(Portal::money((float)$card['today'], 0))?></span>
                <b class="<?=$up ? 'is-up' : 'is-down'?>"><?=$up ? '+' : ''?><?=e((string)(int)$pct)?>%</b>
            </div>
        </div>
    </article>
<?php endforeach; ?>
</div>
