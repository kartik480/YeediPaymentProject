<?php include __DIR__.'/_portal_head.php'; ?>
<section class="yp-hello">
    <h1><?=e(Portal::t('settings'))?></h1>
    <p><?=e($c['code'])?></p>
</section>
<section class="yp-sheet yp-settings">
    <?php if (!empty($error)): ?><div class="yp-error"><?=e($error)?></div><?php endif; ?>
    <dl class="yp-facts">
        <div><dt>Name</dt><dd><?=e($c['name'])?></dd></div>
        <div><dt>Mobile</dt><dd><?=e($c['phone'])?></dd></div>
        <div><dt>Account</dt><dd><?=e(trim((string)($c['account_number'] ?? '')) !== '' ? $c['account_number'] : '—')?></dd></div>
        <div><dt>GSTIN</dt><dd><?=e(trim((string)($c['gst'] ?? '')) !== '' ? $c['gst'] : '—')?></dd></div>
    </dl>
    <form method="post" action="<?=e(url('/c/'.$c['code'].'/settings'))?>" class="yp-ticket-form">
        <?=csrf_field()?>
        <label>Email
            <input type="email" name="email" value="<?=e((string)($c['email'] ?? ''))?>" placeholder="name@email.com">
        </label>
        <div class="yp-form-actions">
            <button class="yp-btn yp-btn-sm" type="submit">Save</button>
        </div>
    </form>
</section>
<?php include __DIR__.'/_portal_foot.php'; ?>
