<?php include __DIR__.'/_portal_head.php'; ?>
<section class="yp-sheet">
    <p class="yp-back"><a href="<?=e(url('/c/'.$c['code'].'/tickets'))?>">← <?=e(Portal::t('tickets'))?></a></p>
    <div class="yp-sheet-head">
        <h1><?=e($ticket['subject'])?></h1>
        <span class="<?=e(TicketService::statusClass((string)$ticket['status']))?>"><?=e(TicketService::statusLabel((string)$ticket['status']))?></span>
    </div>
    <p class="yp-note"><?=e($ticket['reference'])?> · <?=e(TicketService::categoryLabel((string)$ticket['category']))?> · <?=e(Portal::when((string)$ticket['created_at']))?></p>
    <article class="yp-reply">
        <div class="yp-reply-meta"><?=e($c['name'])?></div>
        <p><?=e($ticket['message'])?></p>
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
