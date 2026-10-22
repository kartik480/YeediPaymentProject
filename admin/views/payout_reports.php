<?php include __DIR__.'/_payout_head.php'; ?>
<div class="po-head">
    <div>
        <h1>Reports</h1>
        <p class="po-sub">Payout batches and collection health from platform records.</p>
    </div>
    <a class="po-btn" href="<?=e(url('/admin/payout'))?>">Back to Home</a>
</div>
<div class="po-stats">
    <div class="po-stat">
        <div class="k">SUCCESS AMOUNT</div>
        <div class="n">₹<?=amount_fmt($success_amount ?? 0)?></div>
        <div class="s">Confirmed collections</div>
    </div>
    <div class="po-stat">
        <div class="k">UNSETTLED</div>
        <div class="n">₹<?=amount_fmt($unsettled_amount ?? 0)?></div>
        <div class="s">Pending payout coverage</div>
    </div>
    <div class="po-stat">
        <div class="k">BATCHES</div>
        <div class="n"><?=e((string)count($rows ?? []))?></div>
        <div class="s">Settlement requests</div>
    </div>
</div>
<div class="po-card scroll">
    <table class="po-table">
        <tr><th>Reference</th><th>Range</th><th>Count</th><th>Net</th><th>Status</th></tr>
        <?php foreach ($rows ?? [] as $s): ?>
        <tr>
            <td class="mono"><?=e($s['reference'])?></td>
            <td><?=e($s['range_start'])?> → <?=e($s['range_end'])?></td>
            <td><?=e((string)$s['txn_count'])?></td>
            <td>₹<?=amount_fmt($s['net_amount'] ?? 0)?></td>
            <td><?=e($s['status'])?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?><tr><td colspan="5" class="po-muted">No payout reports yet. Request a settlement from Settlements when collections are ready.</td></tr><?php endif; ?>
    </table>
</div>
<?php include __DIR__.'/_payout_foot.php'; ?>
