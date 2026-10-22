<?php
include __DIR__.'/_head.php';
$acc = trim((string)($c['account_number'] ?? ''));
?>
<h1><?=e($c['code'])?> — <?=e($c['name'])?></h1>
<div class="money-grid money-grid-3">
    <div class="money-card">
        <div class="label">Success Amount</div>
        <div class="value">₹<?=amount_fmt($success_total ?? 0)?></div>
    </div>
    <div class="money-card">
        <div class="label">Pending</div>
        <div class="value">₹<?=amount_fmt($pending_total ?? 0)?></div>
    </div>
    <div class="money-card money-card-reject">
        <div class="label">Rejected Amount</div>
        <div class="value">₹<?=amount_fmt($rejected_total ?? 0)?></div>
        <div class="money-sub">Unsuccessful QR payments (server / bank failure)</div>
    </div>
</div>
<div class="card">    <p><b>Payment Link:</b> <a class="mono" href="<?=e(pay_url('/p/'.$c['code']))?>" target="_blank" rel="noopener"><?=e(pay_url('/p/'.$c['code']))?></a></p>
    <p><b>Customer Dashboard:</b> <a href="<?=e(url('/admin/accounts/'.$c['code']))?>">Open this customer's dashboard</a></p>
    <p><a href="<?=e(url('/admin/accounts/'.$c['code'].'/statement'))?>">Account Statement</a> · <a href="<?=e(url('/admin/accounts/'.$c['code'].'/billing'))?>">Billing</a> · <a href="<?=e(url('/admin/accounts/'.$c['code'].'/wallet'))?>">Wallet</a></p>
    <p><b>Merchant QR:</b> <a class="mono" href="<?=e(pay_url('/c/'.$c['code'].'/qr'))?>" target="_blank" rel="noopener"><?=e(pay_url('/c/'.$c['code'].'/qr'))?></a></p>
    <p><b>Merchant service:</b> <?=!empty($c['service_activated'])?'ACTIVATED (confirmed SUCCESS)':'WAITING — activates only after a bank-confirmed SUCCESS payment'?></p>
    <p><b>Account number:</b> <?=$acc !== '' ? '<span class="mono">'.e($acc).'</span>' : 'Not generated yet (created when a valid UTR marks SUCCESS)'?></p>
    <p><a class="btn2 btn" target="_blank" rel="noopener" href="<?=e(pay_url('/p/'.$c['code'].'/qr'))?>">Open / Download Permanent QR</a>
        <a class="btn2 btn" href="<?=e(url('/admin/customers/'.$c['code'].'/statement.pdf'))?>">Download PDF statement</a></p>
</div>
<div class="card">
    <h2>Merchant Payment API</h2>
    <p><b>Create payment endpoint:</b> <span class="mono">POST <?=e(pay_url('/api/v1/payments'))?></span></p>
    <p><b>Check status endpoint:</b> <span class="mono">GET <?=e(pay_url('/api/v1/payments/{payment_id}'))?></span></p>
    <p class="muted">Send headers: X-Merchant-Id, X-Api-Key, X-Timestamp and X-Signature. The signature is HMAC-SHA256 of timestamp, method, path and the SHA-256 request-body hash using the API secret.</p>
    <pre class="mono" style="white-space:pre-wrap">{
  "order_id": "ORDER1001",
  "amount": "100.00",
  "customer_name": "Customer Name",
  "mobile": "9876543210"
}</pre>
    <?php if(!empty($newMerchant)): ?>
    <div class="ok">
        <b>Copy these credentials now. API and webhook secrets are shown only once.</b>
        <p>Merchant ID<br><input class="mono" readonly value="<?=e($newMerchant['merchant_id'])?>" onclick="this.select()"></p>
        <p>API Key<br><input class="mono" readonly value="<?=e($newMerchant['api_key'])?>" onclick="this.select()"></p>
        <p>API Secret<br><input class="mono" readonly value="<?=e($newMerchant['api_secret'])?>" onclick="this.select()"></p>
        <p>Webhook Secret<br><input class="mono" readonly value="<?=e($newMerchant['webhook_secret'])?>" onclick="this.select()"></p>
    </div>
    <?php endif; ?>
    <?php if(!empty($merchants)): ?>
    <div class="scroll"><table><tr><th>Merchant ID</th><th>Mode</th><th>API key ending</th><th>Status</th><th>Created</th></tr>
    <?php foreach($merchants as $merchant): ?><tr><td class="mono"><?=e($merchant['merchant_id'])?></td><td><?=e($merchant['mode'])?></td><td class="mono">…<?=e($merchant['api_key_hint'])?></td><td><?=!empty($merchant['active'])?'ACTIVE':'DISABLED'?></td><td><?=e($merchant['created_at'])?></td></tr><?php endforeach; ?>
    </table></div>
    <?php endif; ?>
    <form method="post" action="<?=e(url('/admin/customers/'.$c['code'].'/api-keys'))?>" onsubmit="return confirm('Generate another API credential set for this customer?')">
        <?=csrf_field()?>
        <button type="submit">Generate New API Keys</button>
    </form>
