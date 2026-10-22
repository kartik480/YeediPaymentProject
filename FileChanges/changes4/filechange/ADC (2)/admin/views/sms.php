<?php
$demoCfg = require YEEDIPAY_ROOT.'/sms/config.php';
$demoKey = (string)($demoCfg['token'] ?? '');
include __DIR__.'/_head.php';
$smsReady = (int)($s['enabled'] ?? 0) === 1 && !empty($s['endpoint_url']);
?>
<h1>SMS Gateway</h1>
<div class="card">
    <h2>smslogin.co (live)</h2>
    <p>GET uses username, apikey, senderid <b>JLKCBK</b>, mobile as 91XXXXXXXXXX, urlencode(message), and the approved DLT template ID.</p>
    <p>Payment SUCCESS uses template <b>transaction confirmation</b> <span class="mono">1707174530074657515</span>, sender <b>JLKCBK</b>. OTP uses <b>otp verification</b> <span class="mono">1707173641664963524</span>. Message text is the exact approved DLT wording.</p>
    <form method="post" action="<?=e(url('/admin/sms/smslogin'))?>"><?=csrf_field()?>
        <button>Enable smslogin.co gateway</button>
    </form>
    <p class="muted" style="margin-top:10px">Status: <b><?=$smsReady ? e((string)$s['gateway_name']).' · '.$s['http_method'].' · '.$s['content_type'] : 'NOT CONFIGURED'?></b></p>
</div>
<div class="card">
    <h2>Delivery report</h2>
    <p class="muted">Paste the campaign ID returned when the SMS was accepted. A report like <span class="mono">9398907446-Failed</span> means smslogin.co accepted the API call, but the operator did <b>not deliver</b> to that number (DLT / template / DND / credits).</p>
    <form method="post" action="<?=e(url('/admin/sms/dlr'))?>" class="row"><?=csrf_field()?>
        <input class="grow" name="campid" placeholder="Campaign ID" required>
        <button>Fetch report</button>
    </form>
</div>
<div class="card">
    <h2>YEEDIPAY Demo SMS Gateway</h2>
    <p>This does <b>not</b> send a real SMS to a phone. It logs OTP / PENDING / SUCCESS / FAILED / EXPIRED messages so you can test the full flow.</p>
    <form method="post" action="<?=e(url('/admin/sms/demo'))?>"><?=csrf_field()?>
        <button class="btn2">Enable Demo SMS Gateway</button>
    </form>
    <p class="muted" style="margin-top:12px">Inbox: <a href="<?=e(sms_demo_url('/inbox.php?key='.$demoKey))?>" target="_blank" rel="noopener"><?=e(sms_demo_url('/inbox.php?key='.$demoKey))?></a></p>
</div>
<div class="card">
    <form method="post"><?=csrf_field()?>
        <div class="grid">
            <label><input type="checkbox" name="enabled" value="1" <?= (int)$s['enabled'] ? 'checked' : '' ?>> Enabled</label>
            <input name="gateway_name" value="<?=e($s['gateway_name'])?>" placeholder="Gateway name">
            <input name="endpoint_url" value="<?=e($s['endpoint_url'])?>" placeholder="https://sms-provider/api">
            <select name="http_method">
                <option <?= $s['http_method'] === 'POST' ? 'selected' : '' ?>>POST</option>
                <option <?= $s['http_method'] === 'GET' ? 'selected' : '' ?>>GET</option>
            </select>
            <select name="content_type"><?php foreach (['json', 'form', 'query'] as $x): ?><option <?= $s['content_type'] === $x ? 'selected' : '' ?>><?=$x?></option><?php endforeach; ?></select>
            <input name="auth_header_name" value="<?=e($s['auth_header_name'])?>" placeholder="Auth header (blank for query apikey)">
            <input name="auth_prefix" value="<?=e($s['auth_prefix'])?>" placeholder="Auth prefix">
            <input type="password" name="auth_token" placeholder="Leave blank to keep current API key">
            <input name="sender_id" value="<?=e($s['sender_id'])?>" placeholder="Sender ID">
        </div>
        <p><textarea name="request_template" rows="4" style="width:100%"><?=e($s['request_template'])?></textarea></p>
        <div class="grid">
            <input name="otp_template_id" value="<?=e($s['otp_template_id'])?>" placeholder="OTP DLT template ID">
            <input name="pending_template_id" value="<?=e($s['pending_template_id'])?>" placeholder="Pending template ID">
            <input name="success_template_id" value="<?=e($s['success_template_id'])?>" placeholder="Success template ID">
            <input name="failed_template_id" value="<?=e($s['failed_template_id'])?>" placeholder="Failed template ID">
            <input name="expired_template_id" value="<?=e($s['expired_template_id'])?>" placeholder="Expired template ID">
        </div>
        <?php foreach (['otp_message', 'pending_message', 'success_message', 'failed_message', 'expired_message'] as $f): ?>
        <p><label><?=e($f)?><textarea name="<?=e($f)?>" rows="2" style="width:100%"><?=e($s[$f])?></textarea></label></p>
        <?php endforeach; ?>
        <button>Save SMS Gateway</button>
    </form>
</div>
<div class="card">
    <h2>Send Test SMS</h2>
    <form method="post" action="<?=e(url('/admin/sms/test'))?>" class="row"><?=csrf_field()?>
        <input name="phone" placeholder="Mobile 10-digit or 91..." required>
        <input class="grow" name="message" value="<?=e(SmsService::sampleSuccessSms())?>" required>
        <button>Send Test</button>
    </form>
    <p class="muted">Test text must match the creditTxn DLT template. Do not send Hello World — that is rejected.</p>
</div>
<div class="card">
    <h2>Recent SMS (outbox)</h2>
    <?php $outbox = $outbox ?? []; ?>
    <?php if (!$outbox): ?>
    <p class="muted">No SMS queued yet.</p>
    <?php else: ?>
    <table>
        <tr><th>ID</th><th>Event</th><th>Mobile</th><th>Status</th><th>Error</th><th>Time</th></tr>
        <?php foreach ($outbox as $row): ?>
        <tr>
            <td><?=e((string)$row['id'])?></td>
            <td class="mono"><?=e((string)$row['event_key'])?></td>
            <td class="mono"><?=e((string)$row['phone'])?></td>
            <td><?=e((string)$row['status'])?></td>
            <td><?=e((string)($row['last_error'] ?? ''))?></td>
            <td class="mono"><?=e((string)($row['sent_at'] ?: $row['created_at']))?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>
<?php include __DIR__.'/_foot.php'; ?>
