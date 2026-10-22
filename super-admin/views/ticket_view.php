<?php include __DIR__.'/_super_head.php'; ?>
<h1 class="sa-title">Ticket <?=e($ticket['reference'] ?? '')?></h1>
<?php if (!empty($error)): ?><div class="sa-error"><?=e($error)?></div><?php endif; ?>

<div class="sa-stats">
    <div class="sa-stat"><div class="label">Status</div><div class="value"><?=e($ticket['status'] ?? '')?></div></div>
    <div class="sa-stat"><div class="label">Category</div><div class="value"><?=e($ticket['category'] ?? '')?></div></div>
    <div class="sa-stat"><div class="label">Priority</div><div class="value"><?=e($ticket['priority'] ?? '')?></div></div>
    <div class="sa-stat"><div class="label">Customer</div><div class="value" style="font-size:1rem"><?=e($ticket['customer_code'] ?? '')?></div></div>
</div>

<div class="sa-panel">
    <h2 class="sa-section-title"><?=e($ticket['subject'] ?? '')?></h2>
    <p class="sa-note"><?=e($ticket['customer_name'] ?? '')?> · <?=e($ticket['customer_phone'] ?? '')?> · <?=e($ticket['customer_email'] ?? '')?></p>
    <p><?=nl2br(e($ticket['message'] ?? ''))?></p>
    <p class="sa-note">Raised: <?=e($ticket['created_at'] ?? '')?> · Updated: <?=e($ticket['updated_at'] ?? '')?></p>
</div>

<div class="sa-panel sa-billing-form">
    <h2 class="sa-section-title">Manage ticket</h2>
    <form method="post" action="<?=e(url('/super-admin/tickets/'.$ticket['id'].'/update'))?>">
        <?=csrf_field()?>
        <div class="sa-form-grid">
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
                <input name="assigned_to" value="<?=e($ticket['assigned_to'] ?? '')?>" placeholder="Staff username">
            </label>
            <label style="grid-column:1/-1">Internal note
                <textarea name="admin_note" rows="3"><?=e($ticket['admin_note'] ?? '')?></textarea>
            </label>
        </div>
        <button type="submit" class="sa-filter-btn sa-save-settings-btn">Save ticket</button>
    </form>
</div>

<div class="sa-panel">
    <h2 class="sa-section-title">Conversation</h2>
    <?php foreach ($replies as $r): ?>
    <div class="sa-panel" style="margin-bottom:10px;padding:14px">
        <div class="sa-note"><b><?=e($r['author_name'])?></b> · <?=e($r['author_type'])?> · <?=e($r['created_at'])?></div>
        <div><?=nl2br(e($r['message']))?></div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($replies)): ?>
    <p class="sa-empty">No replies yet.</p>
    <?php endif; ?>

    <form method="post" action="<?=e(url('/super-admin/tickets/'.$ticket['id'].'/reply'))?>" style="margin-top:14px">
        <?=csrf_field()?>
        <label>Reply to customer
            <textarea name="message" rows="4" required></textarea>
        </label>
        <button type="submit" class="sa-filter-btn">Send reply</button>
    </form>
</div>

<p class="sa-note"><a href="<?=e(url('/super-admin/tickets'))?>">← All tickets</a></p>
<?php include __DIR__.'/_super_foot.php'; ?>