</div>
<div class="card">
    <h2>Customer Details & Limits</h2>
    <form method="post" action="<?=e(url('/admin/customers/'.$c['code'].'/update'))?>">
        <?=csrf_field()?>
        <div class="grid">
            <label>Name
                <input name="name" value="<?=e($c['name'])?>" required>
            </label>
            <label>Phone
                <input name="phone" value="<?=e($c['phone'])?>" required>
            </label>
            <label>Login ID
                <input name="login_id" value="<?=e($c['login_id'] ?? '')?>" minlength="4" maxlength="64" autocomplete="username" required>
            </label>
            <label>New password <span class="muted">(blank = keep)</span>
                <input type="password" name="password" minlength="8" autocomplete="new-password" placeholder="Optional">
            </label>
            <label>Account number
                <input class="mono" value="<?=e($acc !== '' ? $acc : 'Not generated yet')?>" readonly>
            </label>
            <label>Per txn
                <input name="per_txn_limit" value="<?=e($c['per_txn_limit']??'')?>" placeholder="Per txn">
            </label>
            <label>Daily
                <input name="daily_limit" value="<?=e($c['daily_limit']??'')?>" placeholder="Daily">
            </label>
            <label>Weekly
                <input name="weekly_limit" value="<?=e($c['weekly_limit']??'')?>" placeholder="Weekly">
            </label>
            <label>Monthly
                <input name="monthly_limit" value="<?=e($c['monthly_limit']??'')?>" placeholder="Monthly">
            </label>
            <label>Daily count
                <input name="daily_txn_count_limit" value="<?=e($c['daily_txn_count_limit']??'')?>" placeholder="Daily count">
            </label>
            <label>Max pending
                <input name="max_pending_attempts" value="<?=e($c['max_pending_attempts'])?>">
            </label>
        </div>
        <p><button>Save</button></p>
    </form>
    <form method="post" action="<?=e(url('/admin/customers/'.$c['code'].'/toggle'))?>">
        <?=csrf_field()?>
        <button class="btn2"><?=((int)$c['active']?'Disable Customer Link':'Enable Customer Link')?></button>
    </form>
</div>
<div class="card scroll">
    <h2>Recent Transactions</h2>
    <table>
        <tr><th>Date (IST)</th><th>Ref</th><th>Status</th><th>Bank</th><th>Buyer name</th><th>Requested</th><th>Confirmed</th><th>UTR number</th><th>Screenshot</th><th>Admin UTR Correction</th></tr>
        <?php foreach($txns as $t):?>
        <tr>
            <td><?=e(format_datetime_ist((string)($t['created_at'] ?? '')))?></td>
            <td class="mono"><?=e($t['reference'])?></td>
            <td class="status-<?=e($t['status'])?>"><?=e($t['status'])?></td>
            <td><?=e($t['bank_name'])?></td>
            <td><?=e(TransactionService::displayBuyerName($t))?></td>
            <td><?=e($t['requested_amount']??'')?></td>
            <td><?=e($t['confirmed_amount']??'')?></td>
            <td class="mono"><?=e(TransactionService::displayVerifiedUtr($t))?></td>
            <td><?php if (!empty($t['utr_screenshot_path'])): ?><a href="<?=e(url('/admin/transactions/'.$t['id'].'/screenshot'))?>" target="_blank" rel="noopener">View</a><?php else: ?>—<?php endif; ?></td>
            <td>
                <?php if (!empty($t['is_razorpay'])): ?>
                <span class="muted">Razorpay</span>
                <?php else: ?>
                <form method="post" action="<?=e(url('/admin/transactions/'.$t['id'].'/reported-utr'))?>" class="row">
                    <?=csrf_field()?>
                    <input name="utr" value="<?=e($t['customer_reported_utr']??'')?>" placeholder="UTR or blank to clear">
                    <button class="btn2">Save</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach;?>
    </table>
</div>
<?php include __DIR__.'/_foot.php'; ?>
