<?php
include __DIR__.'/_head.php';
$edit = $edit ?? null;
$isEdit = is_array($edit);
$action = $isEdit ? url('/admin/banks/'.$edit['id'].'/save') : url('/admin/banks');
$val = function (string $key, $default = '') use ($edit, $isEdit) {
    if (!$isEdit) return (string)$default;
    return (string)($edit[$key] ?? $default);
};
$mcc = $val('mcc');
if ($mcc === '' && $isEdit && !empty($edit['qr_params_json'])) {
    $params = json_decode((string)$edit['qr_params_json'], true) ?: [];
    $mcc = (string)($params['mc'] ?? '');
}
$routingMode = !empty($cfg['rotation_enabled']) ? 'AUTO' : 'MANUAL';
$manualId = (int)($cfg['manual_active_bank_id'] ?? ($active['id'] ?? 0));
$enabledChecked = !$isEdit || (int)($edit['enabled'] ?? 1) === 1;
$rot = $rot ?? [];
$minsLeft = (int)ceil(($rot['seconds_left'] ?? 0) / 60);
?>
<h1>Banks &amp; Routing</h1>
<?php if (!empty($error)): ?><div class="error"><?=e($error)?></div><?php endif; ?>
<?php if (!empty($rot['on'])): ?>
<div class="ok">Hourly auto-switch is ON. Live QR uses <b><?=e((string)($rot['active']['bank_name'] ?? ''))?></b><?php if (!empty($rot['next'])): ?> · next <b><?=e((string)$rot['next']['bank_name'])?></b> in about <b><?=e((string)max(1, $minsLeft))?> min</b><?php endif; ?>. Customer payment links stay the same.</div>
<?php endif; ?>

<div class="br-panel">
    <h2>Banks / UPI Routing<?=$isEdit ? ' — Edit '.e($edit['bank_name']) : ''?></h2>
    <form method="post" action="<?=e($action)?>" id="bankForm" enctype="multipart/form-data" autocomplete="off">
        <?=csrf_field()?>
        <p class="br-note">ICICI Eazypay UPI <span class="mono"><?=e(BankService::ICICI_EAZYPAY_VPA)?></span> — standees: <?php foreach (BankService::iciciEazypayStandeeDefinitions() as $i => $d): ?><?= $i ? ' · ' : '' ?><button type="button" class="btn2 icici-standee-preset" data-code="<?=e($d['code'])?>" data-name="<?=e($d['bank_name'])?>" data-pn="<?=e($d['payee_name'])?>" data-vpa="<?=e(BankService::ICICI_EAZYPAY_VPA)?>">Fill <?=e($d['bank_name'])?></button><?php endforeach; ?> (upload each QR image after filling)</p>
        <div class="br-grid br-2">
            <label class="br-req">Bank Code*
                <input name="code" id="bank_code" value="<?=e($val('code'))?>" placeholder="INDUS / ICICI / RBL" required>
            </label>
            <label class="br-req">Bank Name*
                <input name="bank_name" value="<?=e($val('bank_name'))?>" placeholder="Bank name" required>
            </label>
        </div>
        <label>Paste original UPI URI (optional; pa/pn/mc will be read)
            <input name="upi_uri" id="upi_uri" value="<?=e($isEdit ? (string)$edit['qr_payload'] : '')?>" placeholder="upi://pay?pa=...&amp;pn=...">
        </label>
        <div class="br-grid br-2">
            <label>Static Bank QR Image
                <input type="file" name="qr_image" id="bank_qr" accept="image/png,image/jpeg,image/webp,image/gif">
            </label>
            <div>
                <p class="br-note" id="qrDecodeStatus" style="margin:8px 0 0">Standee photo is for your records and to read UPI (pa/pn/mc). Customer pay pages generate a <b>new QR per payment</b> with amount + Payment ID from the UPI URI / VPA below — not this poster image.</p>
                <?php if ($isEdit && !empty($edit['qr_image_path'])): ?>
                <p class="br-note" style="margin-top:8px">Current QR: <a href="<?=e(url('/admin/banks/'.$edit['id'].'/qr'))?>" target="_blank" rel="noopener">View</a></p>
                <p><img src="<?=e(url('/admin/banks/'.$edit['id'].'/qr'))?>" alt="Bank QR" style="max-width:180px;background:#fff;padding:8px;border-radius:8px"></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="br-grid br-3">
            <label class="br-req">UPI ID / VPA*
                <input name="vpa" id="bank_vpa" value="<?=e($val('vpa'))?>" placeholder="merchant@bank" required>
            </label>
            <label class="br-req">Payee Name*
                <input name="payee_name" id="bank_pn" value="<?=e($val('payee_name'))?>" required>
            </label>
            <label>MCC (bank-issued only)
                <input name="mcc" id="bank_mcc" value="<?=e($mcc)?>">
            </label>
        </div>
        <div class="br-grid br-3">
            <label>Rotation Order
                <input type="number" name="rotation_order" min="1" value="<?=e($val('rotation_order', '1'))?>">
            </label>
            <label>Active Minutes
                <input type="number" name="rotation_minutes" min="1" value="<?=e($val('rotation_minutes', '60'))?>">
            </label>
            <label class="br-check">Enabled
                <input type="checkbox" name="enabled" value="1" <?=$enabledChecked ? 'checked' : ''?>>
            </label>
        </div>
        <p class="br-note">RED fields must be replaced/configured by the operator for each live bank. Customer permanent links never change when the active bank changes.</p>
        <hr class="br-hr">
        <p class="br-note"><b>Collection confirmation:</b> only the authenticated Android Bank SMS Relay can mark a payment SUCCESS. Provider API fields below may be used for QR/order connectivity only; webhook/status responses do not confirm this collection flow.</p>
        <div class="br-grid br-2">
            <label>Provider name
                <input name="provider_name" value="<?=e($val('provider_name'))?>" placeholder="Provider name">
            </label>
            <label>Provider Base URL
                <input name="provider_base_url" value="<?=e($val('provider_base_url'))?>" placeholder="Provider Base URL">
            </label>
            <label>Create Order Path
                <input name="create_order_path" value="<?=e($val('create_order_path'))?>">
            </label>
            <label>Status Path
                <input name="status_path" value="<?=e($val('status_path'))?>">
            </label>
        </div>
        <label>Refund Path
            <input name="refund_path" value="<?=e($val('refund_path'))?>">
        </label>
        <div class="br-grid br-3">
            <label>API Key (blank = keep old)
                <input type="password" name="api_key" autocomplete="new-password">
            </label>
            <label>API Secret (blank = keep old)
                <input type="password" name="api_secret" autocomplete="new-password">
            </label>
            <label>Webhook Secret (blank = keep old)
                <input type="password" name="webhook_secret" autocomplete="new-password">
            </label>
        </div>
        <p class="br-note" id="bankSaveHint" style="margin-bottom:8px">Required to save: Bank Code, Bank Name, UPI ID, Payee Name (scroll up if the browser asks you to fill a field).</p>
        <div class="br-actions">
            <button type="submit" class="br-save" id="bankSaveBtn">SAVE BANK</button>
            <a class="br-clear" href="<?=e(url('/admin/banks'))?>">Clear Form</a>
        </div>
    </form>
