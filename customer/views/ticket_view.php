<?php include __DIR__.'/_portal_head.php'; ?>
<section class="yp-sheet">
    <p class="yp-back"><a href="<?=e(url('/c/'.$c['code'].'/tickets'))?>">← <?=e(Portal::t('tickets'))?></a></p>
    <div class="yp-sheet-head">
        <h1><?=e($ticket['subject'])?></h1>
        <span class="<?=e(TicketService::statusClass((string)$ticket['status']))?>"><?=e(TicketService::statusLabel((string)$ticket['status']))?></span>
    </div>
    <p class="yp-note"><?=e($ticket['reference'])?> · <?=e(TicketService::categoryLabel((string)$ticket['category']))?> · <?=e(Portal::when((string)$ticket['created_at']))?></p>
    <?php if (!empty($ticket['order_reference'])): ?>
    <p class="yp-note">Order / transaction: <span class="mono"><?=e((string)$ticket['order_reference'])?></span></p>
    <?php endif; ?>
    <p class="yp-note"><a class="yp-btn yp-btn-sm" href="<?=e(TicketService::whatsappSupportUrl($c, TicketService::problemSummary($ticket), (string)$ticket['reference']))?>" target="_blank" rel="noopener noreferrer">Continue on WhatsApp</a></p>
    <article class="yp-reply">
        <div class="yp-reply-meta"><?=e($c['name'])?></div>
        <p><?=e($ticket['message'])?></p>
        <?php if (!empty($ticket['attachment_path'])): ?>
        <p><a href="<?=e(url('/c/'.$c['code'].'/tickets/'.$ticket['id'].'/attachment'))?>" target="_blank" rel="noopener">View screenshot</a></p>
        <?php endif; ?>
    </article>
    <?php foreach ($replies as $reply): ?>
    <article class="yp-reply">
        <div class="yp-reply-meta"><?=e($reply['author_name'])?> · <?=e(Portal::when((string)$reply['created_at']))?></div>
        <p><?=e($reply['message'])?></p>
    </article>
    <?php endforeach; ?>
    <?php if (!empty($error)): ?><div class="yp-error"><?=e($error)?></div><?php endif; ?>
    <form class="yp-ticket-form" method="post" action="<?=e(url('/c/'.$c['code'].'/tickets/'.$ticket['id'].'/reply'))?>">
        <?=csrf_field()?>
        <label class="yp-span">Reply
            <textarea name="message" rows="4" required></textarea>
        </label>
        <div class="yp-form-actions">
            <button class="yp-btn yp-btn-sm" type="submit">Send reply</button>
        </div>
    </form>
</section>
<?php include __DIR__.'/_portal_foot.php'; ?>
