<?php $invoiceHref = (string)($invoiceHref ?? ''); ?>
<div class="pay-share-box">
    <button type="button" class="pay-share-btn">Share</button>
    <div class="pay-share-menu" hidden>
        <button type="button" class="pay-share-wa" data-invoice="<?=e($invoiceHref)?>">WhatsApp</button>
    </div>
</div>
<?php if (empty($invoiceShareReady)): $invoiceShareReady = true; ?>
<style>
.pay-invoice-actions{display:flex;gap:8px;align-items:stretch;margin-top:16px}
.pay-invoice-actions .pay-invoice,.pay-invoice-actions .pay-go{flex:1;margin:0;width:auto}
.pay-share-box{position:relative;flex:0 0 118px;margin:0}
.pay-share-btn,.pay-share-wa{width:100%;border:0;border-radius:9px;font-weight:800;cursor:pointer;padding:14px}
.pay-share-btn{background:#2a2a2a;color:#fff;border:1px solid #555;height:100%}
.pay-share-menu{position:absolute;left:0;right:0;top:calc(100% + 6px);z-index:6}
.pay-share-wa{background:#25D366;color:#083018}
</style>
<script>
document.addEventListener('click', function(ev){
  var shareBtn = ev.target.closest && ev.target.closest('.pay-share-btn');
  if(shareBtn){
    var menu = shareBtn.parentElement.querySelector('.pay-share-menu');
    if(menu) menu.hidden = !menu.hidden;
    return;
  }
  var wa = ev.target.closest && ev.target.closest('.pay-share-wa');
  if(!wa) return;
  var href = wa.getAttribute('data-invoice') || '';
  var pdfUrl = new URL(href, location.href).href;
  var msg = 'Payment Success Invoice\n' + pdfUrl;
  window.open('https://wa.me/?text=' + encodeURIComponent(msg), '_blank', 'noopener');
});
</script>
<?php endif; ?>
