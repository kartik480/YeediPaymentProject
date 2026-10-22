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
        <?php $acc = trim((string)($c['account_number'] ?? '')); ?>
        <div class="pay-who grow"><?=e($c['name'])?><div><?php if ($acc !== ''): ?>Account <?=e($acc)?><?php else: ?><?=e($c['code'])?><?php endif; ?></div></div>
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
        <?php $payStatus = strtoupper((string)($t['status'] ?? '')); $waitLeft = BankSmsService::qrSecondsLeft($t); $payOutcome = TransactionService::customerOutcome($t); ?>
        <?php if ($payStatus === 'SUCCESS'): ?>
        <button type="button" class="pay-go" id="paidBtn" disabled style="margin-top:10px;background:#1fa84a;color:#fff">PAID</button>
        <?php elseif ($payOutcome['code'] === 'pending'): ?>
        <div class="pay-timer" id="payTimerBox">
            <b id="payTimer"><?=(int)$waitLeft?></b>
            <span>seconds left</span>
        </div>
        <button type="button" class="pay-go" id="paidBtn" data-acked="<?= $paidAck ? '1' : '0' ?>" <?= $paidAck ? 'disabled' : '' ?> style="margin-top:10px;background:#1fa84a;color:#fff"><?= $paidAck ? 'CHECKING SERVER…' : 'I HAVE PAID' ?></button>
        <button type="button" class="pay-go" id="cancelBtn" style="margin-top:8px;background:#2a2a2a;color:#fff">Cancel</button>
        <?php elseif ($payOutcome['label'] !== ''): ?>
        <h2 id="payOutcome" style="text-align:center;color:#ff3b3b"><?=e($payOutcome['label'])?></h2>
        <?php endif; ?>
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
        <p class="muted">Confirmed by the server. The receipt file is created automatically.</p>
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
.pay-timer{position:sticky;top:8px;z-index:25;display:flex;align-items:baseline;justify-content:center;gap:10px;margin:14px 0 4px;padding:12px 16px;border:2px solid #f5c518;border-radius:14px;background:#111;color:#fff}
.pay-timer b{font-size:2.6rem;line-height:1;color:#f5c518;min-width:2ch;text-align:right}
.pay-timer span{font-weight:700}
</style>
<?php endif; ?>
<?php if (!empty($pay_video_url)): ?>
<style>
.pay-video{display:block;width:100%;margin:14px 0 0;border-radius:12px;background:#000}
.pay-video-note{margin:12px 0 0;color:#f5c518;font-weight:700;line-height:1.45;text-align:center}
</style>
<?php endif; ?>
<?php
if (!empty($pay_qr['reference']) && !empty($c['code'])) {
    $receiptRef = (string)$pay_qr['reference'];
    $receiptPdfUrl = url('/p/'.$c['code'].'/result/'.$receiptRef.'/invoice.pdf');
    $receiptPrintUrl = url('/p/'.$c['code'].'/result/'.$receiptRef.'/invoice.print');
    $receiptAuto = strtoupper((string)($pay_qr['status'] ?? '')) === 'SUCCESS';
    include __DIR__.'/_receipt_auto.php';
}
?>
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
  var ackUrl=<?=json_encode(!empty($pay_qr['reference']) ? url('/pay/'.$pay_qr['reference'].'/acknowledge') : '')?>;
  var statusUrl=<?=json_encode(!empty($pay_qr['reference']) ? url('/pay/'.$pay_qr['reference'].'/status') : '')?>;
  var cancelUrl=<?=json_encode(!empty($pay_qr['reference']) ? url('/pay/'.$pay_qr['reference'].'/cancel') : '')?>;
  var resultUrl=<?=json_encode(!empty($pay_qr['reference']) && !empty($c['code']) ? url('/p/'.$c['code'].'/result/'.$pay_qr['reference']) : '')?>;
  var homeUrl=<?=json_encode(!empty($c['code']) ? url('/p/'.$c['code']) : '/')?>;
  var csrf=<?=json_encode(csrf_token())?>;
  var paidMsg=document.getElementById('paidMsg');
  var successBox=document.getElementById('successPopup');
  var timerEl=document.getElementById('payTimer');
  var left=<?= (int)($waitLeft ?? 0) ?>;
  var pollTimer=null;
  var checking=paidBtn && paidBtn.getAttribute('data-acked')==='1';
  var finished=false;
  function paintTimer(){ if(timerEl) timerEl.textContent=String(Math.max(0, left)); }
  function stopPoll(){if(pollTimer){clearInterval(pollTimer);pollTimer=null;}}
  function acknowledged(){ return checking || (paidBtn && paidBtn.getAttribute('data-acked')==='1'); }
  function onTimerEnd(){
    if(finished) return;
    paintTimer();
    if(!acknowledged()){
      if(paidBtn){ paidBtn.disabled=true; paidBtn.textContent='Session Expired'; }
      var cancel=document.getElementById('cancelBtn');
      if(cancel) cancel.disabled=true;
      if(paidMsg) paidMsg.textContent='Session Expired';
      var box=document.getElementById('payTimerBox');
      if(box) box.innerHTML='<b>0</b><span>Session Expired</span>';
    }
    poll();
  }
  function openWebsite(target){
    var next=target || resultUrl || homeUrl;
    if(next) location.replace(next);
  }
  function showSuccess(d){
    if(finished) return;
    finished=true;
    stopPoll();
    if(paidBtn){paidBtn.textContent='PAID';paidBtn.disabled=true;}
    openWebsite(resultUrl);
  }
  function closedLabel(d){
    var code=String(d.outcome||'');
    if(code==='expired') return 'Session Expired';
    if(code==='cancelled') return 'Payment Cancelled';
    if(code==='failed') return 'Payment Failed';
    var status=String(d.status||'').toUpperCase();
    if(status==='EXPIRED') return 'Session Expired';
    if(status==='FAILED') return 'Payment Failed';
    return '';
  }
  function showClosed(d){
    var label=closedLabel(d);
    if(!label) return false;
    finished=true;
    stopPoll();
    if(paidBtn){paidBtn.textContent=label;paidBtn.disabled=true;}
    if(paidMsg) paidMsg.textContent=label;
    openWebsite(resultUrl);
    return true;
  }
  function applyServer(d){
    if(!d) return;
    var status=String(d.status||'').toUpperCase();
    var view=String(d.view||'');
    if(showClosed(d)) return;
    if(paidMsg && d.customer_message) paidMsg.textContent=d.customer_message;
    if(status==='SUCCESS' && d.outcome==='paid'){showSuccess(d);return;}
    if(!checking || !paidBtn) return;
    paidBtn.disabled=true;
    if(view==='review'){paidBtn.textContent='UNDER VERIFICATION';}
    else {paidBtn.textContent='CHECKING SERVER…';}
  }
  async function poll(){
    if(!statusUrl || finished) return;
    try{
      var r=await fetch(statusUrl,{cache:'no-store'});
      applyServer(await r.json());
    }catch(e){}
  }
  function watchServer(){
    poll();
    if(!pollTimer) pollTimer=setInterval(poll,3000);
  }
  if(statusUrl && paidBtn){
    watchServer();
    if(timerEl){
      paintTimer();
      setInterval(function(){
        if(finished || left<=0) return;
        left-=1;
        paintTimer();
        if(left===0) onTimerEnd();
      },1000);
    }
    paidBtn.addEventListener('click', async function(){
      if(paidBtn.disabled || finished) return;
      if(left<=0 && paidBtn.getAttribute('data-acked')!=='1') return;
      checking=true;
      paidBtn.disabled=true;
      paidBtn.textContent='CHECKING SERVER…';
      try{
        var body=new URLSearchParams();
        body.set('_csrf', csrf);
        var r=await fetch(ackUrl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body.toString(),cache:'no-store'});
        applyServer(await r.json());
      }catch(e){}
      watchServer();
    });
  }
  var cancelBtn=document.getElementById('cancelBtn');
  if(cancelBtn && cancelUrl){
    cancelBtn.addEventListener('click', async function(){
      if(finished) return;
      cancelBtn.disabled=true;
      if(paidBtn) paidBtn.disabled=true;
      try{
        var body=new URLSearchParams();
        body.set('_csrf', csrf);
        var r=await fetch(cancelUrl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body.toString(),cache:'no-store'});
        applyServer(await r.json());
      }catch(e){
        cancelBtn.disabled=false;
      }
    });
  }
  var popOk=document.getElementById('popOk');
  if(popOk) popOk.addEventListener('click', function(){ openWebsite(resultUrl); });
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
