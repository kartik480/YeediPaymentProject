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
        <div class="br-grid br-2">
            <label class="br-req">Bank Code*
                <input name="code" value="<?=e($val('code'))?>" placeholder="INDUS / ICICI / RBL" required>
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
                <p class="br-note" id="qrDecodeStatus" style="margin:8px 0 0">Upload bank QR to decode UPI and store the image.</p>
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
        <div class="br-actions">
            <button type="submit" class="br-save">SAVE BANK</button>
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
                <?php foreach ($banks as $b): ?>
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
            <?php foreach ($banks as $b): $isActive = $active && (int)$active['id'] === (int)$b['id']; ?>
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
  var uri=document.getElementById('upi_uri');
  if(!uri) return;
  function fill(){
    var raw=(uri.value||'').trim();
    if(!raw) return;
    try{
      var u=new URL(raw);
      if(u.protocol!=='upi:') return;
      var pa=u.searchParams.get('pa'), pn=u.searchParams.get('pn'), mc=u.searchParams.get('mc');
      var vpa=document.getElementById('bank_vpa'), name=document.getElementById('bank_pn'), mcc=document.getElementById('bank_mcc');
      if(pa && vpa) vpa.value=pa;
      if(pn && name) name.value=pn;
      if(mc && mcc) mcc.value=mc;
    }catch(e){}
  }
  uri.addEventListener('change', fill);
  uri.addEventListener('paste', function(){ setTimeout(fill, 0); });
  uri.addEventListener('input', fill);
})();
</script>
<?php include __DIR__.'/_foot.php'; ?>
