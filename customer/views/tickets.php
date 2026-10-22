<?php
include __DIR__.'/_portal_head.php';
$quickWa = TicketService::whatsappSupportUrl($c, 'I need help with my payment/order.');
$waRedirect = $whatsapp_redirect ?? '';
?>
<section class="yp-sheet">
    <div class="yp-sheet-head">
        <h1><?=e(Portal::t('tickets'))?></h1>
        <a class="yp-btn yp-btn-sm yp-btn-ghost" href="<?=e($quickWa)?>" target="_blank" rel="noopener noreferrer">WhatsApp seller</a>
    </div>
    <p class="yp-note">Submit a ticket here — we save it for your records, then open WhatsApp so you can chat with the seller at <strong>+66 64 258 4053</strong>.</p>

    <?php if (!empty($submitted_ticket)): ?>
    <div class="yp-flash" role="status">
        <strong>Ticket submitted.</strong> Your Ticket ID is <span class="mono"><?=e((string)$submitted_ticket['reference'])?></span>.
        Status: <?=e(TicketService::statusLabel((string)$submitted_ticket['status']))?>.
        <?php if ($waRedirect !== ''): ?>
        Opening WhatsApp… If nothing happens, <a href="<?=e($waRedirect)?>" target="_blank" rel="noopener noreferrer">tap here to message the seller</a>.
        <?php endif; ?>
    </div>
    <?php if ($waRedirect !== ''): ?>
    <script>
    (function () {
      var url = <?=json_encode($waRedirect, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)?>;
      setTimeout(function () { window.location.href = url; }, 900);
    })();
    </script>
    <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($show_form)): ?>
    <form class="yp-ticket-form" method="post" action="<?=e(url('/c/'.$c['code'].'/tickets'))?>" enctype="multipart/form-data">
        <?=csrf_field()?>
        <?php if (!empty($error)): ?><div class="yp-error"><?=e($error)?></div><?php endif; ?>
        <label>Subject
            <input name="subject" maxlength="220" required placeholder="Brief summary of your issue" value="<?=e((string)($_POST['subject'] ?? ''))?>">
        </label>
        <label>Problem category
            <select name="category">
                <?php foreach (TicketService::CATEGORIES as $cat): ?>
                <option value="<?=e($cat)?>" <?=(strtoupper((string)($_POST['category'] ?? '')) === $cat ? 'selected' : '')?>><?=e(TicketService::categoryLabel($cat))?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Order / transaction ID
            <input name="order_reference" maxlength="80" placeholder="Payment ref or order ID (optional)" value="<?=e((string)($_POST['order_reference'] ?? ''))?>">
        </label>
        <label class="yp-span">Detailed problem description
            <textarea name="message" rows="5" required placeholder="Explain what happened, amount, date, UTR if any"><?=e((string)($_POST['message'] ?? ''))?></textarea>
        </label>
        <label class="yp-span">Screenshot (optional)
            <input type="file" name="screenshot" accept="image/jpeg,image/png,image/webp">
        </label>
        <div class="yp-form-actions yp-span">
            <button class="yp-btn" type="submit">Submit ticket</button>
        </div>
    </form>
    <?php endif; ?>

    <h2 style="font-size:1.05rem;margin:18px 0 10px">Your tickets</h2>
    <div class="yp-table-wrap">
        <table class="yp-table">
            <thead>
                <tr>
                    <th>Ticket ID</th>
                    <th>Subject</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($tickets as $ticket): ?>
                <tr>
                    <td class="mono"><?=e($ticket['reference'])?></td>
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
                <tr><td colspan="6" class="yp-empty">No tickets yet. Use the form above or WhatsApp the seller.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php include __DIR__.'/_portal_foot.php'; ?>
