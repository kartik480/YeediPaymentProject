<?php include __DIR__.'/_head.php'; ?>
<div class="card">
    <h1>Ticket <?=e($ticket['reference'] ?? '')?></h1>
    <p><b><?=e($ticket['customer_name'] ?? '')?></b> · <span class="mono"><?=e($ticket['customer_code'] ?? '')?></span>
        <?php if (!empty($ticket['customer_phone'])): ?> · <?=e($ticket['customer_phone'])?><?php endif; ?>
    </p>

    <div class="grid money-grid-3" style="margin:14px 0">
        <div class="money-card"><div>Status</div><div class="value"><?=e($ticket['status'] ?? '')?></div></div>
        <div class="money-card"><div>Category</div><div class="value"><?=e($ticket['category'] ?? '')?></div></div>
        <div class="money-card"><div>Priority</div><div class="value"><?=e($ticket['priority'] ?? '')?></div></div>
    </div>

    <div class="card" style="background:#f9fafb">
        <h2><?=e($ticket['subject'] ?? '')?></h2>
        <p><?=nl2br(e($ticket['message'] ?? ''))?></p>
        <p class="muted">Raised: <?=e($ticket['created_at'] ?? '')?></p>
    </div>

    <form method="post" action="<?=e(url('/admin/tickets/'.$ticket['id'].'/update'))?>" class="grid" style="margin:14px 0">
        <?=csrf_field()?>
        <label>Status
            <select name="status">
                <?php foreach (TicketService::STATUSES as $s): ?>
                <option value="<?=e($s)?>" <?=($ticket['status'] ?? '') === $s ? 'selected' : ''?>><?=e($s)?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Priority
            <select name="priority">
                <?php foreach (TicketService::PRIORITIES as $p): ?>
                <option value="<?=e($p)?>" <?=($ticket['priority'] ?? '') === $p ? 'selected' : ''?>><?=e($p)?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Assigned to
            <input name="assigned_to" value="<?=e($ticket['assigned_to'] ?? '')?>" placeholder="Admin username">
        </label>
        <label style="grid-column:1/-1">Internal note
            <textarea name="admin_note" rows="3"><?=e($ticket['admin_note'] ?? '')?></textarea>
        </label>
        <button type="submit">Save ticket</button>
    </form>

    <h2>Conversation</h2>
    <?php foreach ($replies as $r): ?>
    <div class="card" style="margin:10px 0">
        <div class="muted"><b><?=e($r['author_name'])?></b> · <?=e($r['author_type'])?> · <?=e($r['created_at'])?></div>
        <div><?=nl2br(e($r['message']))?></div>
    </div>
    <?php endforeach; ?>

    <form method="post" action="<?=e(url('/admin/tickets/'.$ticket['id'].'/reply'))?>" style="margin-top:14px">
        <?=csrf_field()?>
        <label>Reply to customer
            <textarea name="message" rows="4" required></textarea>
        </label>
        <button type="submit">Send reply</button>
    </form>

    <p style="margin-top:16px"><a href="<?=e(url('/admin/tickets'))?>">← All tickets</a></p>
</div>
<?php include __DIR__.'/_foot.php'; ?>
