<?php include __DIR__.'/_portal_head.php'; ?>
<?php if (!$pay): ?>
<section class="yp-sheet">
    <h1>Merchants</h1>
    <div class="yp-error"><?=e($error ?: 'The payment account was not found or the payment link is disabled.')?></div>
</section>
<?php else: ?>
<div class="yp-merchant-pay pay-dark">
    <div class="yp-merchant-layout">
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
</div>
<style>
/* Merchant page /c/{code}/qr — paste-friendly layout (Steps | Payment | Video, then Support full width) */
.yp-merchant-pay{width:100%;min-width:0;background:transparent!important}
.yp-merchant-layout{
  display:flex;
  flex-direction:column;
  align-items:stretch;
  width:100%;
  max-width:1280px;
  margin:0 auto;
  padding:0 8px 16px;
  gap:0;
}
.yp-merchant-pay .wrap{max-width:none;width:100%;margin:0;padding:0}
.yp-merchant-pay .pay-stage{
  display:grid!important;
  width:100%;
  margin:0 auto;
  padding:8px 4px 12px;
  gap:20px 28px;
  align-items:center;
  grid-template-columns:minmax(140px,1fr) minmax(280px,400px) minmax(180px,1fr);
  grid-template-areas:"steps form video";
}
.yp-merchant-pay .pay-steps{
  grid-area:steps;
  position:static!important;
  inset:auto!important;
  transform:none!important;
  z-index:auto!important;
  width:auto!important;
  max-width:320px;
  margin:0;
  display:grid;
  grid-template-columns:1fr auto;
  align-content:start;
  gap:10px 12px;
  font-size:clamp(.92rem,1.05vw,1.15rem);
  font-weight:700;
  line-height:1.35;
  color:#171719;
}
.yp-merchant-pay .pay-steps .pay-side-title{
  grid-column:1;
  margin:0;
  font-size:clamp(1.15rem,1.8vw,1.65rem);
  font-weight:800;
  line-height:1.2;
}
.yp-merchant-pay .pay-steps .pay-steps-icon{grid-column:2;grid-row:1;align-self:start;margin:0}
.yp-merchant-pay .pay-steps>span{grid-column:1/-1;overflow-wrap:anywhere}
.yp-merchant-pay .pay-stage-main{
  grid-area:form;
  min-width:0;
  width:100%;
  justify-self:center;
  max-width:400px;
}
.yp-merchant-pay .pay{max-width:400px;width:100%;margin:0 auto}
.yp-merchant-pay .pay-side{
  grid-area:video;
  position:static!important;
  inset:auto!important;
  transform:none!important;
  z-index:auto!important;
  width:100%!important;
  max-width:380px;
  margin:0;
  justify-self:center;
}
.yp-merchant-pay .pay-side .pay-side-title{
  text-align:center;
  margin:0 0 10px;
  font-size:clamp(1.1rem,1.6vw,1.55rem);
  font-weight:800;
  color:#171719;
}
.yp-merchant-pay .pay-video{
  width:100%;
  height:auto;
  max-height:min(42vh,320px);
  object-fit:contain;
  border-radius:12px;
  background:#000;
}
.yp-merchant-pay .pay-video-note{
  margin:12px 0 0;
  font-size:clamp(.82rem,1.2vw,.98rem);
  font-weight:700;
  line-height:1.45;
  text-align:center;
  color:#171719;
}
.yp-merchant-pay .pay-chips{grid-template-columns:repeat(4,minmax(0,1fr))}
@media (max-width:1100px){
  .yp-merchant-pay .pay-stage{
    grid-template-columns:minmax(0,1fr) minmax(0,1fr);
    grid-template-areas:"form form" "steps video";
    align-items:start;
  }
  .yp-merchant-pay .pay-stage-main{max-width:430px;grid-column:1/-1;justify-self:center}
  .yp-merchant-pay .pay-steps{max-width:none}
  .yp-merchant-pay .pay-side{max-width:none}
  .yp-merchant-pay .pay-side .pay-side-title{text-align:left}
}
@media (max-width:720px){
  .yp-merchant-pay .pay-stage{
    grid-template-columns:minmax(0,1fr);
    grid-template-areas:"form" "steps" "video";
  }
  .yp-merchant-pay .pay-stage-main,.yp-merchant-pay .pay{max-width:none}
  .yp-merchant-pay .pay-chips{grid-template-columns:repeat(2,minmax(0,1fr))}
  .yp-merchant-pay .pay-video{max-height:240px}
}
@media (max-width:480px){
  .yp-merchant-layout{padding:0 4px 12px}
  .yp-merchant-pay .pay{padding:14px 12px}
}
</style>
<?php endif; ?>
<?php include __DIR__.'/_portal_foot.php'; ?>
