<?php include __DIR__.'/_head.php'; ?>
<div class="pay" style="max-width:440px;margin:40px auto">
  <div class="pay-who"><b><?=e($c['name'])?></b><div><?=e($c['code'])?> · <?=e($masked_phone??'')?></div></div>
  <h1 style="font-size:1.45rem">Customer Login</h1>
  <?php if(!empty($error)): ?><div class="error"><?=e($error)?></div><?php endif; ?>
  <?php if(empty($sent)): ?>
    <p class="muted">A 6-digit OTP will be sent to your registered mobile number.</p>
    <?php if(trim((string)($c['phone']??''))===''): ?><div class="error">Registered mobile number is missing. Update the customer mobile number in the database/admin panel first.</div><?php else: ?>
    <form method="post" action="<?=e(url('/c/'.$c['code'].'/request-otp'))?>"><?=csrf_field()?><button class="pay-go" type="submit">SEND OTP</button></form><?php endif; ?>
  <?php else: ?>
    <div class="ok">OTP sent to <?=e($masked_phone??'registered mobile')?>.</div>
    <?php if(!empty($dev_otp)): ?><div class="warn">DEV OTP: <b><?=e($dev_otp)?></b></div><?php endif; ?>
    <form method="post" action="<?=e(url('/c/'.$c['code'].'/verify-otp'))?>"><?=csrf_field()?><label>OTP<input name="otp" inputmode="numeric" pattern="\d{6}" maxlength="6" required style="width:100%;padding:14px;margin:8px 0 14px;border-radius:8px;border:1px solid #444;background:#222;color:#fff"></label><button class="pay-go" type="submit">VERIFY & LOGIN</button></form>
    <form method="post" action="<?=e(url('/c/'.$c['code'].'/request-otp'))?>" style="margin-top:10px"><?=csrf_field()?><button class="col-btn-gray" type="submit">Resend OTP</button></form>
  <?php endif; ?>
  <p class="pay-more"><a href="<?=e(url('/p/'.$c['code']))?>">Open public payment page</a></p>
</div>
<?php include __DIR__.'/_foot.php'; ?>
