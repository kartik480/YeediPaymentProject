<?php
include __DIR__.'/_head.php';
$st = strtoupper((string)$order['status']);
$amt = number_format(((int)$order['amount_paise'])/100, 2);
$base = $base ?? ('/c/'.$c['code'].'/razorpay');
$statusUrl = url($base.'/status/'.$order['local_reference']);
$payUrl = url('/p/' . strtoupper((string)$c['code']));
$isPaid = $st === 'PAID';
$isFailed = in_array($st, ['FAILED','CANCELLED'], true);
$isReview = $st === 'REVIEW';
$isPending = in_array($st, ['CREATED','PENDING'], true);
?>
<div class="pay">
    <div class="row">
        <div class="pay-who grow"><?=e($c['name'])?><div><?=e($c['code'])?></div></div>
        <a class="pay-copy" href="<?=e($payUrl)?>" style="width:auto;padding:8px 14px;text-decoration:none">Pay again</a>
    </div>

    <div class="card" id="rzpResultBox">
        <?php if ($isPaid): ?>
            <div class="pay-ok">
                <div class="pay-ok-mark">✓</div>
                <h1>Payment Successful!</h1>
                <div class="pay-inr">Amount ₹<?=e($amt)?></div>
                <p>Reference: <span class="mono"><?=e((string)$order['local_reference'])?></span></p>
                <?php if (!empty($order['rzp_payment_id'])): ?><p>Payment ID: <span class="mono"><?=e((string)$order['rzp_payment_id'])?></span></p><?php endif; ?>
                <p class="paid-mark">PAID</p>
            </div>
        <?php elseif ($isReview): ?>
            <h1>Payment under review</h1>
            <p>Your payment of ₹<?=e($amt)?> is being verified by our team. Please <b>do not pay again</b>. Reference <span class="mono"><?=e((string)$order['local_reference'])?></span>.</p>
        <?php elseif ($isFailed): ?>
            <h1>Payment <?=e(strtolower($st))?></h1>
            <p>This payment did not complete. No amount has been captured. You can try again.</p>
            <p><a href="<?=e($payUrl)?>">Try again →</a></p>
        <?php else: ?>
            <h1 id="rzpHeading">Processing payment…</h1>
            <p id="rzpMsg">Please do not close this page. We are confirming your payment with the bank.</p>
            <p class="muted">Reference <span class="mono"><?=e((string)$order['local_reference'])?></span> · ₹<?=e($amt)?></p>
        <?php endif; ?>
    </div>
</div>

<?php if ($isPending): ?>
<script>
(function(){
  var URL = <?= json_encode($statusUrl) ?>;
  var tries = 0, max = 40; // ~2 min
  function poll(){
    tries++;
    fetch(URL, {credentials:'same-origin'})
      .then(function(r){ return r.json(); })
      .then(function(d){
        if (!d.ok) return schedule();
        var s = (d.status || '').toUpperCase();
        if (s === 'PAID' || s === 'FAILED' || s === 'CANCELLED' || s === 'REVIEW') {
          window.location.reload();
          return;
        }
        schedule();
      })
      .catch(schedule);
  }
  function schedule(){
    if (tries >= max) {
      var h = document.getElementById('rzpHeading');
      var m = document.getElementById('rzpMsg');
      if (h) h.textContent = 'Still confirming…';
      if (m) m.textContent = 'This is taking longer than usual. Your payment is safe; refresh this page in a moment to see the final status. Do not pay again.';
      return;
    }
    setTimeout(poll, 3000);
  }
  setTimeout(poll, 2500);
})();
</script>
<?php endif; ?>
<?php include __DIR__.'/_foot.php'; ?>
