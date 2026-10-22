<?php include __DIR__.'/_portal_head.php'; ?>
<section class="yp-hello">
    <h1><?=e($c['name'])?></h1>
    <p><?=e(Portal::t('customer_dashboard'))?></p>
    <strong><?=e(Portal::t('welcome'))?></strong>
</section>
<?php include __DIR__.'/_portal_cards.php'; ?>
<?php include __DIR__.'/_portal_foot.php'; ?>
