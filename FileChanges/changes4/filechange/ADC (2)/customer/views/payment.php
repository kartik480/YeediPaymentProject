<?php include __DIR__.'/_head.php'; ?>
<?php
$form_action = url('/p/'.$c['code'].'/start');
$show_top_dashboard = false;
$presets = TransactionService::PUBLIC_PAY_AMOUNTS;
$hide_pay_extras = true;
$footer_link = null;
$pay_video_url = '';
$payVideos = array_merge(glob(YEEDIPAY_ROOT.'/video/*.mp4') ?: [], glob(YEEDIPAY_ROOT.'/video/*.MP4') ?: []);
if ($payVideos) {
    usort($payVideos, static fn(string $a, string $b): int => filemtime($b) <=> filemtime($a));
    $pay_video_url = url('/video/'.rawurlencode(basename($payVideos[0])));
}
?>
<div class="pay-stage">
<aside class="pay-steps" role="note">
    <h2 class="pay-side-title">Steps</h2>
    <span class="pay-steps-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="22" height="22"><path fill="#111" d="M3 10v4h3l5 4V6L6 10H3zm13.5 2a4.5 4.5 0 0 0-2.2-3.9v7.8A4.5 4.5 0 0 0 16.5 12zM14 4.2v2.1a7 7 0 0 1 0 11.4v2.1a9 9 0 0 0 0-15.6z"/></svg>
    </span>
    <span><b>Step 1:</b> Enter amount</span>
    <span><b>Step 2:</b> Generate QR</span>
    <span><b>Step 3:</b> Scan and pay</span>
    <span><b>Step 4:</b> Click I Have Paid</span>
</aside>
<div class="pay-stage-main">
<?php include __DIR__.'/_payment_start.php'; ?>
</div>
<aside class="pay-side">
<?php if ($pay_video_url !== ''): ?>
    <h2 class="pay-side-title">Video guideline</h2>
    <video class="pay-video" controls playsinline preload="metadata" src="<?=e($pay_video_url)?>"></video>
    <p class="pay-video-note">Make a direct payment and never take a screenshot. It does not support.</p>
<?php endif; ?>
</aside>
</div>
<style>
.pay-stage-main{min-width:0}
.pay-side-title{margin:0 0 4px;color:#fff;font-size:1.7rem;font-weight:800;line-height:1.2}
.pay-steps{position:fixed;left:max(24px,6vw);top:50%;transform:translateY(-50%);z-index:20;display:flex;flex-direction:column;align-items:flex-start;gap:16px;color:#fff;font-weight:700;font-size:1.35rem;line-height:1.35;max-width:280px}
.pay-steps-icon{width:40px;height:40px;border-radius:50%;background:#f5c518;display:inline-flex;align-items:center;justify-content:center}
.pay-side{position:fixed;right:max(24px,5vw);top:50%;transform:translateY(-50%);z-index:20;width:min(340px,26vw)}
.pay-side .pay-side-title{text-align:center;margin-bottom:12px}
.pay-video{display:block;width:100%;border-radius:12px;background:#000}
.pay-video-note{margin:14px 0 0;color:#fff;font-weight:700;font-size:1.2rem;line-height:1.45;text-align:center}
@media(max-width:1100px){
  .pay-steps,.pay-side{position:static;transform:none;width:auto;max-width:none;margin:0 0 18px}
  .pay-side{margin:18px 0 0}
}
</style>
<?php include __DIR__.'/_foot.php'; ?>
