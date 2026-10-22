<?php include __DIR__.'/_head.php'; ?>
<div class="pay">
    <div class="pay-who"><?=e($t['customer_name'])?><div><?=e($t['customer_code'])?></div></div>
    <div class="pay-upi">
        <div class="pay-upi-mark">UPI</div>
        <div>Unified Payments Interface</div>
        <div class="pay-instant">INSTANT</div>
    </div>
    <?php if ($t['requested_amount']!==null): ?>
    <div class="pay-amt-lab">Amount</div>
    <div class="pay-inr">INR <?=amount_fmt($t['requested_amount'])?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?><div class="error"><?=e($error)?></div><?php endif; ?>
    <?php if (!empty($paid_reported)): ?><div class="ok">UTR saved. Status remains PENDING until reconciliation.</div><?php endif; ?>
    <div class="pay-pending">
        <b id="status"><?=e($t['status'])?></b>
        <div>Reference: <?=e($t['reference'])?><?=$t['status']==='PENDING'?' — Bank confirmation pending...':''?></div>
    </div>
    <p class="pay-route">Current payment route: <?=e($t['bank_name'])?> • UPI ID: <?=e($t['vpa'])?></p>
    <button type="button" class="pay-copy" id="copyVpa" data-vpa="<?=e($t['vpa'])?>">COPY UPI ID</button>
    <?php include __DIR__.'/_pay_qr.php'; ?>
    <form method="post" action="<?=e(url('/pay/'.$t['reference'].'/paid'))?>" style="margin-top:14px">
        <?=csrf_field()?>
        <label class="pay-lab">UTR / RRN from your UPI app
            <input name="utr" value="<?=e($t['customer_reported_utr']??'')?>" placeholder="UTR / RRN / UPI transaction reference" required>
        </label>
        <label class="pay-lab">Gmail ID
            <input type="email" name="email" value="<?=e($t['customer_reported_email']??'')?>" placeholder="Enter your Gmail ID" required maxlength="180" autocomplete="email">
        </label>
        <button class="pay-copy" style="margin-top:10px">Save UTR</button>
    </form>
    <p class="pay-more"><a href="<?=e(url('/p/'.$t['customer_code']))?>">Back to pay</a></p>
</div>
<script>
var copy=document.getElementById('copyVpa');
if(copy){copy.addEventListener('click',function(){var v=copy.getAttribute('data-vpa')||'';if(navigator.clipboard&&navigator.clipboard.writeText)navigator.clipboard.writeText(v);copy.textContent='COPIED';setTimeout(function(){copy.textContent='COPY UPI ID';},1600);});}
async function poll(){try{let r=await fetch(<?=json_encode(url('/pay/'.$t['reference'].'/status'))?>,{cache:'no-store'}),d=await r.json(),el=document.getElementById('status');el.textContent=d.status;}catch(e){}}
setInterval(poll,5000);poll();
</script>
<?php include __DIR__.'/_foot.php'; ?>
