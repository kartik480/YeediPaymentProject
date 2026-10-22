<?php include __DIR__.'/_portal_head.php'; ?>
<section class="yp-hello">
    <h1><?=e(Portal::t('withdrawals'))?></h1>
    <p>Settled collections</p>
</section>
<section class="yp-sheet">
    <div class="yp-table-wrap">
        <table class="yp-table">
            <thead>
                <tr><th>Date</th><th>Transaction ID</th><th>Amount</th><th>Bank</th></tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $t):
                $raw = TransactionService::displayAmount($t);
                $num = $raw === '' ? 0.0 : (float)str_replace(',', '', $raw);
            ?>
                <tr>
                    <td><?=e(Portal::when((string)$t['created_at']))?></td>
                    <td class="mono"><?=e($t['reference'])?></td>
                    <td>₹<?=e(Portal::money($num))?></td>
                    <td><?=e((string)($t['bank_name'] ?? ''))?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?>
                <tr><td colspan="4" class="yp-empty">No settled withdrawals yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php include __DIR__.'/_portal_foot.php'; ?>
