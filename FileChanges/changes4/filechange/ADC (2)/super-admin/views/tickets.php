<?php include __DIR__.'/_super_head.php'; ?>
<h1 class="sa-title">Support Tickets</h1>
<div class="sa-stats">
    <div class="sa-stat"><div class="label">Open</div><div class="value"><?=e((string)($stats['open'] ?? 0))?></div></div>
    <div class="sa-stat"><div class="label">In progress</div><div class="value"><?=e((string)($stats['in_progress'] ?? 0))?></div></div>
    <div class="sa-stat"><div class="label">Resolved</div><div class="value"><?=e((string)($stats['resolved'] ?? 0))?></div></div>
    <div class="sa-stat"><div class="label">Total</div><div class="value"><?=e((string)($stats['total'] ?? 0))?></div></div>
</div>
<div class="sa-panel">
    <form method="get" action="<?=e(url('/super-admin/tickets'))?>" class="sa-filter-row">
        <label>Status
            <select name="status">
                <option value="">All</option>
                <?php foreach (TicketService::STATUSES as $s): ?>
                <option value="<?=e($s)?>" <?=($status ?? '') === $s ? 'selected' : ''?>><?=e($s)?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Search
            <input name="q" value="<?=e($q ?? '')?>" placeholder="Reference / customer / subject">
        </label>
        <button type="submit" class="sa-filter-btn">Filter</button>
    </form>
    <div class="sa-txn-table-wrap scroll">
        <table class="sa-txn-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Reference</th>
                    <th>Customer</th>
                    <th>Subject</th>
                    <th>Category</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($tickets as $t): ?>
                <tr>
                    <td><?=e($t['created_at'])?></td>
                    <td class="mono"><?=e($t['reference'])?></td>
                    <td><?=e($t['customer_name'])?><div class="sa-sub mono"><?=e($t['customer_code'])?></div></td>
                    <td><?=e($t['subject'])?></td>
                    <td><?=e($t['category'])?></td>
                    <td><?=e($t['priority'])?></td>
                    <td><?=e($t['status'])?></td>
                    <td><a class="sa-link-btn" href="<?=e(url('/super-admin/tickets/'.$t['id']))?>">View</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($tickets)): ?>
                <tr><td colspan="8" class="sa-empty">No tickets found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__.'/_super_foot.php'; ?>
