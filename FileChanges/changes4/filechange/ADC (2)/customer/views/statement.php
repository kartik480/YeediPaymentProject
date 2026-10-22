<?php
include __DIR__.'/_dash_head.php';
$start = $start ?? '';
$end = $end ?? '';
$stQs = http_build_query(array_filter([
    'start' => $start,
    'end' => $end,
], static fn($v) => $v !== '' && $v !== null));
$exportQs = $stQs !== '' ? '?'.$stQs : '';
$totals = $totals ?? ['confirmed' => 0, 'refunded' => 0, 'settled' => 0, 'ledger_balance' => 0];
$rows = $rows ?? [];
?>
<div class="pay dash col-dash stmt-dash">
    <h1 class="col-title">Account Statement</h1>

    <div class="col-stats stmt-stats">
        <div class="col-stat">
            <div class="label">Confirmed</div>
            <div class="value">₹<?=amount_fmt($totals['confirmed'] ?? 0)?></div>
        </div>
        <div class="col-stat">
            <div class="label">Refunded</div>
            <div class="value">₹<?=amount_fmt($totals['refunded'] ?? 0)?></div>
        </div>
        <div class="col-stat">
            <div class="label">Settled</div>
            <div class="value">₹<?=amount_fmt($totals['settled'] ?? 0)?></div>
        </div>
        <div class="col-stat">
            <div class="label">Ledger Balance</div>
            <div class="value">₹<?=amount_fmt($totals['ledger_balance'] ?? 0)?></div>
        </div>
    </div>

    <div class="col-panel col-filters stmt-filters">
        <form method="get" action="<?=e(url($custBase.'/statement'))?>" class="col-filter-form stmt-filter-form">
            <label>From
                <input type="date" name="start" value="<?=e($start)?>">
            </label>
            <label>To
                <input type="date" name="end" value="<?=e($end)?>">
            </label>
            <div class="col-filter-actions stmt-filter-actions">
                <button type="submit" class="col-btn-gold">Filter</button>
                <a class="col-btn-gray" href="<?=e(url($custBase.'/statement.csv'.$exportQs))?>">Excel</a>
                <a class="col-btn-gray" href="<?=e(url($custBase.'/statement.pdf'.$exportQs))?>">PDF</a>
            </div>
        </form>
        <p class="col-note stmt-note">Only bank-confirmed SUCCESS payments create credits. SETTLED payouts create debits. PENDING, FAILED and EXPIRED attempts do not change this balance; FAILED is never treated as a refund.</p>
    </div>

    <div class="col-panel col-history">
        <div class="scroll">
            <table class="col-table stmt-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Reference</th>
                        <th>UTR</th>
                        <th>Credit</th>
                        <th>Debit</th>
                        <th>Balance</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?=e($r['date'])?></td>
                        <td><?=e($r['type'])?></td>
                        <td class="mono"><?=e($r['reference'])?></td>
                        <td class="mono"><?=e($r['utr'] !== '' ? $r['utr'] : '—')?></td>
                        <td><?=($r['credit'] ?? 0) > 0 ? '₹'.e(amount_fmt($r['credit'])) : '—'?></td>
                        <td><?=($r['debit'] ?? 0) > 0 ? '₹'.e(amount_fmt($r['debit'])) : '—'?></td>
                        <td>₹<?=e(amount_fmt($r['balance'] ?? 0))?></td>
                        <td><?=e($r['note'] ?? '')?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="8" class="muted">No ledger entries for this filter.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include __DIR__.'/_dash_foot.php'; ?>
