<?php
/** Payment QR drawn from the bank UPI link, not the uploaded poster image. */
$__payQrT = $t ?? [];
$__qrSize = (int)($qrSize ?? 220);
?>
<div class="pay-qrwrap" style="margin:14px auto;width:fit-content;background:#fff;padding:12px;border-radius:16px"><div id="qr" class="qrbox" data-logo="<?=e(brand_logo_url())?>"></div></div>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script src="<?=e(asset_url('qr-brand.js'))?>"></script>
<script>
(function(){
  var box=document.getElementById('qr');
  if(box && window.yeediQr) yeediQr(box,<?=json_encode(TransactionService::scanUri($__payQrT))?>,<?=$__qrSize?>);
})();
</script>
