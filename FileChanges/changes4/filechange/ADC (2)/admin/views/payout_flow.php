<?php include __DIR__.'/_payout_head.php'; ?>
<div class="po-head">
    <div>
        <h1><?=e($heading)?></h1>
        <p class="po-sub"><?=e($subtitle)?></p>
    </div>
    <div class="po-head-actions">
        <?php if (!empty($primary_href)): ?>
        <a class="po-btn po-btn-primary" href="<?=e(url($primary_href))?>"><?=e($primary_label ?? 'Continue')?></a>
        <?php endif; ?>
        <a class="po-btn" href="<?=e(url('/admin/payout'))?>">Back to Home</a>
    </div>
</div>
<div class="po-card" style="margin-bottom:16px">
    <div class="po-card-h">
        <h2>Operational control</h2>
        <span class="po-badge">Control rule</span>
    </div>
    <p class="po-hint">Bank evidence is the final source of truth.</p>
    <div class="po-alert"><b>Important:</b> Never mark a payout successful only because a bank file was generated or uploaded. Success is recorded only after bank MIS/status evidence is reconciled.</div>
    <?php include __DIR__.'/_payout_steps.php'; ?>
</div>
<?php if (!empty($stats)): ?>
<div class="po-stats">
    <?php foreach ($stats as $st): ?>
    <div class="po-stat">
        <div class="k"><?=e($st['k'])?></div>
        <div class="n"><?=e($st['n'])?></div>
        <div class="s"><?=e($st['s'])?></div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php if (!empty($batches)): ?>
<div class="po-card scroll" style="margin-bottom:16px">
    <h2>Payout batches</h2>
    <p class="po-hint"><?=e($batch_hint ?? 'Settlement batches in this stage.')?></p>
    <table class="po-table">
        <tr><th>Reference</th><th>Range</th><th>Count</th><th>Net</th><th>Status</th><th>Bank ref</th></tr>
        <?php foreach ($batches as $s): ?>
        <tr>
            <td class="mono"><a href="<?=e(url('/admin/settlements/'.$s['id']))?>"><?=e($s['reference'])?></a></td>
            <td><?=e($s['range_start'])?> → <?=e($s['range_end'])?></td>
            <td><?=e((string)$s['txn_count'])?></td>
            <td>₹<?=amount_fmt($s['net_amount'] ?? 0)?></td>
            <td><?=e($s['status'])?></td>
            <td class="mono"><?=e($s['bank_ref'] ?? '—')?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>
<div class="po-card scroll">
    <h2><?=e($txn_title ?? 'Items')?></h2>
    <p class="po-hint"><?=e($txn_hint ?? '')?></p>
    <table class="po-table">
        <tr><th>Ref</th><th>Tenant</th><th>Bank</th><th>Amount</th><th>UTR</th><th>Settlement</th><th>Date</th></tr>
        <?php foreach ($txns ?? [] as $t): ?>
        <tr>
            <td class="mono"><?=e($t['reference'])?></td>
            <td><?=e($t['customer_name'])?> <span class="po-muted mono"><?=e($t['customer_code'])?></span></td>
            <td><?=e($t['bank_name'])?></td>
            <td>₹<?=e(TransactionService::displayAmount($t))?></td>
            <td class="mono"><?=e($t['rrn'] ?: ($t['customer_reported_utr'] ?? '—'))?></td>
            <td><?=e($t['settlement_status'] ?? 'UNSETTLED')?></td>
            <td><?=e($t['created_at'])?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($txns)): ?><tr><td colspan="7" class="po-muted"><?=e($empty ?? 'No items in this stage.')?></td></tr><?php endif; ?>
    </table>
</div>
<?php include __DIR__.'/_payout_foot.php'; ?>
