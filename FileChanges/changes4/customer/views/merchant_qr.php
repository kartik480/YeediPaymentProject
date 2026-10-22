<?php
include __DIR__.'/_portal_head.php';
$pay_qr = $pay_qr ?? null;
$qrText = $pay_qr ? TransactionService::scanUri($pay_qr) : (string)($upi ?? '');
$amountLabel = $pay_qr ? TransactionService::displayAmount($pay_qr) : '';
?>
<div class="yp-qr-wrap">
    <section class="yp-card yp-qr-card">
        <h1><?=e(Portal::t('scan'))?></h1>
        <?php if ($qrText !== ''): ?>
        <div class="yp-qrbox"><div id="qr" class="qrbox" data-logo="<?=e(brand_logo_url())?>"></div></div>
        <?php else: ?>
        <p class="yp-note">No active UPI QR is configured for this account.</p>
        <?php endif; ?>
        <h2><?=e($c['name'])?></h2>
        <?php if (!empty($vpa)): ?><p class="yp-upi">UPI ID: <?=e($vpa)?></p><?php endif; ?>
        <?php if ($pay_qr): ?>
        <p class="yp-note">Amount QR <?=e((string)$pay_qr['reference'])?><?php if ($amountLabel !== ''): ?> · ₹<?=e(Portal::money((float)str_replace(',', '', $amountLabel)))?><?php endif; ?></p>
        <p class="yp-forgot"><a href="<?=e(url('/c/'.$c['code'].'/qr'))?>">Show merchant QR</a></p>
        <?php endif; ?>
        <div class="yp-apps" aria-label="UPI apps">
            <span class="yp-app yp-gpay">GPay</span>
            <span class="yp-app yp-phonepe">PhonePe</span>
            <span class="yp-app yp-paytm">Paytm</span>
            <span class="yp-app yp-bhim">BHIM</span>
        </div>
    </section>

    <section class="yp-card yp-create-card">
        <h2><?=e(Portal::t('create_qr'))?></h2>
        <?php if (!empty($error)): ?><div class="yp-error"><?=e($error)?></div><?php endif; ?>
        <form method="post" action="<?=e(url('/c/'.$c['code'].'/start'))?>">
            <?=csrf_field()?>
            <label class="yp-field">
                <span class="yp-sr">Amount</span>
                <input name="amount" id="qrAmount" inputmode="decimal" value="<?=e((string)($presets[0] ?? 100))?>" required>
            </label>
            <div class="yp-chips">
                <?php foreach ($presets as $amt): ?>
                <button type="button" class="yp-chip" data-amt="<?=e((string)$amt)?>">₹<?=e(number_format((float)$amt, 0, '.', ','))?></button>
                <?php endforeach; ?>
            </div>
            <button class="yp-btn" type="submit"><?=e(Portal::t('create_qr'))?></button>
        </form>
    </section>
</div>
<?php if ($qrText !== ''): ?>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script src="<?=e(asset_url('qr-brand.js'))?>"></script>
<script>
(function () {
  var box = document.getElementById('qr');
  if (box && window.yeediQr) yeediQr(box, <?=json_encode($qrText)?>, 220);
})();
</script>
<?php endif; ?>
<script>
document.querySelectorAll('.yp-chip').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var input = document.getElementById('qrAmount');
    if (input) input.value = btn.getAttribute('data-amt') || '';
    document.querySelectorAll('.yp-chip').forEach(function (el) { el.classList.remove('is-on'); });
    btn.classList.add('is-on');
  });
});
</script>
<?php include __DIR__.'/_portal_foot.php'; ?>
