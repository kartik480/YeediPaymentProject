<?php
include __DIR__.'/_head.php';
$st = strtoupper((string)$t['status']);
$amt = TransactionService::displayAmount($t);
$viewState = BankSmsService::customerView($t);
$isOk = $viewState === 'success';
$isReview = $viewState === 'review';
$isUnmatched = $viewState === 'unmatched';
$isWaiting = $viewState === 'waiting';
$isBad = $viewState === 'closed';
$utrShow = BankSmsService::displayUtr($t);
$when = BankSmsService::formatLocal((string)($isOk ? ($t['updated_at'] ?? $t['created_at']) : $t['created_at']));
$waitLeft = BankSmsService::qrSecondsLeft($t);
$verify = TransactionService::verificationState($t);
$paidAck = !empty($t['customer_paid_ack_at']);
?>
<div class="pay">
    <div class="row">
        <?php $acc=trim((string)($c['account_number']??'')); ?>
        <div class="pay-who grow"><?=e($c['name'])?><div><?php if($acc!==''): ?>Account <?=e($acc)?><?php else: ?><?=e($c['code'])?><?php endif; ?></div></div>
        <a class="pay-copy" href="<?=e(url('/c/'.$c['code'].'/qr'))?>" style="width:auto;padding:8px 14px;text-decoration:none">Merchant QR</a>
    </div>

    <?php if ($isOk): ?>
    <div id="okBox" class="pay-ok">
        <div class="pay-ok-mark">✓</div>
        <h1>Payment Successful!</h1>
        <div class="pay-inr">Amount ₹<?=e($amt !== '' ? $amt : '0.00')?></div>
        <p>Payment ID: <span class="mono"><?=e($t['reference'])?></span></p>
        <p class="paid-mark">PAID</p>
        <?php if ($utrShow !== ''): ?><p>UTR: <span class="mono"><?=e($utrShow)?></span></p><?php endif; ?>
        <p>Bank: <?=e($t['bank_name'])?></p>
        <p>Time: <?=e($when)?></p>
        <p class="muted">Note: The bill amount is inclusive of applicable GST.</p>
        <p class="muted">Confirmed by the server. The receipt file is created automatically.</p>
        <a class="pay-go" href="<?=e(url('/p/'.$c['code']))?>" style="display:block;text-align:center;text-decoration:none;margin-top:14px;background:#f5c518;color:#111">Return to website</a>
        <div class="pay-invoice-actions">
        <a class="pay-go pay-invoice" href="<?=e(url('/p/'.$c['code'].'/result/'.$t['reference'].'/invoice.pdf'))?>" target="_blank" rel="noopener">Payment Success Invoice</a>
        <?php $invoiceHref = url('/p/'.$c['code'].'/result/'.$t['reference'].'/invoice.pdf'); include __DIR__.'/_invoice_share.php'; ?>
        </div>
    </div>
    <?php elseif ($isReview): ?>
    <div id="reviewBox" class="warn" style="color:#3d3200">
        <h1 style="font-size:1.25rem;margin:0 0 8px">Payment Received Under Verification</h1>
        <p>We received your payment but multiple payments are pending. Our team will verify shortly.</p>
        <p>Payment ID: <span class="mono"><?=e($t['reference'])?></span></p>
        <p>Amount: ₹<?=e($amt !== '' ? $amt : '0.00')?></p>
    </div>
    <?php elseif ($isUnmatched): ?>
    <div id="missBox" class="error">
        <h1 style="font-size:1.25rem;margin:0 0 8px">Payment Not Matched Yet</h1>
        <p>We have not found a matching payment for this transaction. It will be reviewed manually.</p>
        <p>Amount: ₹<?=e($amt !== '' ? $amt : '0.00')?></p>
        <p>Time: <?=e($when)?></p>
        <p>Payment ID: <span class="mono"><?=e($t['reference'])?></span></p>
    </div>
    <?php elseif ($isBad): ?>
    <?php $outcome = TransactionService::customerOutcome($t); ?>
    <div id="badBox" class="error">
        <h1 style="font-size:1.35rem;margin:0 0 8px"><?=e($outcome['label'])?></h1>
        <p>Payment ID: <span class="mono"><?=e($t['reference'])?></span></p>
        <p>Amount: ₹<?=e($amt !== '' ? $amt : '0.00')?></p>
        <a class="pay-go" href="<?=e(url('/p/'.$c['code']))?>" style="display:block;text-align:center;text-decoration:none;margin-top:14px;background:#f5c518;color:#111">Return to website</a>
    </div>
    <?php endif; ?>

    <?php if ($isWaiting): ?>
    <div class="pay-upi">
        <div class="pay-upi-mark">UPI</div>
        <div>Scan &amp; Pay</div>
        <div class="pay-instant">₹<?=e($amt !== '' ? $amt : '0.00')?></div>
    </div>
    <p class="pay-scan">Scan and pay ₹<?=e($amt !== '' ? $amt : '0.00')?>. Your UPI app will open with this amount filled in.</p>
    <?php $qrSize = 260; include __DIR__.'/_pay_qr.php'; ?>
    <p class="pay-route">Payment ID: <span class="mono"><?=e($t['reference'])?></span></p>
    <div class="pay-timer" id="payTimerBox"><b id="payTimer"><?=(int)$waitLeft?></b><span>seconds left</span></div>
    <p class="muted" id="paidMsg" style="color:#ff3b3b;font-weight:700"><?=e($verify['message'])?></p>
    <button type="button" class="pay-go" id="paidBtn" data-acked="<?= $paidAck ? '1' : '0' ?>" <?= $paidAck ? 'disabled' : '' ?>><?= $paidAck ? 'CHECKING SERVER…' : 'I HAVE PAID' ?></button>
    <button type="button" class="pay-go" id="cancelBtn" style="margin-top:8px;background:#2a2a2a;color:#fff">Cancel</button>
    <?php if (trim((string)($t['adc_payment_id'] ?? '')) !== '' && trim((string)($t['adc_public_token'] ?? '')) !== ''): ?>
    <p class="pay-route"><a href="<?=e('/ADC/03_CUSTOMER_PAYMENT_DASHBOARD/?payment_id='.rawurlencode((string)$t['adc_payment_id']).'&token='.rawurlencode((string)$t['adc_public_token']))?>">Open payment check</a></p>
    <?php endif; ?>
    <p class="pay-route">Current payment route: <?=e($t['bank_name'])?> • UPI ID: <?=e($t['vpa'])?></p>
    <button type="button" class="pay-copy" id="copyVpa" data-vpa="<?=e($t['vpa'])?>">COPY UPI ID</button>
    <?php endif; ?>

    <?php if (!$isOk && !$isBad): ?>
    <p class="pay-more">
        <a class="pay-go" href="<?=e(url('/p/'.$c['code']))?>" style="display:block;text-align:center;text-decoration:none;margin-bottom:10px;background:#f5c518;color:#111">Return to website</a>
    </p>
    <?php endif; ?>
