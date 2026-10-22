<?php include __DIR__.'/_head.php'; ?>
<div class="card">
    <h1>Bank credit SMS</h1>
    <p class="muted">The Android phone reads the bank UPI CREDIT SMS and posts it to this site. SUCCESS requires the registered receiving bank/account last-4, the exact amount, a valid unique bank UTR/RRN, and one acknowledged pending payment in the matching time window. Ambiguous matches remain under review; no manual success route is available.</p>
    <p class="muted">Phone endpoint: <span class="mono">https://customer.yeedipay.in/api/bank-relay/webhook_bank_sms.php</span></p>
    <p class="muted">The app sends a bank credit only to an https address. Paste that URL, the Device ID, and the secret. Enter the actual receiving account last 4 digits for each bank. Last seen stays empty until the phone reaches this server. A message whose sender is not ICICI, IndusInd, or RBL stays on the phone and is not posted.</p>
</div>

<?php if (!empty($newDevice)): ?>
<div class="ok">
    <b>Device ID:</b> <span class="mono"><?=e($newDevice['device_id'])?></span><br>
    <b>HMAC secret (shown once):</b> <span class="mono"><?=e($newDevice['secret'])?></span>
</div>
<?php endif; ?>

<div class="card">
    <h2>Register parser device</h2>
    <form method="post" action="<?=e(url('/admin/bank-sms/devices'))?>" class="row">
        <?=csrf_field()?>
        <input name="name" required maxlength="120" placeholder="Phone name">
        <select name="bank_code" required>
            <option value="">Bank</option>
            <?php foreach ($banks as $b): ?>
            <option value="<?=e($b['code'])?>"><?=e($b['bank_name'])?> (<?=e($b['code'])?>)</option>
            <?php endforeach; ?>
        </select>
        <input name="account_last4" required pattern="\d{4}" maxlength="4" placeholder="A/c last 4">
        <button>Create device</button>
    </form>
    <?php if (!$banks): ?><p class="error">Add and enable a bank before registering a parser.</p><?php endif; ?>
    <table style="width:100%;border-collapse:collapse;margin-top:14px">
        <tr><th align="left">Name</th><th align="left">Device ID</th><th align="left">Accounts</th><th align="left">Last seen</th><th align="left">Health</th><th align="left">Active</th><th></th></tr>
        <?php foreach ($devices as $d): ?>
        <tr>
            <td><?=e($d['name'])?></td>
            <td class="mono"><?=e($d['device_id'])?></td>
            <td>
                <?php foreach (($d['accounts'] ?? []) as $account): ?>
                <div><?=e($account['bank_code'])?> · <?=e($account['account_last4'])?></div>
                <?php endforeach; ?>
                <form method="post" action="<?=e(url('/admin/bank-sms/devices/'.$d['id'].'/accounts'))?>" class="row" style="margin-top:8px">
                    <?=csrf_field()?>
                    <select name="bank_code" required>
                        <option value="">Add bank</option>
                        <?php foreach ($banks as $b): ?>
                        <option value="<?=e($b['code'])?>"><?=e($b['bank_name'])?></option>
                        <?php endforeach; ?>
                    </select>
                    <input name="account_last4" required pattern="\d{4}" maxlength="4" placeholder="Last 4">
                    <button class="btn2">Add</button>
                </form>
            </td>
            <td><?=e(BankSmsService::formatLocal((string)($d['last_seen_at'] ?? '')))?></td>
            <td><span class="status-<?=(int)$d['active']?'SUCCESS':'FAILED'?>"><?=(int)$d['active']?'ok':'off'?></span></td>
            <td><?=(int)$d['active']?'Yes':'No'?></td>
            <td>
                <form method="post" action="<?=e(url('/admin/bank-sms/devices/'.$d['id'].'/toggle'))?>">
                    <?=csrf_field()?>
                    <button class="btn2"><?=(int)$d['active']?'Disable':'Enable'?></button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$devices): ?><tr><td colspan="7" class="muted">No parser devices yet.</td></tr><?php endif; ?>
    </table>
</div>

<div class="card">
    <h2>SMS results</h2>
    <p class="muted">Rows here come only from the Android phone. When a bank credit SMS arrives on that phone, the app posts it to the endpoint above and this list updates.</p>
    <table style="width:100%;border-collapse:collapse">
        <tr><th align="left">Time</th><th align="left">Bank</th><th align="left">Amount</th><th align="left">UTR</th><th align="left">Last 4</th><th align="left">Sender</th><th align="left">Result</th><th align="left">Payment</th><th></th></tr>
        <?php foreach ($events as $ev): ?>
        <tr>
            <td><?=e(BankSmsService::formatLocal((string)$ev['sms_time']))?></td>
            <td><?=e($ev['bank_code'])?></td>
            <td>₹<?=e(number_format((float)$ev['amount'], 2, '.', ''))?></td>
            <td class="mono"><?=e($ev['utr'])?></td>
            <td><?=e($ev['account_last4'])?></td>
            <td><?=e((string)($ev['sender_id'] ?? ''))?></td>
            <td><span class="status-<?=e($ev['match_status']==='SUCCESS'?'SUCCESS':($ev['match_status']==='REVIEW_REQUIRED'?'PENDING':'FAILED'))?>"><?=e($ev['match_status']==='SUCCESS'?'CREDITED':$ev['match_status'])?></span></td>
            <td class="mono"><?=e((string)($ev['reference'] ?: $ev['candidate_refs']))?></td>
            <td>
                <?php if ($ev['match_status'] === 'REVIEW_REQUIRED'): ?>
                    <span class="muted">Same-amount ambiguity — no automatic success</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$events): ?><tr><td colspan="9" class="muted">No bank SMS received yet.</td></tr><?php endif; ?>
    </table>
</div>
<?php include __DIR__.'/_foot.php'; ?>
