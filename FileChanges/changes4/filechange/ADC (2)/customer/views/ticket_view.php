<?php
include __DIR__.'/_dash_head.php';
$ticket = $ticket ?? [];
$replies = $replies ?? [];
?>
<div class="pay dash col-dash">
    <h1 class="col-title">Ticket <?=e($ticket['reference'] ?? '')?></h1>

    <div class="col-stats stmt-stats">
        <div class="col-stat">
            <div class="label">Status</div>
            <div class="value <?=e(TicketService::statusClass((string)($ticket['status'] ?? '')))?>" style="font-size:1.1rem"><?=e($ticket['status'] ?? '')?></div>
        </div>
        <div class="col-stat">
            <div class="label">Category</div>
            <div class="value" style="font-size:1.1rem"><?=e($ticket['category'] ?? '')?></div>
        </div>
        <div class="col-stat">
            <div class="label">Priority</div>
            <div class="value" style="font-size:1.1rem"><?=e($ticket['priority'] ?? '')?></div>
        </div>
        <div class="col-stat">
            <div class="label">Updated</div>
            <div class="value" style="font-size:1rem"><?=e($ticket['updated_at'] ?? '')?></div>
        </div>
    </div>

    <div class="col-panel">
        <h2 class="col-section-title"><?=e($ticket['subject'] ?? '')?></h2>
        <p class="col-note"><?=nl2br(e($ticket['message'] ?? ''))?></p>
    </div>

    <div class="col-panel col-history">
        <h2 class="col-section-title">Conversation</h2>
        <?php foreach ($replies as $r): ?>
        <div class="ticket-reply">
            <div class="ticket-reply-meta"><b><?=e($r['author_name'])?></b> · <?=e($r['author_type'])?> · <?=e($r['created_at'])?></div>
            <div><?=nl2br(e($r['message']))?></div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($replies)): ?>
        <p class="muted">No replies yet. Our team will respond here.</p>
        <?php endif; ?>

        <?php if (!in_array(strtoupper((string)($ticket['status'] ?? '')), ['CLOSED'], true)): ?>
        <form method="post" action="<?=e(url('/c/'.$c['code'].'/tickets/'.$ticket['id'].'/reply'))?>" style="margin-top:16px">
            <?=csrf_field()?>
            <label class="pay-lab">Add reply
                <textarea name="message" rows="4" required placeholder="Write your follow-up message"></textarea>
            </label>
            <button type="submit" class="col-btn-gold">Send reply</button>
        </form>
        <?php endif; ?>
    </div>

    <p class="pay-more"><a href="<?=e(url('/c/'.$c['code'].'/tickets'))?>">← Back to tickets</a></p>
</div>
<?php include __DIR__.'/_dash_foot.php'; ?>
