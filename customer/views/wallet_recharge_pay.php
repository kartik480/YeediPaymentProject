<?php
include __DIR__.'/_dash_head.php';
$t = $t ?? [];
$recharge = $recharge ?? [];
$st = strtoupper((string)($t['status'] ?? ''));
$isOk = $st === 'SUCCESS';
$isBad = in_array($st, ['FAILED', 'EXPIRED'], true);
$waitLeft = TransactionService::checkWaitLeft($t);
$amt = TransactionService::displayAmount($t);
?>
<div class="pay dash col-dash">
    <h1 class="col-title">Wallet Recharge</h1>

    <div class="col-panel">
        <div id="okBox" class="pay-ok" <?=$isOk ? '' : 'hidden'?>>
            <div class="pay-ok-mark">✓</div>
            <h2>Recharge Successful</h2>
            <div class="pay-inr">INR <?=e($amt !== '' ? $amt : '0.00')?></div>
            <p class="muted">Wallet reference: <span class="mono"><?=e($recharge['reference'] ?? '')?></span></p>
            <p>Your wallet balance has been updated.</p>
        </div>

        <div id="waitBox" class="pay-pending" <?=$isOk || $isBad ? 'hidden' : ''?>>
            <b id="stLabel"><?=e($st)?></b>
            <div>Reference: <?=e($t['reference'] ?? '')?> — Scan the QR and pay. <span id="checkLabel">Checking payment status…</span> <b id="payTimer"><?=$waitLeft?></b></div>
            <div style="margin-top:8px">After payment, enter your <b>12-digit UTR</b> to complete the recharge.</div>
        </div>

        <div id="badBox" class="error" <?=$isBad ? '' : 'hidden'?>>
            Recharge <?=e(strtolower($st))?>. Reference: <?=e($t['reference'] ?? '')?>
        </div>

        <?php if (!$isOk && !$isBad): ?>
        <div class="pay-inr">INR <?=amount_fmt($t['requested_amount'] ?? 0)?></div>
        <p class="pay-scan">Scan this bank QR to recharge</p>
        <?php include __DIR__.'/_pay_qr.php'; ?>
        <?php endif; ?>

        <p class="pay-route">Payment route: <?=e($t['bank_name'] ?? '')?> • UPI ID: <?=e($t['vpa'] ?? '')?></p>
        <button type="button" class="pay-copy" id="copyVpa" data-vpa="<?=e($t['vpa'] ?? '')?>">COPY UPI ID</button>

        <?php if (!$isOk && !$isBad): ?>
        <?php if (!empty($utr_error)): ?><div class="error"><?=e($utr_error)?></div><?php endif; ?>
        <?php if (empty($t['customer_reported_utr'])): ?>
        <form method="post" action="<?=e(url($custBase.'/wallet/recharge/'.$t['reference'].'/utr'))?>" class="col-filter-form" style="margin-top:14px">
            <?=csrf_field()?>
            <label>UTR / RRN from your UPI app
                <input name="utr" required minlength="6" maxlength="160" placeholder="Enter UTR after you pay">
            </label>
            <label>Gmail ID
                <input type="email" name="email" required maxlength="180" placeholder="Enter your Gmail ID" autocomplete="email">
            </label>
            <button type="submit" class="col-btn-gold">Save UTR</button>
        </form>
        <?php else: ?>
        <p class="pay-route">UTR saved: <span class="mono"><?=e($t['customer_reported_utr'])?></span></p>
        <?php if (!empty($t['customer_reported_email'])): ?>
        <p class="pay-route">Gmail: <span class="mono"><?=e($t['customer_reported_email'])?></span></p>
        <?php endif; ?>
        <?php endif; ?>
        <?php endif; ?>

        <p style="margin-top:16px"><a href="<?=e(url($custBase.'/wallet'))?>">← Back to wallet</a></p>
    </div>
</div>
<?php if (!$isOk && !$isBad): ?>
<?php /* bank QR scripts loaded by _pay_qr.php when needed */ ?>
<?php endif; ?>
<script>
(function(){
  var copy=document.getElementById('copyVpa');
  if(copy){copy.addEventListener('click',function(){var v=copy.getAttribute('data-vpa')||'';if(navigator.clipboard&&navigator.clipboard.writeText)navigator.clipboard.writeText(v);copy.textContent='COPIED';setTimeout(function(){copy.textContent='COPY UPI ID';},1600);});}
  var url=<?=json_encode(url('/pay/'.($t['reference']??'').'/status'))?>;
  var wallet=<?=json_encode(url($custBase.'/wallet'))?>;
  var last=<?=json_encode($st)?>;
  var left=<?=(int)$waitLeft?>;
  var timerEl=document.getElementById('payTimer');
  if(last==='SUCCESS'){ setTimeout(function(){ location.href=wallet; }, 2200); }
  async function poll(){
    try{
      var r=await fetch(url,{cache:'no-store'});
      var d=await r.json();
      if(!d||!d.status||d.status===last) return;
      last=d.status;
      location.reload();
    }catch(e){}
  }
  if(last==='PENDING'){
    var label=document.getElementById('checkLabel');
    setInterval(function(){
      if(left>0){ left-=1; if(timerEl) timerEl.textContent=left>0?String(left):''; if(left<=0 && label) label.textContent='Enter your UTR to complete recharge.'; }
    },1000);
    setInterval(poll,3000);
    poll();
  }
})();
</script>
<?php include __DIR__.'/_dash_foot.php'; ?>
