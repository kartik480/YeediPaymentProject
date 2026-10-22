<?php include __DIR__.'/_super_head.php'; ?>
<div class="sa-panel sa-report-panel">
    <h2 class="sa-section-title">Daily Collection Summary</h2>
    <div class="sa-txn-table-wrap scroll">
        <table class="sa-txn-table sa-report-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Success</th>
                    <th>Pending</th>
                    <th>Failed</th>
                    <th>Success Amount</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows ?? [] as $r): ?>
                <tr>
                    <td><?=e($r['date'])?></td>
                    <td><?=e((string)($r['total'] ?? 0))?></td>
                    <td><?=e((string)($r['success'] ?? 0))?></td>
                    <td><?=e((string)($r['pending'] ?? 0))?></td>
                    <td><?=e((string)($r['failed'] ?? 0))?></td>
                    <td>₹<?=amount_fmt($r['success_amount'] ?? 0)?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
                <tr><td colspan="6" class="sa-empty">No collection activity yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__.'/_super_foot.php'; ?>
