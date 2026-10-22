<?php include __DIR__.'/_portal_head.php'; ?>
<?php if (!$pay): ?>
<section class="yp-sheet">
    <h1>Merchants</h1>
    <div class="yp-error"><?=e($error ?: 'CUST0003 was not found or the payment link is disabled.')?></div>
</section>
<?php else: ?>
<div class="yp-merchant-pay pay-dark">
    <div class="wrap">
<?php
$portalAccount = $c;
$seller_next = url('/c/'.$portalAccount['code'].'/qr');
if (!empty($print_receipt['reference'])) {
    $receiptRef = (string)$print_receipt['reference'];
    $receiptPdfUrl = url('/p/'.$pay['code'].'/result/'.$receiptRef.'/invoice.pdf');
    $receiptPrintUrl = url('/p/'.$pay['code'].'/result/'.$receiptRef.'/invoice.print');
    $receiptAuto = true;
    include __DIR__.'/_receipt_auto.php';
}
$c = $pay;
$embed = true;
$form_action = url('/c/'.$portalAccount['code'].'/qr');
$pay_ui = true;
include __DIR__.'/payment.php';
$c = $portalAccount;
?>
    </div>
</div>
<style>
.yp-merchant-pay{container-type:inline-size;width:100%;min-width:0}
.yp-merchant-pay .wrap{max-width:none;width:100%;margin:0;padding:0}
.yp-merchant-pay .pay-stage{
  display:grid;
  grid-template-columns:minmax(0,1fr) minmax(240px,400px) minmax(0,1.05fr);
  grid-template-areas:"steps form video";
  align-items:center;
  gap:18px 20px;
  width:100%;
  max-width:none;
  margin:0;
}
.yp-merchant-pay .pay-steps{grid-area:steps}
.yp-merchant-pay .pay-stage-main{grid-area:form;min-width:0}
.yp-merchant-pay .pay-side{grid-area:video}
.yp-merchant-pay .pay-steps,
.yp-merchant-pay .pay-side{
  position:static;
  inset:auto;
  transform:none;
  width:auto;
  max-width:none;
  margin:0;
  z-index:auto;
}
.yp-merchant-pay .pay{max-width:none;width:100%;margin:0}
.yp-merchant-pay .pay-steps{font-size:clamp(.92rem,1.7cqw,1.2rem);gap:12px;line-height:1.35}
.yp-merchant-pay .pay-side-title{font-size:clamp(1.15rem,2.2cqw,1.7rem)}
.yp-merchant-pay .pay-side .pay-side-title{text-align:center}
.yp-merchant-pay .pay-video{width:100%;height:auto;max-height:none;object-fit:contain}
.yp-merchant-pay .pay-video-note{font-size:clamp(.85rem,1.5cqw,1.05rem)}
.yp-merchant-pay .pay-steps span{overflow-wrap:anywhere}
@container (max-width: 820px){
  .yp-merchant-pay .pay-stage{
    grid-template-columns:minmax(0,1fr) minmax(0,1fr);
    grid-template-areas:"form form" "steps video";
    align-items:start;
  }
  .yp-merchant-pay .pay{max-width:430px;margin:0 auto}
}
@container (max-width: 560px){
  .yp-merchant-pay .pay-stage{
    grid-template-columns:minmax(0,1fr);
    grid-template-areas:"steps" "form" "video";
  }
  .yp-merchant-pay .pay,.yp-merchant-pay .pay-side,.yp-merchant-pay .pay-steps{max-width:none;width:100%}
  .yp-merchant-pay .pay-chips{grid-template-columns:repeat(2,minmax(0,1fr))}
  .yp-merchant-pay .pay-side .pay-side-title{text-align:left}
}
</style>
<?php endif; ?>
<?php include __DIR__.'/_portal_foot.php'; ?>