</div>
<?php if (!$isBad): ?>
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
        <p class="pay-pop-amt">Amount ₹<span id="popAmount"><?=e($amt !== '' ? $amt : '0.00')?></span></p>
        <p>Payment ID: <span class="mono" id="popRef"><?=e($t['reference'])?></span></p>
        <p id="popUtr" style="display:none">UTR: <b class="mono" id="popUtrVal"></b></p>
        <p class="muted">Confirmed by the server. The receipt file is created automatically.</p>
        <div class="pay-invoice-actions">
        <a class="pay-go pay-invoice" id="popInvoice" href="<?=e(url('/p/'.$c['code'].'/result/'.$t['reference'].'/invoice.pdf'))?>" target="_blank" rel="noopener">Payment Success Invoice</a>
        <?php $invoiceHref = url('/p/'.$c['code'].'/result/'.$t['reference'].'/invoice.pdf'); include __DIR__.'/_invoice_share.php'; ?>
        </div>
        <a class="pay-go" id="popOk" href="<?=e(url('/p/'.$c['code']))?>" style="display:block;text-align:center;text-decoration:none;background:#f5c518;color:#111">Return to website</a>
    </div>
</div>
<?php endif; ?>
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
.paid-mark{color:#d01212;font-weight:800;margin:0 0 10px}
.pay-timer{position:sticky;top:8px;z-index:25;display:flex;align-items:baseline;justify-content:center;gap:10px;margin:14px 0;padding:12px 16px;border:2px solid #f5c518;border-radius:14px;background:#111;color:#fff}
.pay-timer b{font-size:2.6rem;line-height:1;color:#f5c518;min-width:2ch;text-align:right}
.pay-timer span{font-weight:700}
a.pay-invoice{display:block;text-align:center;text-decoration:none;box-sizing:border-box;border-radius:9px}
.pay-ok .pay-invoice{margin-top:16px}
.pay-invoice-actions{display:flex;gap:8px;align-items:stretch;margin-top:16px}
.pay-invoice-actions .pay-go,.pay-invoice-actions .pay-invoice{flex:1;width:auto;margin:0}
</style>
<?php
$receiptRef = (string)$t['reference'];
$receiptPdfUrl = url('/p/'.$c['code'].'/result/'.$receiptRef.'/invoice.pdf');
$receiptPrintUrl = url('/p/'.$c['code'].'/result/'.$receiptRef.'/invoice.print');
$receiptAuto = $isOk && $st === 'SUCCESS';
include __DIR__.'/_receipt_auto.php';
?>
<script>
(function(){
  var copy=document.getElementById('copyVpa');
  if(copy){copy.addEventListener('click',function(){var v=copy.getAttribute('data-vpa')||'';if(navigator.clipboard&&navigator.clipboard.writeText)navigator.clipboard.writeText(v);copy.textContent='COPIED';setTimeout(function(){copy.textContent='COPY UPI ID';},1600);});}
  var url=<?=json_encode(url('/pay/'.$t['reference'].'/status'))?>;
  var ackUrl=<?=json_encode(url('/pay/'.$t['reference'].'/acknowledge'))?>;
  var cancelUrl=<?=json_encode(url('/pay/'.$t['reference'].'/cancel'))?>;
  var csrf=<?=json_encode(csrf_token())?>;
  var view=<?=json_encode($viewState)?>;
  var left=<?=(int)$waitLeft?>;
  var verifyLeft=<?=(int)($verify['remaining'] ?? 0)?>;
  var timerEl=document.getElementById('payTimer');
  var verifyEl=document.getElementById('verifyTimer');
  var paidBtn=document.getElementById('paidBtn');
  var paidMsg=document.getElementById('paidMsg');
  var successBox=document.getElementById('successPopup');
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
  function showSuccess(d){
    if(finished) return;
    finished=true;
    stopPoll();
    if(paidBtn){paidBtn.textContent='PAID';paidBtn.disabled=true;}
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
    if(d && d.status==='SUCCESS' && d.receipt===true && d.outcome==='paid' && window.yeediIssueReceipt){
      window.yeediIssueReceipt(<?=json_encode($receiptPdfUrl)?>, <?=json_encode($receiptPrintUrl)?>, d.reference || <?=json_encode($receiptRef)?>);
    }
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
    location.reload();
    return true;
  }
  function applyServer(d){
    if(!d) return;
    var status=String(d.status||'').toUpperCase();
    var viewName=String(d.view||'');
    if(showClosed(d)) return;
    if(paidMsg && d.customer_message) paidMsg.textContent=d.customer_message;
    if(status==='SUCCESS' && d.outcome==='paid'){showSuccess(d);return;}
    if((view==='review'||view==='unmatched') && viewName && viewName!==view){location.reload();return;}
    if(!checking || !paidBtn) return;
    paidBtn.disabled=true;
    if(viewName==='review'){paidBtn.textContent='UNDER VERIFICATION';}
    else {paidBtn.textContent='CHECKING SERVER…';}
  }
  async function poll(){
    if(finished) return;
    try{
      var r=await fetch(url,{cache:'no-store'});
      applyServer(await r.json());
    }catch(e){}
  }
  function watchServer(){
    poll();
    if(!pollTimer) pollTimer=setInterval(poll,3000);
  }
  if(paidBtn){
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
  if(cancelBtn){
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
  var homeUrl=<?=json_encode(url('/p/'.$c['code']))?>;
  var popOk=document.getElementById('popOk');
  if(popOk && popOk.tagName!=='A') popOk.addEventListener('click', function(){ location.replace(homeUrl); });
  if(view==='waiting'){
    paintTimer();
    setInterval(function(){
      if(!finished && left>0){left-=1;paintTimer();if(left===0)onTimerEnd();}
      if(verifyLeft>0){verifyLeft-=1;if(verifyEl)verifyEl.textContent=String(verifyLeft);}
    },1000);
    watchServer();
  } else if(view==='review'||view==='unmatched'){
    watchServer();
  }
})();
</script>
<?php include __DIR__.'/_foot.php'; ?>