</div>

<div class="br-panel">
    <form method="post" action="<?=e(url('/admin/routing'))?>" class="br-route">
        <?=csrf_field()?>
        <label>Routing Mode
            <select name="routing_mode">
                <option value="MANUAL" <?=$routingMode==='MANUAL'?'selected':''?>>MANUAL</option>
                <option value="AUTO" <?=$routingMode==='AUTO'?'selected':''?>>AUTO</option>
            </select>
        </label>
        <label class="grow">Manual Active Bank
            <select name="manual_active_bank_id">
                <option value="">Select bank</option>
                <?php foreach ($banks as $b): if (!(int)$b['enabled']) continue; ?>
                    <option value="<?=e($b['id'])?>" <?=$manualId===(int)$b['id']?'selected':''?>><?=e($b['bank_name'])?> — <?=e($b['vpa'])?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="br-save">APPLY ROUTING</button>
    </form>
    <p class="br-note">AUTO = hourly switch. MANUAL / <b>Make Active</b> = stay on that bank until you change it.</p>
    <div class="scroll">
        <table class="br-table">
            <tr>
                <th>Active</th>
                <th>Bank</th>
                <th>VPA</th>
                <th>Order</th>
                <th>Minutes</th>
                <th>QR</th>
                <th>Provider Link</th>
                <th>Actions</th>
            </tr>
            <?php foreach ($banks as $b): if (!(int)$b['enabled']) continue; $isActive = $active && (int)$active['id'] === (int)$b['id']; ?>
            <tr>
                <td><?php if ($isActive): ?><span class="br-active">• ACTIVE</span><?php else: ?><span class="muted"><?=((int)$b['enabled']?'Ready':'Off')?></span><?php endif; ?></td>
                <td><?=e($b['bank_name'])?><div class="br-code"><?=e($b['code'])?></div></td>
                <td class="mono"><?=e($b['vpa'])?></td>
                <td><?=e($b['rotation_order'])?></td>
                <td><?=e($b['rotation_minutes'])?></td>
                <td>
                    <?php if (!empty($b['qr_image_path'])): ?>
                    <a href="<?=e(url('/admin/banks/'.$b['id'].'/qr'))?>" target="_blank" rel="noopener">View</a>
                    <?php else: ?>
                    <span class="muted">—</span>
                    <?php endif; ?>
                </td>
                <td class="br-api"><?=e(BankService::statusApiLabel($b))?></td>
                <td>
                    <div class="row">
                        <a class="btn btn2" href="<?=e(url('/admin/banks?edit='.$b['id']))?>">Edit</a>
                        <?php if (!$isActive): ?>
                        <form method="post" action="<?=e(url('/admin/banks/'.$b['id'].'/activate'))?>"><?=csrf_field()?><button type="submit">Make Active</button></form>
                        <?php endif; ?>
                        <?php if (!$isActive): ?>
                        <form method="post" action="<?=e(url('/admin/banks/'.$b['id'].'/remove'))?>" onsubmit="return confirm('Remove <?=e(addslashes($b['bank_name']))?> (<?=e($b['code'])?>) from routing?');"><?=csrf_field()?><button type="submit">Remove</button></form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$banks): ?>
            <tr><td colspan="8" class="muted">No banks yet. Save a bank above.</td></tr>
            <?php endif; ?>
        </table>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
