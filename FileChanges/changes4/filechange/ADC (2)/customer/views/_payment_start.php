<?php
$presets = $presets ?? TransactionService::PRESET_AMOUNTS;
$max = 200000.0;
$hide_pay_extras = !empty($hide_pay_extras);
if (!$hide_pay_extras && !empty($c['per_txn_limit']) && (float)$c['per_txn_limit'] > 0) {
    $max = min($max, (float)$c['per_txn_limit']);
}
$selected = 100;
if (!empty($pay_qr['requested_amount'])) {
    $selected = (float)$pay_qr['requested_amount'];
}
$selectedValue = !empty($pay_qr) ? number_format((float)$selected, 2, '.', '') : (string)(int)$selected;
$amountMode = 'manual';
foreach ($presets as $presetAmt) {
    if (abs((float)$presetAmt - (float)$selected) < 0.001) {
        $amountMode = 'preset';
        break;
    }
}
$maxLabel = $max >= 200000 ? '₹2,00,000.00' : '₹'.amount_fmt($max);
$chipLabel = static function (int $a): string {
    if ($a >= 100000) {
        return '₹1,00,000';
    }
    if ($a >= 1000) {
        return '₹'.number_format($a, 0, '.', '');
    }
    return '₹'.$a;
};
$bank = $bank ?? null;
$pending = $pending ?? null;
$form_action = $form_action ?? url('/p/'.$c['code'].'/start');
$show_top_dashboard = !empty($show_top_dashboard);
$footer_link = $footer_link ?? null;
?>
<div class="pay<?=!empty($pay_shell)?' cust-pay-start':''?>">
    <div class="row">
        <div class="pay-who grow"><?=e($c['name'])?><div><?=e($c['code'])?><?php $acc = trim((string)($c['account_number'] ?? '')); if ($acc !== ''): ?> · A/c <?=e($acc)?><?php endif; ?></div></div>
        <?php if ($show_top_dashboard): ?>
        <a class="pay-copy" href="<?=e(url('/c/'.$c['code']))?>" style="width:auto;padding:8px 14px;text-decoration:none">Dashboard</a>
        <?php endif; ?>
    </div>
    <?php if (!$hide_pay_extras): ?>
    <div class="pay-upi">
        <div class="pay-upi-mark">UPI</div>
        <div>YEEDI Payments</div>
        <div class="pay-instant">INSTANT</div>
    </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?><div class="error"><?=e($error)?></div><?php endif; ?>
    <form method="post" action="<?=e($form_action)?>" id="payForm">
        <?=csrf_field()?>
        <input type="hidden" name="amount_mode" id="amtMode" value="<?=e($amountMode)?>">
        <h1 class="pay-title" style="font-size:1.35rem;margin:8px 0 4px">Make Payment</h1>
        <label class="pay-lab">Enter Amount (₹)
            <input name="amount" id="amtVal" inputmode="decimal" required value="<?=e($selectedValue)?>" placeholder="500" autocomplete="off">
        </label>
        <div class="pay-inr">INR <span id="amtShow"><?=number_format((float)$selected, 2, '.', '')?></span></div>
        <?php if (!$hide_pay_extras): ?>
        <p class="pay-lim">Min/Max Limit: ₹1 / <?=e($maxLabel)?></p>
        <?php endif; ?>
        <div class="pay-chips" id="payChips">
            <?php foreach ($presets as $a): $over = !$hide_pay_extras && $a > $max; $on = abs((float)$a - (float)$selected) < 0.001; ?>
                <button type="button" class="pay-chip<?=$on ? ' on' : ''?>" data-amt="<?=e((string)$a)?>" <?=$over ? 'disabled' : ''?>><?=e($chipLabel($a))?></button>
            <?php endforeach; ?>
        </div>
        <?php if (!$hide_pay_extras): ?>
        <label class="pay-lab">Invoice / Reference (optional)
            <input name="invoice_ref" maxlength="160" placeholder="Invoice number / reference" autocomplete="off">
        </label>
        <?php endif; ?>
        <button type="submit" class="pay-go">Generate QR</button>
        <?php if (!empty($pay_qr)): ?>
        <?php $t = $pay_qr; $qrSize = 260; include __DIR__.'/_pay_qr.php'; ?>
        <?php $paidAck = !empty($t['customer_paid_ack_at']); $verify = TransactionService::verificationState($t); ?>
        <p class="muted" id="paidMsg" style="text-align:center;margin-top:12px;color:#ff3b3b;font-weight:700"><?=e($verify['message'])?></p>
        <?php if (!$paidAck && strtoupper((string)($t['status'] ?? '')) === 'PENDING'): ?>
        <button type="button" class="pay-go" id="paidBtn" style="margin-top:10px;background:#1fa84a;color:#fff">I HAVE PAID</button>
        <?php elseif ($paidAck): ?>
        <p class="pay-route" style="text-align:center">Checking for the bank credit SMS<?php if ((int)($verify['remaining'] ?? 0) > 0): ?> · <b id="verifyTimer"><?=(int)$verify['remaining']?></b>s<?php endif; ?></p>
        <?php endif; ?>
        <?php endif; ?>
        <?php if (!empty($pay_video_url) && empty($hide_pay_extras)): ?>
        <video class="pay-video" controls playsinline preload="metadata" src="<?=e($pay_video_url)?>"></video>
        <p class="pay-video-note">Make a direct payment and never take a screenshot. It does not support.</p>
        <?php endif; ?>
    </form>
    <?php if ($pending && !$hide_pay_extras): ?>
    <div class="pay-pending">
        <b><?=e($pending['status'])?></b>
        <div>Reference: <?=e($pending['reference'])?> — Bank confirmation pending...</div>
    </div>
    <?php endif; ?>
    <?php if ($bank && !$hide_pay_extras): ?>
    <p class="pay-route">Current payment route: <?=e($bank['bank_name'])?> • UPI ID: <?=e($bank['vpa'])?></p>
    <button type="button" class="pay-copy" id="copyVpa" data-vpa="<?=e($bank['vpa'])?>">COPY UPI ID</button>
    <?php elseif (!$bank): ?>
    <div class="error">No active bank is configured.</div>
    <?php endif; ?>
    <?php if ($footer_link): ?>
    <p class="pay-more">
        <?php if (!empty($footer_link['class']) && str_contains((string)$footer_link['class'], 'pay-go')): ?>
        <a class="pay-go" href="<?=e($footer_link['href'])?>" style="display:block;text-align:center;text-decoration:none"><?=e($footer_link['label'])?></a>
        <?php else: ?>
        <a href="<?=e($footer_link['href'])?>"><?=e($footer_link['label'])?></a>
        <?php endif; ?>
    </p>
    <?php endif; ?>
