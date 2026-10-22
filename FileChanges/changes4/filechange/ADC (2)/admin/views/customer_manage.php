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
        <tr><th>Date</th><th>Ref</th><th>Status</th><th>Bank</th><th>Requested</th><th>Confirmed</th><th>Customer UTR</th><th>Screenshot</th><th>Admin UTR Correction</th></tr>
        <?php foreach($txns as $t):?>
        <tr>
            <td><?=e($t['created_at'])?></td>
            <td class="mono"><?=e($t['reference'])?></td>
            <td class="status-<?=e($t['status'])?>"><?=e($t['status'])?></td>
            <td><?=e($t['bank_name'])?></td>
            <td><?=e($t['requested_amount']??'')?></td>
            <td><?=e($t['confirmed_amount']??'')?></td>
            <td><?=e($t['customer_reported_utr']??'')?></td>
            <td><?php if (!empty($t['utr_screenshot_path'])): ?><a href="<?=e(url('/admin/transactions/'.$t['id'].'/screenshot'))?>" target="_blank" rel="noopener">View</a><?php else: ?>—<?php endif; ?></td>
            <td>
                <form method="post" action="<?=e(url('/admin/transactions/'.$t['id'].'/reported-utr'))?>" class="row">
                    <?=csrf_field()?>
                    <input name="utr" value="<?=e($t['customer_reported_utr']??'')?>" placeholder="UTR or blank to clear">
                    <button class="btn2">Save</button>
                </form>
            </td>
        </tr>
        <?php endforeach;?>
    </table>
</div>
<?php include __DIR__.'/_foot.php'; ?>