<script src="<?=e(asset_url('qr-upload.js'))?>"></script>
<script>
(function(){
  var ICICI_VPA='<?=e(BankService::ICICI_EAZYPAY_VPA)?>';
  var uri=document.getElementById('upi_uri');
  var vpaEl=document.getElementById('bank_vpa');
  var pnEl=document.getElementById('bank_pn');
  var mccEl=document.getElementById('bank_mcc');
  var form=document.getElementById('bankForm');
  var status=document.getElementById('qrDecodeStatus');
  var saveHint=document.getElementById('bankSaveHint');
  var saveBtn=document.getElementById('bankSaveBtn');
  if(!uri) return;

  function syncUriBeforeSave(){
    try{
      var v=(vpaEl&&vpaEl.value||'').trim();
      var n=(pnEl&&pnEl.value||'').trim();
      var m=(mccEl&&mccEl.value||'').trim();
      var p=parseUpiRaw(uri.value);
      if(p&&p.pa){
        if(vpaEl) vpaEl.value=p.pa;
        if(p.pn&&pnEl) pnEl.value=p.pn;
        if(p.mc&&mccEl) mccEl.value=p.mc;
        v=(vpaEl&&vpaEl.value||'').trim();
        n=(pnEl&&pnEl.value||'').trim();
        m=(mccEl&&mccEl.value||'').trim()||p.mc||'';
      }
      if(v.indexOf('@')>=0&&n) uri.value=buildUpiUri(v,n,m);
      else if(!uri.value.trim()&&v.indexOf('@')>=0&&n) uri.value=buildUpiUri(v,n,m);
    }catch(err){}
  }

  function showInvalidFields(){
    if(!form) return false;
    if(form.checkValidity()) return true;
    var first=form.querySelector(':invalid');
    if(first){
      first.scrollIntoView({behavior:'smooth',block:'center'});
      try{ first.focus({preventScroll:true}); }catch(e){ first.focus(); }
      if(typeof first.reportValidity==='function') first.reportValidity();
    }
    var msg='Fill all required fields: Bank Code, Bank Name, UPI ID, and Payee Name (see top of this form).';
    if(saveHint) saveHint.textContent=msg;
    if(status) status.textContent=msg;
    return false;
  }

  function decodeParam(v){ try{ return decodeURIComponent(String(v||'').replace(/\+/g,' ')); }catch(e){ return String(v||''); } }

  function parseUpiRaw(raw){
    raw=(raw||'').trim();
    if(!raw) return null;
    if(/^[^\s?&]+@[^\s&]+$/i.test(raw) && raw.indexOf('upi:')!==0){
      return { pa: raw, pn: null, mc: null };
    }
    var m=raw.match(/^upi:\/\/pay\?(.*)$/i);
    if(m){
      var out={ pa:null, pn:null, mc:null };
      m[1].split('&').forEach(function(pair){
        var i=pair.indexOf('='); if(i<0) return;
        var k=decodeParam(pair.slice(0,i)).toLowerCase();
        var val=decodeParam(pair.slice(i+1));
        if(k==='pa') out.pa=val;
        if(k==='pn') out.pn=val;
        if(k==='mc') out.mc=val;
      });
      if(out.pa) return out;
    }
    try{
      var u=new URL(raw);
      if(u.protocol!=='upi:') return null;
      return {
        pa: u.searchParams.get('pa'),
        pn: u.searchParams.get('pn'),
        mc: u.searchParams.get('mc')
      };
    }catch(e){ return null; }
  }

  function buildUpiUri(vpa, pn, mcc){
    var parts=['pa='+encodeURIComponent(vpa),'pn='+encodeURIComponent(pn||vpa),'cu=INR'];
    if(mcc) parts.push('mc='+encodeURIComponent(mcc));
    return 'upi://pay?'+parts.join('&');
  }

  function uriFromFields(){
    var v=(vpaEl&&vpaEl.value||'').trim();
    var n=(pnEl&&pnEl.value||'').trim();
    var m=(mccEl&&mccEl.value||'').trim();
    if(v.indexOf('@')<0 || !n) return '';
    return buildUpiUri(v,n,m);
  }

  function applyParsed(p, opts){
    if(!p) return;
    opts=opts||{};
    if(p.pa && vpaEl) vpaEl.value=p.pa;
    if(p.pn && pnEl) pnEl.value=p.pn;
    if(p.mc && mccEl) mccEl.value=p.mc;
    if(opts.rebuildUri){
      var v=(vpaEl&&vpaEl.value||'').trim();
      var n=(pnEl&&pnEl.value||'').trim();
      var m=(mccEl&&mccEl.value||'').trim();
      if(v.indexOf('@')>=0 && n){
        uri.value=buildUpiUri(v,n,m||p.mc||'');
        if(status) status.textContent='UPI URI set for '+n+' ('+v+'). Save bank, then Make Active if needed.';
      }
    }else if(p.pa && !p.pn && pnEl && pnEl.value.trim()){
      uri.value=buildUpiUri(p.pa, pnEl.value.trim(), (mccEl&&mccEl.value)||'');
    }
  }

  function syncFromUri(){
    var p=parseUpiRaw(uri.value);
    if(p) applyParsed(p,{ rebuildUri: !!(p.pa && !String(uri.value||'').match(/^upi:\/\//i) });
  }

  uri.addEventListener('change', syncFromUri);
  uri.addEventListener('paste', function(){ setTimeout(syncFromUri, 0); });
  uri.addEventListener('input', syncFromUri);
  if(vpaEl) vpaEl.addEventListener('change', function(){
    var built=uriFromFields(); if(built) uri.value=built;
  });
  if(pnEl) pnEl.addEventListener('change', function(){
    var built=uriFromFields(); if(built) uri.value=built;
  });
  if(mccEl) mccEl.addEventListener('change', function(){
    var built=uriFromFields(); if(built) uri.value=built;
  });
  if(saveBtn) saveBtn.addEventListener('click', function(){
    syncUriBeforeSave();
    showInvalidFields();
  });
  if(form) form.addEventListener('submit', function(e){
    syncUriBeforeSave();
    if(!form.checkValidity()){
      e.preventDefault();
      showInvalidFields();
    }
  });
  syncFromUri();
  if(!(uri.value||'').trim() && vpaEl && pnEl && vpaEl.value.indexOf('@')>=0 && pnEl.value.trim()){
    uri.value=uriFromFields();
  }
})();

(function(){
  var vpa='<?=e(BankService::ICICI_EAZYPAY_VPA)?>';
  var status=document.getElementById('qrDecodeStatus');
  document.querySelectorAll('.icici-standee-preset').forEach(function(btn){
    btn.addEventListener('click', function(){
      var code=btn.getAttribute('data-code')||'';
      var name=btn.getAttribute('data-name')||'';
      var pn=btn.getAttribute('data-pn')||name;
      var uriEl=document.getElementById('upi_uri');
      var codeEl=document.getElementById('bank_code');
      var vpaEl=document.getElementById('bank_vpa');
      var pnEl=document.getElementById('bank_pn');
      var bankName=document.querySelector('input[name="bank_name"]');
      var upi='upi://pay?pa='+encodeURIComponent(vpa)+'&pn='+encodeURIComponent(pn)+'&cu=INR';
      if(codeEl) codeEl.value=code;
      if(bankName) bankName.value=name;
      if(vpaEl) vpaEl.value=vpa;
      if(pnEl) pnEl.value=pn;
      if(uriEl){ uriEl.value=upi; uriEl.dispatchEvent(new Event('change',{bubbles:true})); }
      if(status) status.textContent='Filled ICICI VPA '+vpa+' and UPI URI for '+pn+'. Upload standee QR (optional), then SAVE BANK.';
    });
  });
})();
</script>
<?php include __DIR__.'/_foot.php'; ?>
