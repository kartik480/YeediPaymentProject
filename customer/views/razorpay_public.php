<?php
include __DIR__.'/_head.php';
$enabled = !empty($cfg['enabled']);
$code = strtoupper((string)$c['code']);
$orderUrl  = url('/p/'.$code.'/razorpay/order');
$verifyUrl = url('/p/'.$code.'/razorpay/verify');
$csrf = csrf_token();
?>
<div class="pay">
    <div class="row">
        <div class="pay-who grow"><?=e($c['name'])?><div>Make a Payment · Card / UPI / Netbanking</div></div>
    </div>

    <?php if (!$enabled): ?>
        <div class="card"><p><b>Online payment is not available right now.</b> Please contact <?=e($c['name'])?> or try again later.</p></div>
    <?php else: ?>
        <?php if (empty($cfg['live_mode'])): ?>
        <div class="card" style="border-left:4px solid #b45309"><b>TEST MODE</b> — no real money is charged.</div>
        <?php endif; ?>
        <div class="card">
            <h2>Make a Payment</h2>
            <p class="muted">Powered by Razorpay. We never see or store your card number, CVV, or UPI PIN.</p>

            <label for="pName">Customer Name</label>
            <input id="pName" type="text" maxlength="120" placeholder="Your name" autocomplete="name" style="padding:12px;width:100%;box-sizing:border-box">

            <label for="pMobile" style="margin-top:10px;display:block">Mobile Number</label>
            <input id="pMobile" type="tel" inputmode="numeric" maxlength="15" placeholder="10-digit mobile" autocomplete="tel" style="padding:12px;width:100%;box-sizing:border-box">

            <label for="pAmount" style="margin-top:10px;display:block">Enter Amount (₹)</label>
            <input id="pAmount" inputmode="decimal" placeholder="e.g. 1000" style="font-size:1.3rem;padding:12px;width:100%;box-sizing:border-box">

            <p class="muted" style="margin-top:6px">Min ₹<?=e(rtrim(rtrim(number_format((float)$cfg['min'],2,'.',''),'0'),'.'))?> · Max ₹<?=e(rtrim(rtrim(number_format((float)$cfg['max'],2,'.',''),'0'),'.'))?></p>
            <p id="pError" style="color:#dc2626;display:none"></p>
            <p style="margin-top:14px"><button id="pPay" type="button" style="font-size:1.1rem;padding:12px 28px;width:100%">PAY NOW</button></p>
            <p class="muted" id="pBusy" style="display:none">Opening secure checkout…</p>
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
  var nameEl = document.getElementById('pName');
  var mobileEl = document.getElementById('pMobile');
  var amountEl = document.getElementById('pAmount');
  var payBtn = document.getElementById('pPay');
  var errEl = document.getElementById('pError');
  var busyEl = document.getElementById('pBusy');

  function showErr(m){ errEl.textContent = m; errEl.style.display = 'block'; }
  function clearErr(){ errEl.style.display = 'none'; }
  function busy(on){ busyEl.style.display = on ? 'block' : 'none'; payBtn.disabled = on; }

  payBtn.addEventListener('click', function(){
    clearErr();
    var name = (nameEl.value || '').trim();
    var mobile = (mobileEl.value || '').trim();
    var amt = (amountEl.value || '').trim();
    if (name.length < 2) { showErr('Please enter your name.'); return; }
    if (!/^\d{10,15}$/.test(mobile.replace(/\D/g,''))) { showErr('Please enter a valid mobile number.'); return; }
    if (!/^\d+(\.\d{1,2})?$/.test(amt)) { showErr('Enter a valid amount.'); return; }
    busy(true);
    var fd = new FormData();
    fd.append('_csrf', CSRF);
    fd.append('name', name);
    fd.append('mobile', mobile.replace(/\D/g,''));
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
      prefill: { name: d.prefill_name || '', contact: d.prefill_contact || '' },
      notes: { yeedipay_reference: d.local_reference },
      handler: function(resp){
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
      modal: { ondismiss: function(){ busy(false); window.location = d.result_url; } },
      theme: { color: '#2563eb' }
    };
    try { var rzp = new Razorpay(opts); rzp.open(); busy(false); }
    catch (e) { busy(false); showErr('Could not open checkout. Please try again.'); }
  }
})();
</script>
<?php endif; ?>
<?php include __DIR__.'/_foot.php'; ?>
