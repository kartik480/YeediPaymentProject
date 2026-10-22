<?php include __DIR__.'/_portal_head.php'; ?>
<section class="yp-hello">
    <h1><?=e(Portal::t('reports'))?></h1>
    <p>Collections for this account</p>
</section>
<?php include __DIR__.'/_portal_cards.php'; ?>
<p class="yp-report-links">
    <a class="yp-btn yp-btn-sm" href="<?=e(url('/c/'.$c['code'].'/statement'))?>"><?=e(Portal::t('statement'))?></a>
    <a class="yp-btn yp-btn-sm yp-btn-ghost" href="<?=e(url('/c/'.$c['code'].'/statement.csv'))?>"><?=e(Portal::t('export'))?></a>
</p>
<?php include __DIR__.'/_portal_foot.php'; ?>
