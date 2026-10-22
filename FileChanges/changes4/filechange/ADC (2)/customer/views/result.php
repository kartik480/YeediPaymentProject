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
        <div class="pay-who grow"><?=e($c['name'])?><div><?=e($c['code'])?><?php $acc=trim((string)($c['account_number']??'')); if($acc!==''): ?> · A/c <?=e($acc)?><?php endif; ?></div></div>
        <a class="pay-copy" href="<?=e(url('/c/'.$c['code'].'/qr'))?>" style="width:auto;padding:8px 14px;text-decoration:none">Merchant QR</a>
    </div>

    <?php if ($isOk): ?>
    <div id="okBox" class="pay-ok">
        <div class="pay-ok-mark">✓</div>
        <h1>Payment Successful!</h1>
        <div class="pay-inr">Amount ₹<?=e($amt !== '' ? $amt : '0.00')?></div>
        <p>Payment ID: <span class="mono"><?=e($t['reference'])?></span></p>
        <?php if ($utrShow !== ''): ?><p>UTR: <span class="mono"><?=e($utrShow)?></span></p><?php endif; ?>
        <p>Bank: <?=e($t['bank_name'])?></p>
        <p>Time: <?=e($when)?></p>
        <p class="muted">Confirmed from the bank credit SMS. You do not need to enter a UTR.</p>
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
    <div id="badBox" class="error">
        Payment <?=e(strtolower($st))?>. Reference: <?=e($t['reference'])?>
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
    <p class="pay-route">This will expire in <b id="payTimer"><?=sprintf('%d:%02d', intdiv($waitLeft, 60), $waitLeft % 60)?></b></p>
    <p class="muted" id="paidMsg" style="color:#ff3b3b;font-weight:700"><?=e($verify['message'])?></p>
    <?php if (!$paidAck): ?>
    <button type="button" class="pay-go" id="paidBtn">I HAVE PAID — CHECK NOW</button>
    <?php else: ?>
    <p class="pay-route">Checking for the bank credit SMS<?php if ((int)$verify['remaining'] > 0): ?> · <b id="verifyTimer"><?=(int)$verify['remaining']?></b>s<?php endif; ?></p>
    <?php endif; ?>
    <?php if (trim((string)($t['adc_payment_id'] ?? '')) !== '' && trim((string)($t['adc_public_token'] ?? '')) !== ''): ?>
    <p class="pay-route"><a href="<?=e('/ADC/03_CUSTOMER_PAYMENT_DASHBOARD/?payment_id='.rawurlencode((string)$t['adc_payment_id']).'&token='.rawurlencode((string)$t['adc_public_token']))?>">Open payment check</a></p>
    <?php endif; ?>
    <p class="pay-route">Current payment route: <?=e($t['bank_name'])?> • UPI ID: <?=e($t['vpa'])?></p>
    <button type="button" class="pay-copy" id="copyVpa" data-vpa="<?=e($t['vpa'])?>">COPY UPI ID</button>
    <?php endif; ?>

    <p class="pay-more">
        <a class="pay-go" href="<?=e(url('/c/'.$c['code'].'/qr'))?>" style="display:block;text-align:center;text-decoration:none;margin-bottom:10px">MERCHANT QR</a>
        <a href="<?=e(url('/p/'.$c['code']))?>">Pay again</a>
    </p>
</div>
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
        <p class="muted">Confirmed from the bank credit SMS.</p>
        <div class="pay-invoice-actions">
        <a class="pay-go pay-invoice" id="popInvoice" href="<?=e(url('/p/'.$c['code'].'/result/'.$t['reference'].'/invoice.pdf'))?>" target="_blank" rel="noopener">Payment Success Invoice</a>
        <?php $invoiceHref = url('/p/'.$c['code'].'/result/'.$t['reference'].'/invoice.pdf'); include __DIR__.'/_invoice_share.php'; ?>
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
.pay-ok .pay-invoice{margin-top:16px}
.pay-invoice-actions{display:flex;gap:8px;align-items:stretch;margin-top:16px}
.pay-invoice-actions .pay-go,.pay-invoice-actions .pay-invoice{flex:1;width:auto;margin:0}
</style>
<script>
(function(){
  var copy=document.getElementById('copyVpa');
  if(copy){copy.addEventListener('click',function(){var v=copy.getAttribute('data-vpa')||'';if(navigator.clipboard&&navigator.clipboard.writeText)navigator.clipboard.writeText(v);copy.textContent='COPIED';setTimeout(function(){copy.textContent='COPY UPI ID';},1600);});}
  var url=<?=json_encode(url('/pay/'.$t['reference'].'/status'))?>;
  var ackUrl=<?=json_encode(url('/pay/'.$t['reference'].'/acknowledge'))?>;
  var csrf=<?=json_encode(csrf_token())?>;
  var view=<?=json_encode($viewState)?>;
  var left=<?=(int)$waitLeft?>;
  var verifyLeft=<?=(int)($verify['remaining'] ?? 0)?>;
  var timerEl=document.getElementById('payTimer');
  var verifyEl=document.getElementById('verifyTimer');
  var paidBtn=document.getElementById('paidBtn');
  var verifyBox=document.getElementById('verifyOverlay');
  var successBox=document.getElementById('successPopup');
  var pollTimer=null;
  var checking=false;
  function fmt(s){var m=Math.floor(s/60),ss=s%60;return m+':'+(ss<10?'0':'')+ss;}
  function showVerify(){if(verifyBox)verifyBox.classList.add('on');if(successBox)successBox.classList.remove('on');}
  function showSuccess(d){
    if(pollTimer){clearInterval(pollTimer);pollTimer=null;}
    checking=false;
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
  function startChecking(){
    if(checking) return;
    checking=true;
    showVerify();
    poll();
    pollTimer=setInterval(poll,3000);
  }
  if(paidBtn){
    paidBtn.addEventListener('click', async function(){
      paidBtn.disabled=true;
      showVerify();
      try{
        var body=new URLSearchParams();
        body.set('_csrf', csrf);
        var r=await fetch(ackUrl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body.toString(),cache:'no-store'});
        var d=await r.json();
        if(d && (d.status==='SUCCESS' || d.view==='success')){showSuccess(d);return;}
        if(d && d.ok){startChecking();return;}
        if(verifyBox) verifyBox.classList.remove('on');
        paidBtn.disabled=false;
      }catch(e){
        startChecking();
      }
    });
  }
  var popOk=document.getElementById('popOk');
  if(popOk) popOk.addEventListener('click', function(){ location.reload(); });
  async function poll(){
    try{
      var r=await fetch(url,{cache:'no-store'});
      var d=await r.json();
      if(!d) return;
      if(d.status==='SUCCESS' || d.view==='success'){showSuccess(d);return;}
      if(d.view==='review' || d.view==='closed' || d.view==='unmatched'){
        if(view!==d.view) location.reload();
      }
    }catch(e){}
  }
  if(view==='waiting'){
    setInterval(function(){
      if(left>0){left-=1;if(timerEl)timerEl.textContent=fmt(left);}
      if(verifyLeft>0){verifyLeft-=1;if(verifyEl)verifyEl.textContent=String(verifyLeft);}
    },1000);
  }
  if(<?= $paidAck ? 'true' : 'false' ?> && view==='waiting') startChecking();
  else if(view==='review'||view==='unmatched'){
    setInterval(poll,3000);
    poll();
  }
})();
</script>
<?php include __DIR__.'/_foot.php'; ?>
