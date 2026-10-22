<?php
include __DIR__.'/_head.php';
$enabled = !empty($cfg['enabled']);
$orderUrl  = url('/c/'.$c['code'].'/razorpay/order');
$verifyUrl = url('/c/'.$c['code'].'/razorpay/verify');
$csrf = csrf_token();
?>
<div class="pay">
    <div class="row">
        <div class="pay-who grow"><?=e($c['name'])?><div><?=e($c['code'])?> · Card / UPI / Netbanking</div></div>
    </div>

    <?php if (!$enabled): ?>
        <div class="card"><p><b>Online card / netbanking payment is not available right now.</b> Please use the Merchant QR option, or contact support.</p></div>
    <?php else: ?>
        <?php if (empty($cfg['live_mode'])): ?>
        <div class="card" style="border-left:4px solid #b45309"><b>TEST MODE</b> — no real money is charged.</div>
        <?php endif; ?>
        <div class="card">
            <h2>Pay securely</h2>
            <p class="muted">Enter an amount and pay by UPI, card, or netbanking. Powered by Razorpay. We never see or store your card/UPI PIN.</p>
            <label for="rzpAmount">Amount (₹)</label>
            <input id="rzpAmount" inputmode="decimal" placeholder="e.g. 500"
                   style="font-size:1.4rem;padding:12px;width:100%;box-sizing:border-box">
            <p class="muted" style="margin-top:6px">Min ₹<?=e(rtrim(rtrim(number_format((float)$cfg['min'],2,'.',''),'0'),'.'))?> · Max ₹<?=e(rtrim(rtrim(number_format((float)$cfg['max'],2,'.',''),'0'),'.'))?></p>
            <p id="rzpError" style="color:#dc2626;display:none"></p>
            <p style="margin-top:14px"><button id="rzpPay" type="button" style="font-size:1.1rem;padding:12px 28px">Pay Now</button></p>
            <p class="muted" id="rzpBusy" style="display:none">Opening secure checkout…</p>
        </div>
    <?php endif; ?>

    <?php if (!empty($recent)): ?>
    <div class="card">
        <h2>Recent online payments</h2>
        <div style="overflow:auto">
        <table class="tbl">
            <thead><tr><th>Date</th><th>Amount</th><th>Status</th><th>Payment ID</th></tr></thead>
            <tbody>
            <?php foreach ($recent as $r): $st = strtoupper((string)$r['status']); ?>
                <tr>
                    <td><?=e((string)$r['created_at'])?></td>
                    <td>₹<?=e(number_format(((int)$r['amount_paise'])/100,2))?></td>
                    <td><b style="color:<?= $st==='PAID'?'#16a34a':($st==='FAILED'?'#b45309':($st==='REVIEW'?'#dc2626':'#555'))?>"><?=e($st)?></b></td>
                    <td class="mono"><?=e((string)($r['rzp_payment_id'] ?? ''))?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($enabled): ?>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
(function(){
  var CSRF = <?= json_encode($csrf) ?>;
  var ORDER_URL = <?= json_encode($orderUrl) ?>;
  var VERIFY_URL = <?= json_encode($verifyUrl) ?>;
  var payBtn = document.getElementById('rzpPay');
  var amountEl = document.getElementById('rzpAmount');
  var errEl = document.getElementById('rzpError');
  var busyEl = document.getElementById('rzpBusy');

  function showErr(msg){ errEl.textContent = msg; errEl.style.display = 'block'; }
  function clearErr(){ errEl.style.display = 'none'; }
  function busy(on){ busyEl.style.display = on ? 'block' : 'none'; payBtn.disabled = on; }

  payBtn.addEventListener('click', function(){
    clearErr();
    var amt = (amountEl.value || '').trim();
    if (!/^\d+(\.\d{1,2})?$/.test(amt)) { showErr('Enter a valid amount.'); return; }
    busy(true);
    var fd = new FormData();
    fd.append('_csrf', CSRF);
    fd.append('amount', amt);
    fetch(ORDER_URL, {method:'POST', body:fd, credentials:'same-origin'})
      .then(function(r){ return r.json(); })
      .then(function(d){
        if (!d.ok) { busy(false); showErr(d.error || 'Could not start payment.'); return; }
        openCheckout(d);
      })
      .catch(function(){ busy(false); showErr('Network error. Please try again.'); });
  });

  function openCheckout(d){
    var opts = {
      key: d.key_id,
      order_id: d.order_id,
      amount: d.amount,
      currency: d.currency,
      name: d.label || 'YEEDIPAY',
      description: 'Payment to ' + (d.name || 'YEEDIPAY'),
      prefill: { email: d.prefill_email || '', contact: d.prefill_contact || '' },
      notes: { yeedipay_reference: d.local_reference },
      handler: function(resp){
        // Hand the callback to the server; the server re-verifies with Razorpay.
        var fd = new FormData();
        fd.append('_csrf', CSRF);
        fd.append('razorpay_order_id', resp.razorpay_order_id || d.order_id);
        fd.append('razorpay_payment_id', resp.razorpay_payment_id || '');
        fd.append('razorpay_signature', resp.razorpay_signature || '');
        fetch(VERIFY_URL, {method:'POST', body:fd, credentials:'same-origin'})
          .then(function(r){ return r.json(); })
          .then(function(v){ window.location = v.result_url || d.result_url; })
          .catch(function(){ window.location = d.result_url; });
      },
      modal: {
        ondismiss: function(){
          busy(false);
          // Payment not completed in checkout; result page will show real status.
          window.location = d.result_url;
        }
      },
      theme: { color: '#2563eb' }
    };
    try {
      var rzp = new Razorpay(opts);
      rzp.on('payment.failed', function(){ /* result page reflects server truth */ });
      rzp.open();
      busy(false);
    } catch (e) {
      busy(false);
      showErr('Could not open checkout. Please try again.');
    }
  }
})();
</script>
<?php endif; ?>
<?php include __DIR__.'/_foot.php'; ?>
