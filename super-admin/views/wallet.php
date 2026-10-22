<?php include __DIR__.'/_super_head.php'; ?>
<?php
$tab = strtolower(trim((string)($tab ?? 'all')));
if (!in_array($tab, ['all', 'recharges', 'withdrawals'], true)) {
    $tab = 'all';
}
?>
<div class="sa-card">
    <h1>Wallet Transactions</h1>
    <p class="sa-note">All customer wallet recharges and withdrawal requests.</p>

    <div class="sa-stats sa-stats-3">
        <div class="sa-stat"><div class="label">Recharges (success)</div><div class="value"><?=e((string)($rechargeStats['success'] ?? 0))?></div><div class="sa-muted">₹<?=amount_fmt($rechargeStats['total_amt'] ?? 0)?> credited</div></div>
        <div class="sa-stat"><div class="label">Recharges pending</div><div class="value"><?=e((string)($rechargeStats['pending'] ?? 0))?></div></div>
        <div class="sa-stat"><div class="label">Withdrawals pending</div><div class="value"><?=e((string)(($withdrawStats['requested'] ?? 0) + ($withdrawStats['approved'] ?? 0)))?></div><div class="sa-muted">₹<?=amount_fmt($withdrawStats['pending_amt'] ?? 0)?></div></div>
    </div>

    <div class="row" style="margin:14px 0;display:flex;gap:10px;flex-wrap:wrap">
        <a class="sa-btn<?=$tab === 'all' ? '' : '-gray'?>" href="<?=e(url('/super-admin/wallet'))?>">All</a>
        <a class="sa-btn<?=$tab === 'recharges' ? '' : '-gray'?>" href="<?=e(url('/super-admin/wallet?tab=recharges'))?>">Recharges</a>
        <a class="sa-btn<?=$tab === 'withdrawals' ? '' : '-gray'?>" href="<?=e(url('/super-admin/wallet?tab=withdrawals'))?>">Withdrawals</a>
    </div>

    <?php if ($tab === 'all' || $tab === 'recharges'): ?>
    <h2>Wallet recharges</h2>
    <form class="sa-filters" method="get" action="<?=e(url('/super-admin/wallet'))?>">
        <input type="hidden" name="tab" value="recharges">
        <select name="recharge_status">
            <option value="">All status</option>
            <?php foreach (WalletService::RECHARGE_STATUSES as $s): ?>
            <option value="<?=e($s)?>" <?=($rechargeStatus ?? '') === $s ? 'selected' : ''?>><?=e($s)?></option>
            <?php endforeach; ?>
        </select>
        <input name="q" value="<?=e($q ?? '')?>" placeholder="Reference / customer / UPI ref">
        <button type="submit">Filter</button>
    </form>
    <div class="sa-table-wrap" style="margin:14px 0 24px">
        <table class="sa-table">
            <tr>
                <th>Date</th>
                <th>Reference</th>
                <th>Customer</th>
                <th>Amount</th>
                <th>UPI payment ref</th>
                <th>Bank</th>
                <th>Status</th>
            </tr>
            <?php foreach ($recharges as $r): ?>
            <tr>
                <td><?=e($r['created_at'])?></td>
                <td class="mono"><?=e($r['reference'])?></td>
                <td><?=e($r['customer_name'])?><div class="sa-muted mono"><?=e($r['customer_code'])?></div></td>
                <td>₹<?=amount_fmt($r['amount'])?></td>
                <td class="mono"><?=e($r['txn_reference'] ?? '—')?></td>
                <td><?=e($r['bank_name'] ?? '—')?></td>
                <td class="<?=e(WalletService::statusClass((string)$r['status']))?>"><?=e($r['status'])?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($recharges)): ?>
            <tr><td colspan="7" class="sa-muted">No wallet recharges found.</td></tr>
            <?php endif; ?>
        </table>
    </div>
    <?php endif; ?>

    <?php if ($tab === 'all' || $tab === 'withdrawals'): ?>
    <h2>Withdrawal requests</h2>
    <form class="sa-filters" method="get" action="<?=e(url('/super-admin/wallet'))?>">
        <input type="hidden" name="tab" value="withdrawals">
        <select name="withdraw_status">
            <option value="">All status</option>
            <?php foreach (WalletService::WITHDRAW_STATUSES as $s): ?>
            <option value="<?=e($s)?>" <?=($withdrawStatus ?? '') === $s ? 'selected' : ''?>><?=e($s)?></option>
            <?php endforeach; ?>
        </select>
        <input name="q" value="<?=e($q ?? '')?>" placeholder="Reference / customer / account">
        <button type="submit">Filter</button>
    </form>
    <div class="sa-table-wrap" style="margin-top:14px">
        <table class="sa-table">
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
                <td><?=e($w['customer_name'])?><div class="sa-muted mono"><?=e($w['customer_code'])?></div></td>
                <td>₹<?=amount_fmt($w['amount'])?></td>
                <td><?=e($w['bank_name'])?><div class="sa-muted mono"><?=e($w['account_number'])?> · <?=e($w['ifsc'])?></div></td>
                <td class="<?=e(WalletService::statusClass((string)$w['status']))?>"><?=e($w['status'])?></td>
                <td><a href="<?=e(url('/super-admin/wallet/withdrawals/'.$w['id']))?>">Manage</a></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($withdrawals)): ?>
            <tr><td colspan="7" class="sa-muted">No withdrawal requests found.</td></tr>
            <?php endif; ?>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php include __DIR__.'/_super_foot.php'; ?>
