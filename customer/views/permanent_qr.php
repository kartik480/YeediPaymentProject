<?php
include __DIR__.'/_head.php';
$acc = trim((string)($c['account_number'] ?? ''));
?>
<div class="card center">
    <h1><?=e($c['name'])?></h1>
    <p>Customer ID: <b><?=e($c['code'])?></b></p>
    <?php if ($acc !== ''): ?><p>Account number: <b class="mono"><?=e($acc)?></b></p><?php endif; ?>
    <div id="qr" class="qrbox" data-logo="<?=e(brand_logo_url())?>"></div>
    <p class="mono"><?=e($link)?></p>
    <button id="download">Download QR PNG</button>
</div>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script src="<?=e(asset_url('qr-brand.js'))?>"></script>
<script>
(function(){
  var box=document.getElementById('qr');
  if(box) yeediQr(box,<?=json_encode($link)?>,300);
  var btn=document.getElementById('download');
  if(btn) btn.onclick=function(){ yeediQrDownload(box,<?=json_encode($c['code'].'-payment-qr.png')?>); };
})();
</script>
<?php include __DIR__.'/_foot.php'; ?>
