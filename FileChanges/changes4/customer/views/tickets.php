<?php include __DIR__.'/_portal_head.php'; ?>
<section class="yp-sheet">
    <div class="yp-sheet-head">
        <h1><?=e(Portal::t('tickets'))?></h1>
        <?php if (empty($show_form)): ?>
        <a class="yp-btn yp-btn-sm" href="<?=e(url('/c/'.$c['code'].'/tickets?new=1'))?>">+ <?=e(Portal::t('new_ticket'))?></a>
        <?php endif; ?>
    </div>
    <?php if (!empty($show_form)): ?>
    <form class="yp-ticket-form" method="post" action="<?=e(url('/c/'.$c['code'].'/tickets'))?>">
        <?=csrf_field()?>
        <?php if (!empty($error)): ?><div class="yp-error"><?=e($error)?></div><?php endif; ?>
        <label>Subject
            <input name="subject" maxlength="220" required placeholder="Brief summary">
        </label>
        <label>Category
            <select name="category">
                <?php foreach (TicketService::CATEGORIES as $cat): ?>
                <option value="<?=e($cat)?>"><?=e(TicketService::categoryLabel($cat))?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="yp-span">Message
            <textarea name="message" rows="4" required placeholder="Describe the issue"></textarea>
        </label>
        <div class="yp-form-actions">
            <button class="yp-btn yp-btn-sm" type="submit">Submit</button>
            <a href="<?=e(url('/c/'.$c['code'].'/tickets'))?>">Cancel</a>
        </div>
    </form>
    <?php endif; ?>
    <div class="yp-table-wrap">
        <table class="yp-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Subject</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Created On</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php $n = 1; foreach ($tickets as $ticket): ?>
                <tr>
                    <td><?=$n++?></td>
                    <td><?=e($ticket['subject'])?></td>
                    <td><?=e(TicketService::categoryLabel((string)$ticket['category']))?></td>
                    <td><span class="<?=e(TicketService::statusClass((string)$ticket['status']))?>"><?=e(TicketService::statusLabel((string)$ticket['status']))?></span></td>
                    <td><?=e(Portal::when((string)$ticket['created_at'], 'd-m-Y H:i'))?></td>
                    <td>
                        <a class="yp-eye" href="<?=e(url('/c/'.$c['code'].'/tickets/'.$ticket['id']))?>" aria-label="View ticket">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($tickets === []): ?>
                <tr><td colspan="6" class="yp-empty">No tickets yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php include __DIR__.'/_portal_foot.php'; ?>
