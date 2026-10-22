<?php include __DIR__.'/_head.php'; ?>
<div class="card">
    <h1>Wallet Withdrawals</h1>
    <div class="grid money-grid-3" style="margin-bottom:14px">
        <div class="money-card"><div>Requested</div><div class="value"><?=e((string)($stats['requested'] ?? 0))?></div></div>
        <div class="money-card"><div>Approved / processing</div><div class="value"><?=e((string)($stats['approved'] ?? 0))?></div></div>
        <div class="money-card"><div>Paid</div><div class="value"><?=e((string)($stats['paid'] ?? 0))?></div></div>
    </div>
    <p class="muted">Pending payout amount: ₹<?=amount_fmt($stats['pending_amt'] ?? 0)?></p>
    <form class="row" method="get" action="<?=e(url('/admin/wallet/withdrawals'))?>">
        <select name="status">
            <option value="">All status</option>
            <?php foreach (WalletService::WITHDRAW_STATUSES as $s): ?>
            <option value="<?=e($s)?>" <?=($status ?? '') === $s ? 'selected' : ''?>><?=e($s)?></option>
            <?php endforeach; ?>
        </select>
        <input class="grow" name="q" value="<?=e($q ?? '')?>" placeholder="Reference / customer / account">
        <button type="submit">Filter</button>
    </form>
    <div class="scroll" style="margin-top:14px">
        <table>
            <tr>
                <th>Date</th>
                <th>Reference</th>
                <th>Customer</th>
                <th>Amount</th>
                <th>Bank details</th>
                <th>Status</th>
                <th></th>
            </tr>
            <?php foreach ($withdrawals as $w): ?>
            <tr>
                <td><?=e($w['created_at'])?></td>
                <td class="mono"><?=e($w['reference'])?></td>
                <td><?=e($w['customer_name'])?><div class="muted mono"><?=e($w['customer_code'])?></div></td>
                <td>₹<?=amount_fmt($w['amount'])?></td>
                <td><?=e($w['bank_name'])?><div class="muted mono"><?=e($w['account_number'])?> · <?=e($w['ifsc'])?></div></td>
                <td class="<?=e(WalletService::statusClass((string)$w['status']))?>"><?=e($w['status'])?></td>
                <td><a href="<?=e(url('/admin/wallet/withdrawals/'.$w['id']))?>">Manage</a></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($withdrawals)): ?>
            <tr><td colspan="7" class="muted">No withdrawal requests found.</td></tr>
            <?php endif; ?>
        </table>
    </div>
</div>
<?php include __DIR__.'/_foot.php'; ?>
