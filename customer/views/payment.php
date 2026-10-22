<?php if (empty($embed)) include __DIR__.'/_head.php'; ?>
<?php
if (empty($form_action)) $form_action = url('/p/'.$c['code'].'/start');
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
$publicPayPage = empty($embed);
?>
<?php if ($publicPayPage): ?><div class="pay-public-layout"><?php endif; ?>
<div class="pay-stage">
<aside class="pay-steps" role="note">
    <h2 class="pay-side-title">Steps</h2>
    <button type="button" class="pay-steps-icon" id="paySoundBtn" aria-pressed="false" aria-label="Play payment guide">
        <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="#fff" d="M3 10v4h3l5 4V6L6 10H3zm13.5 2a4.5 4.5 0 0 0-2.2-3.9v7.8A4.5 4.5 0 0 0 16.5 12zM14 4.2v2.1a7 7 0 0 1 0 11.4v2.1a9 9 0 0 0 0-15.6z"/></svg>
    </button>
    <span><b>Step 1:</b> Enter amount</span>
    <span><b>Step 2:</b> Generate QR</span>
    <span><b>Step 3:</b> Scan and pay</span>
    <span><b>Step 4:</b> Click I Have Paid</span>
</aside>
<div class="pay-stage-main">
<?php include __DIR__.'/_payment_start.php'; ?>
</div>
<?php if ($pay_video_url !== ''): ?>
<aside class="pay-side">
    <h2 class="pay-side-title">Video guideline</h2>
    <video class="pay-video" controls playsinline preload="metadata" src="<?=e($pay_video_url)?>"></video>
    <p class="pay-video-note">Make a direct payment and never take a screenshot. It does not support.</p>
</aside>
<?php endif; ?>
</div>
<?php if ($publicPayPage):
    $support_merchant_code = (string)($c['code'] ?? '');
    include __DIR__.'/_merchant_support.php';
?>
</div>
<?php endif; ?>
<?php if (!$publicPayPage): ?>
<style>
.pay-stage-main{min-width:0}
.pay-side-title{margin:0 0 4px;color:#171719;font-size:1.7rem;font-weight:800;line-height:1.2}
.pay-steps{position:fixed;left:max(24px,6vw);top:50%;transform:translateY(-50%);z-index:20;display:flex;flex-direction:column;align-items:flex-start;gap:16px;color:#171719;font-weight:700;font-size:1.35rem;line-height:1.35;max-width:280px}
.pay-steps-icon{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#F0442E,#FFA500);display:inline-flex;align-items:center;justify-content:center;border:0;padding:0;cursor:pointer}
.pay-steps-icon[aria-pressed="true"]{outline:3px solid #F0442E}
.pay-side{position:fixed;right:max(24px,4vw);top:50%;transform:translateY(-50%);z-index:20;width:min(360px,30vw)}
.pay-side .pay-side-title{text-align:center;margin-bottom:12px}
.pay-video{display:block;width:100%;margin:0;border-radius:12px;background:#000}
.pay-video-note{margin:14px 0 0;color:#171719;font-weight:700;font-size:1.05rem;line-height:1.45;text-align:center}
@media(max-width:1200px){
  body:not(.yp) .pay-stage{display:flex;flex-direction:column;gap:18px;max-width:430px;margin:0 auto}
  body:not(.yp) .pay-steps,body:not(.yp) .pay-side{position:static;top:auto;right:auto;bottom:auto;left:auto;transform:none;width:100%;max-width:none;margin:0;z-index:auto}
  .pay-steps{font-size:1.05rem;gap:10px}
  .pay-side-title{font-size:1.35rem}
  .pay-side .pay-side-title{text-align:left;margin-bottom:10px}
  .pay-video{width:100%;height:auto;max-height:240px;object-fit:contain}
  .pay-video-note{font-size:.95rem}
}
@media(max-width:640px){
  .pay-steps{font-size:.98rem}
  .pay-inr{font-size:1.35rem}
  .pay-title{font-size:1.2rem}
  .pay-chips{grid-template-columns:repeat(2,minmax(0,1fr))}
  .pay-invoice-actions{flex-direction:column}
  .pay-share-box{flex:1 1 auto;width:100%}
  .qrbox,.pay-qrwrap{max-width:100%}
  .qrbox canvas,.qrbox img:not(.qr-brand){width:100% !important;height:auto !important}
}
</style>
<?php endif; ?>
<script>
(function(){
  var btn=document.getElementById('paySoundBtn');
  if(!btn) return;
  var video=document.querySelector('.pay-video');
  function setPlaying(on){
    btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    btn.setAttribute('aria-label', on ? 'Stop payment guide' : 'Play payment guide');
  }
  if(video){
    video.addEventListener('play', function(){ setPlaying(true); });
    video.addEventListener('pause', function(){ setPlaying(false); });
    video.addEventListener('ended', function(){ setPlaying(false); });
    btn.addEventListener('click', function(){
      if(!video.paused && !video.ended){ video.pause(); return; }
      video.muted=false;
      video.volume=1;
      var play=video.play();
      if(play && play.catch) play.catch(function(){ setPlaying(false); });
    });
    return;
  }
  var playing=false;
  var steps='Step 1: Enter amount. Step 2: Generate QR. Step 3: Scan and pay. Step 4: Click I Have Paid.';
  btn.addEventListener('click', function(){
    if(playing){
      playing=false;
      if(window.speechSynthesis) speechSynthesis.cancel();
      setPlaying(false);
      return;
    }
    playing=true;
    setPlaying(true);
    if(!window.speechSynthesis){ playing=false; setPlaying(false); return; }
    var voice=new SpeechSynthesisUtterance(steps);
    voice.lang='en-IN';
    voice.rate=0.95;
    voice.onend=function(){ playing=false; setPlaying(false); };
    voice.onerror=function(){ playing=false; setPlaying(false); };
    speechSynthesis.cancel();
    speechSynthesis.speak(voice);
  });
})();
</script>
<?php if (empty($embed)) include __DIR__.'/_foot.php'; ?>
