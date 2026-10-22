<?php
include __DIR__.'/_dash_head.php';
$tickets = $tickets ?? [];
?>
<div class="pay dash col-dash">
    <h1 class="col-title">Raise Ticket</h1>

    <div class="col-panel">
        <h2 class="col-section-title">New support ticket</h2>
        <?php if (!empty($error)): ?><div class="error"><?=e($error)?></div><?php endif; ?>
        <form method="post" action="<?=e(url('/c/'.$c['code'].'/tickets'))?>" class="col-filter-form stmt-filter-form">
            <?=csrf_field()?>
            <label>Category
                <select name="category">
                    <?php foreach (TicketService::CATEGORIES as $cat): ?>
                    <option value="<?=e($cat)?>"><?=e(ucfirst(strtolower($cat)))?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Priority
                <select name="priority">
                    <?php foreach (TicketService::PRIORITIES as $p): ?>
                    <option value="<?=e($p)?>" <?=$p === 'NORMAL' ? 'selected' : ''?>><?=e(ucfirst(strtolower($p)))?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label style="grid-column:1/-1">Subject
                <input type="text" name="subject" maxlength="220" required placeholder="Brief summary of your issue">
            </label>
            <label style="grid-column:1/-1">Message
                <textarea name="message" rows="5" required placeholder="Describe the issue in detail"></textarea>
            </label>
            <div class="col-filter-actions" style="grid-column:1/-1">
                <button type="submit" class="col-btn-gold">Submit ticket</button>
            </div>
        </form>
    </div>

    <div class="col-panel col-history">
        <h2 class="col-section-title">Your tickets</h2>
        <div class="scroll">
            <table class="col-table bill-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Reference</th>
                        <th>Subject</th>
                        <th>Category</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($tickets as $t): ?>
                    <tr>
                        <td><?=e($t['created_at'])?></td>
                        <td class="mono"><?=e($t['reference'])?></td>
                        <td><?=e($t['subject'])?></td>
                        <td><?=e($t['category'])?></td>
                        <td><?=e($t['priority'])?></td>
                        <td class="<?=e(TicketService::statusClass((string)$t['status']))?>"><?=e($t['status'])?></td>
                        <td><a href="<?=e(url('/c/'.$c['code'].'/tickets/'.$t['id']))?>">View</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($tickets)): ?>
                    <tr><td colspan="7" class="muted">No tickets yet. Use the form above to raise one.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include __DIR__.'/_dash_foot.php'; ?>
