<?php include __DIR__.'/_portal_head.php'; ?>
<section class="yp-hello">
    <h1><?=e(Portal::t('customers'))?></h1>
    <p>People who paid this account</p>
</section>
<section class="yp-sheet">
    <div class="yp-table-wrap">
        <table class="yp-table">
            <thead>
                <tr><th>Payer</th><th>Payments</th><th>Received</th></tr>
            </thead>
            <tbody>
            <?php foreach ($payers as $row): ?>
                <tr>
                    <td><?=e($row['payer'])?></td>
                    <td><?=e((string)$row['n'])?></td>
                    <td>₹<?=e(Portal::money((float)$row['paid']))?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($payers === []): ?>
                <tr><td colspan="3" class="yp-empty">No payer details yet. They appear after a UPI payment is recorded.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php include __DIR__.'/_portal_foot.php'; ?>