</div>
<?php if (!empty($pay_qr)): ?>
<div id="verifyOverlay" class="pay-verify" role="status" aria-live="polite">
    <div class="pay-verify-card">
        <div class="pay-spin"></div>
        <h2>Verifying payment</h2>
        <p>Checking the bank credit SMS. Please wait.</p>
    </div>
</div>
<div id="successPopup" class="pay-pop" role="dialog" aria-modal="true" aria-labelledby="popTitle">
    <div class="pay-pop-card">
        <div class="pay-ok-mark">✓</div>
        <h2 id="popTitle">Payment Successful!</h2>
        <p class="pay-pop-amt">Amount ₹<span id="popAmount"><?=e(number_format((float)($pay_qr['requested_amount'] ?? 0), 2, '.', ''))?></span></p>
        <p>Payment ID: <span class="mono" id="popRef"><?=e((string)$pay_qr['reference'])?></span></p>
        <p id="popUtr" style="display:none">UTR: <b class="mono" id="popUtrVal"></b></p>
        <p class="muted">Confirmed from the bank credit SMS.</p>
        <div class="pay-invoice-actions">
        <a class="pay-go pay-invoice" id="popInvoice" href="<?=e(url('/p/'.$c['code'].'/result/'.$pay_qr['reference'].'/invoice.pdf'))?>" target="_blank" rel="noopener">Payment Success Invoice</a>
        <?php $invoiceHref = url('/p/'.$c['code'].'/result/'.$pay_qr['reference'].'/invoice.pdf'); include __DIR__.'/_invoice_share.php'; ?>
        </div>
        <button type="button" class="pay-go" id="popOk">OK</button>
    </div>
