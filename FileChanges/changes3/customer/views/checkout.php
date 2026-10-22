<?php include __DIR__.'/_head.php'; $st=strtoupper((string)$t['status']); $mo=$merchant_order??null; ?>
<div class="pay">
  <h1>UPI Payment</h1>
  <?php if($mo): ?><p>Order ID: <b><?=e($mo['order_id'])?></b></p><?php endif; ?>
  <p>Payment ID: <span class="mono"><?=e($t['reference'])?></span></p>
  <h2>₹<?=number_format((float)($t['confirmed_amount']??$t['requested_amount']??0),2)?></h2>
  <p>Status: <b id="checkoutStatus"><?=e($st)?></b></p>
  <?php if(!empty($error)): ?><div class="warn"><?=e($error)?></div><?php endif; ?>
  <?php if($st==='PENDING'): ?>
    <?php $qrSize=250;include __DIR__.'/_pay_qr.php'; ?>
    <button id="checkoutAck" class="pay-go">I HAVE PAID</button>
  <?php elseif($st==='SUCCESS'): ?>
    <div class="pay-ok"><h2>Payment Successful</h2><p>UTR: <?=e(BankSmsService::displayUtr($t))?></p></div>
  <?php else: ?>
    <div class="error">Payment <?=e($st)?></div>
  <?php endif; ?>
  <?php if($mo && !empty($mo['return_url']) && $st!=='PENDING'): ?>
    <p><a class="pay-go" style="display:inline-block;text-decoration:none" href="<?=e((string)$mo['return_url'])?>">Return to Merchant</a></p>
  <?php endif; ?>
</div>
<?php if($st==='PENDING'): ?><script>(function(){var b=document.getElementById('checkoutAck'),s=document.getElementById('checkoutStatus');async function poll(){try{var r=await fetch(<?=json_encode(url('/pay/'.$t['reference'].'/status'))?>,{cache:'no-store'}),d=await r.json();s.textContent=d.status||'';if(d.status!=='PENDING')location.reload();}catch(e){}}b.addEventListener('click',async function(){b.disabled=true;var body=new URLSearchParams();body.set('_csrf',<?=json_encode(csrf_token())?>);await fetch(<?=json_encode(url('/pay/'.$t['reference'].'/acknowledge'))?>,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body.toString()});poll();});setInterval(poll,4000);})();</script><?php endif; ?>
<?php include __DIR__.'/_foot.php'; ?>
