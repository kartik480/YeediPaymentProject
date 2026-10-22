<?php include __DIR__.'/_super_head.php'; ?>
<h1 class="sa-title">Transactions</h1>

<form class="sa-filters" method="get" action="<?=e(url('/super-admin/transactions'))?>">
    <label>Status
        <select name="status">
            <option value="">All</option>
            <?php foreach (['SUCCESS', 'PENDING', 'FAILED', 'EXPIRED'] as $x): ?>
            <option value="<?=$x?>" <?=($status ?? '') === $x ? 'selected' : ''?>><?=$x?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Period
        <select name="period">
            <?php foreach (['all' => 'All', 'day' => 'Today', 'week' => 'This week', 'month' => 'This month'] as $k => $label): ?>
            <option value="<?=e($k)?>" <?=($period ?? 'all') === $k ? 'selected' : ''?>><?=e($label)?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Customer Ref
        <input name="ref" value="<?=e($ref ?? '')?>" placeholder="Customer code / txn ref / UTR">
    </label>
    <button type="submit" class="sa-filter-btn">Filter</button>
</form>

<div class="sa-txn-table-wrap scroll">
    <table class="sa-txn-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Txn</th>
                <th>Customer</th>
                <th>Amount</th>
                <th>Payer UTR</th>
                <th>Status</th>
                <th>Service</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($txns ?? [] as $t): ?>
            <tr>
                <td><?=e($t['created_at'])?></td>
                <td class="mono"><?=e($t['reference'])?></td>
                <td><?=e($t['customer_name'])?><div class="sa-sub"><?=e($t['customer_code'])?></div></td>
                <td><?php $amt = TransactionService::displayAmount($t); echo $amt !== '' ? '₹'.e($amt) : '—'; ?></td>
                <td class="mono"><?=e($t['customer_reported_utr'] ?? $t['rrn'] ?? '')?></td>
                <td class="status-<?=e($t['status'])?>"><?=e($t['status'])?></td>
                <td><?=e($t['service'] ?? 'UPI')?></td>
                <td>
                    <?php if (strtoupper((string)$t['status']) === 'PENDING'): ?>
                    <form method="post" action="<?=e(url('/super-admin/transactions/'.$t['id'].'/confirm'))?>" class="sa-inline">
                        <?=csrf_field()?>
                        <button type="submit" name="status" value="SUCCESS" class="sa-link-btn">Confirm</button>
                        <button type="submit" name="status" value="FAILED" class="sa-link-btn sa-danger">Reject</button>
                    </form>
                    <?php elseif (!empty($t['utr_screenshot_path'])): ?>
                    <a class="sa-link-btn" href="<?=e(url('/super-admin/transactions/'.$t['id'].'/screenshot'))?>" target="_blank" rel="noopener">View</a>
                    <?php else: ?>
                    —
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($txns)): ?>
            <tr><td colspan="8" class="sa-empty">No transactions for this filter.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__.'/_super_foot.php'; ?>