</div>
<style>
.pay-verify,.pay-pop{position:fixed;inset:0;background:rgba(0,0,0,.78);z-index:40;display:none;align-items:center;justify-content:center;padding:18px}
.pay-verify.on,.pay-pop.on{display:flex}
.pay-verify-card,.pay-pop-card{background:#141414;color:#fff;border:1px solid #3a3a3a;border-radius:18px;padding:28px 22px;max-width:420px;width:100%;text-align:center}
.pay-pop-card{border-color:#f5c518}
.pay-spin{width:68px;height:68px;margin:0 auto 16px;border:5px solid #333;border-top-color:#f5c518;border-radius:50%;animation:payspin .8s linear infinite}
@keyframes payspin{to{transform:rotate(360deg)}}
.pay-verify-card h2,.pay-pop-card h2{margin:8px 0 8px;font-size:1.45rem}
.pay-verify-card p,.pay-pop-card p{margin:8px 0;color:#ddd}
.pay-pop-amt{font-size:1.35rem;font-weight:800;color:#f5c518}
.pay-pop-card .pay-go{width:100%;margin-top:14px}
a.pay-invoice{display:block;text-align:center;text-decoration:none;box-sizing:border-box;border-radius:9px}
.pay-invoice-actions{display:flex;gap:8px;align-items:stretch;margin-top:16px}
.pay-invoice-actions .pay-go,.pay-invoice-actions .pay-invoice{flex:1;width:auto;margin:0}
</style>
<?php endif; ?>
<?php if (!empty($pay_video_url)): ?>
<style>
.pay-video{display:block;width:100%;margin:14px 0 0;border-radius:12px;background:#000}
.pay-video-note{margin:12px 0 0;color:#f5c518;font-weight:700;line-height:1.45;text-align:center}
</style>
<?php endif; ?>
<script>
(function(){
  var show=document.getElementById('amtShow'), hid=document.getElementById('amtVal'), mode=document.getElementById('amtMode');
  function paint(n){
    var v=String(n).replace(/,/g,'');
    hid.value=v;
    var num=Number(v);
    show.textContent=isFinite(num)&&v!==''?num.toFixed(2):'0.00';
  }
  hid.addEventListener('input', function(){
    paint(hid.value);
    if(mode) mode.value='manual';
    document.querySelectorAll('.pay-chip').forEach(function(x){ x.classList.remove('on'); });
  });
  document.querySelectorAll('.pay-chip').forEach(function(btn){
    btn.addEventListener('click', function(){
      if(btn.disabled) return;
      document.querySelectorAll('.pay-chip').forEach(function(x){ x.classList.remove('on'); });
      btn.classList.add('on');
      if(mode) mode.value='preset';
      paint(btn.getAttribute('data-amt'));
    });
  });
  var paidBtn=document.getElementById('paidBtn');
  if(paidBtn){
    var ackUrl=<?=json_encode(!empty($pay_qr['reference']) ? url('/pay/'.$pay_qr['reference'].'/acknowledge') : '')?>;
    var statusUrl=<?=json_encode(!empty($pay_qr['reference']) ? url('/pay/'.$pay_qr['reference'].'/status') : '')?>;
    var csrf=<?=json_encode(csrf_token())?>;
    var verifyBox=document.getElementById('verifyOverlay');
    var successBox=document.getElementById('successPopup');
    var pollTimer=null;
    function showVerify(){if(verifyBox)verifyBox.classList.add('on');if(successBox)successBox.classList.remove('on');}
    function showSuccess(d){
      if(pollTimer){clearInterval(pollTimer);pollTimer=null;}
      if(verifyBox)verifyBox.classList.remove('on');
      if(d){
        var amtEl=document.getElementById('popAmount');
        var refEl=document.getElementById('popRef');
        var utrRow=document.getElementById('popUtr');
        var utrVal=document.getElementById('popUtrVal');
        if(amtEl && d.amount) amtEl.textContent=d.amount;
        if(refEl && d.reference) refEl.textContent=d.reference;
        if(utrRow && utrVal && d.utr){utrVal.textContent=d.utr;utrRow.style.display='block';}
      }
      if(successBox) successBox.classList.add('on');
    }
    async function poll(){
      try{
        var r=await fetch(statusUrl,{cache:'no-store'});
        var d=await r.json();
        if(d && (d.status==='SUCCESS' || d.view==='success')) showSuccess(d);
      }catch(e){}
    }
    paidBtn.addEventListener('click', async function(){
      paidBtn.disabled=true;
      showVerify();
      try{
        var body=new URLSearchParams();
        body.set('_csrf', csrf);
        var r=await fetch(ackUrl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body.toString(),cache:'no-store'});
        var d=await r.json();
        if(d && (d.status==='SUCCESS' || d.view==='success')){showSuccess(d);return;}
        if(d && d.ok){poll();pollTimer=setInterval(poll,3000);return;}
        if(verifyBox) verifyBox.classList.remove('on');
        paidBtn.disabled=false;
      }catch(e){
        poll();
        pollTimer=setInterval(poll,3000);
      }
    });
    var popOk=document.getElementById('popOk');
    if(popOk) popOk.addEventListener('click', function(){ location.reload(); });
  }
  var copy=document.getElementById('copyVpa');
  if(copy){
    copy.addEventListener('click', function(){
      var v=copy.getAttribute('data-vpa')||'';
      if(navigator.clipboard&&navigator.clipboard.writeText){ navigator.clipboard.writeText(v); }
      else { var t=document.createElement('textarea'); t.value=v; document.body.appendChild(t); t.select(); document.execCommand('copy'); t.remove(); }
      copy.textContent='COPIED';
      setTimeout(function(){ copy.textContent='COPY UPI ID'; }, 1600);
    });
  }
})();
</script>
