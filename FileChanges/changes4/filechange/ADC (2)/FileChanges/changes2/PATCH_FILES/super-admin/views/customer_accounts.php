<?php include __DIR__.'/_super_head.php'; ?>
<h1 class="sa-title">Customer Accounts</h1>

<div class="sa-stats sa-stats-5">
    <div class="sa-stat">
        <div class="label">Confirmed</div>
        <div class="value">₹<?=amount_fmt($stats['confirmed'] ?? 0)?></div>
    </div>
    <div class="sa-stat">
        <div class="label">Pending</div>
        <div class="value">₹<?=amount_fmt($stats['pending'] ?? 0)?></div>
    </div>
    <div class="sa-stat">
        <div class="label">Refunded</div>
        <div class="value">₹<?=amount_fmt($stats['refunded'] ?? 0)?></div>
    </div>
    <div class="sa-stat">
        <div class="label">Settled</div>
        <div class="value">₹<?=amount_fmt($stats['settled'] ?? 0)?></div>
    </div>
    <div class="sa-stat">
        <div class="label">All Ledger Balance</div>
        <div class="value">₹<?=amount_fmt($stats['ledger_balance'] ?? 0)?></div>
    </div>
</div>

<div class="sa-card scroll">
    <table class="sa-table">
        <tr>
            <th>Customer ID</th>
            <th>Name</th>
            <th>Account number</th>
            <th>Ledger balance</th>
            <th>Transactions</th>
            <th>Status</th>
        </tr>
        <?php foreach ($accounts ?? [] as $a): ?>
        <tr>
            <td class="mono"><?=e($a['code'])?></td>
            <td><?=e($a['name'])?></td>
            <td class="mono"><?=e(trim((string)($a['account_number'] ?? '')) !== '' ? $a['account_number'] : '—')?></td>
            <td>₹<?=amount_fmt((float)($a['ledger_balance'] ?? 0))?></td>
            <td><?=e((string)($a['txn_count'] ?? 0))?></td>
            <td><?=!empty($a['active']) ? 'ACTIVE' : 'DISABLED'?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($accounts)): ?>
        <tr><td colspan="6">No customer accounts yet.</td></tr>
        <?php endif; ?>
    </table>
</div>
<p class="sa-note">Ledger balance = bank-confirmed SUCCESS collections − SETTLED payouts. PENDING/FAILED/EXPIRED do not change balance, and FAILED is not a refund.</p>
<?php include __DIR__.'/_super_foot.php'; ?>
