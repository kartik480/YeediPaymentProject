<?php include __DIR__.'/_head.php'; ?>
<div class="card">
    <h1>Support Tickets</h1>
    <div class="grid money-grid-3" style="margin-bottom:14px">
        <div class="money-card"><div>Open</div><div class="value"><?=e((string)($stats['open'] ?? 0))?></div></div>
        <div class="money-card"><div>In progress</div><div class="value"><?=e((string)($stats['in_progress'] ?? 0))?></div></div>
        <div class="money-card"><div>Resolved / closed</div><div class="value"><?=e((string)($stats['resolved'] ?? 0))?></div></div>
    </div>
    <form class="row" method="get" action="<?=e(url('/admin/tickets'))?>">
        <select name="status">
            <option value="">All status</option>
            <?php foreach (TicketService::STATUSES as $s): ?>
            <option value="<?=e($s)?>" <?=($status ?? '') === $s ? 'selected' : ''?>><?=e($s)?></option>
            <?php endforeach; ?>
        </select>
        <input class="grow" name="q" value="<?=e($q ?? '')?>" placeholder="Reference / customer / subject">
        <button type="submit">Filter</button>
    </form>
    <div class="scroll" style="margin-top:14px">
        <table>
            <tr>
                <th>Date</th>
                <th>Reference</th>
                <th>Customer</th>
                <th>Subject</th>
                <th>Category</th>
                <th>Priority</th>
                <th>Status</th>
                <th></th>
            </tr>
            <?php foreach ($tickets as $t): ?>
            <tr>
                <td><?=e($t['created_at'])?></td>
                <td class="mono"><?=e($t['reference'])?></td>
                <td><?=e($t['customer_name'])?><div class="muted mono"><?=e($t['customer_code'])?></div></td>
                <td><?=e($t['subject'])?></td>
                <td><?=e($t['category'])?></td>
                <td><?=e($t['priority'])?></td>
                <td class="<?=e(TicketService::statusClass((string)$t['status']))?>"><?=e($t['status'])?></td>
                <td><a href="<?=e(url('/admin/tickets/'.$t['id']))?>">Manage</a></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($tickets)): ?>
            <tr><td colspan="8" class="muted">No tickets found.</td></tr>
            <?php endif; ?>
        </table>
    </div>
</div>
<?php include __DIR__.'/_foot.php'; ?>
