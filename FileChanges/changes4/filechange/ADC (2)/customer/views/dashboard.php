<?php
include __DIR__.'/_dash_head.php';
$period = $period ?? 'all';
$status = $status ?? '';
$start = $start ?? '';
$end = $end ?? '';
$stQs = http_build_query(array_filter([
    'period' => $period !== 'all' ? $period : '',
    'status' => $status,
    'start' => $start,
    'end' => $end,
], static fn($v) => $v !== '' && $v !== null));
$exportQs = $stQs !== '' ? '?'.$stQs : '';
$totals = $totals ?? ['confirmed' => 0, 'pending' => 0, 'refunded' => 0, 'settled' => 0, 'ledger_balance' => 0];
?>
<div class="pay dash col-dash">
    <h1 class="col-title">Collections</h1>
    <p class="col-note"><b>Account totals below are all-time totals.</b> The Status/Period/From/To filters apply to Transaction Status History and exports only.</p>

    <div class="col-stats">
        <div class="col-stat">
            <div class="label">Confirmed Collections</div>
            <div class="value">₹<?=amount_fmt($totals['confirmed'] ?? 0)?></div>
        </div>
        <div class="col-stat">
            <div class="label">Pending Amount</div>
            <div class="value">₹<?=amount_fmt($totals['pending'] ?? 0)?></div>
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

    <div class="col-panel col-statement">
        <p class="col-note">Ledger Balance = bank-confirmed SUCCESS collections − SETTLED payouts. PENDING, FAILED and EXPIRED attempts do not change the ledger. FAILED is not treated as a refund. This is a YEEDIPAY internal ledger, not the pooled bank account live balance.</p>
        <a class="col-btn-gold" href="<?=e(url($custBase.'/statement'))?>">VIEW ACCOUNT STATEMENT</a>
    </div>

    <div class="col-panel col-filters">
        <form method="get" action="<?=e(url($custBase))?>" class="col-filter-form">
            <label>Status
                <select name="status">
                    <option value="">All</option>
                    <?php foreach (['SUCCESS', 'PENDING', 'FAILED', 'EXPIRED'] as $x): ?>
                    <option value="<?=$x?>" <?=($status ?? '') === $x ? 'selected' : ''?>><?=$x?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Period
                <select name="period">
                    <?php foreach (['all' => 'All', 'day' => 'Today', 'week' => 'This week', 'month' => 'This month', 'custom' => 'Custom'] as $k => $label): ?>
                    <option value="<?=e($k)?>" <?=($period ?? 'all') === $k ? 'selected' : ''?>><?=e($label)?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>From
                <input type="date" name="start" value="<?=e($start)?>">
            </label>
            <label>To
                <input type="date" name="end" value="<?=e($end)?>">
            </label>
            <div class="col-filter-actions">
                <button type="submit" class="col-btn-gold">Filter</button>
                <a class="col-btn-gray" href="<?=e(url($custBase.'/statement.csv'.$exportQs))?>">Transaction Excel</a>
                <a class="col-btn-gray" href="<?=e(url($custBase.'/statement.pdf'.$exportQs))?>">Transaction PDF</a>
            </div>
        </form>
    </div>

    <div class="col-panel col-history">
        <h2 class="col-section-title">Transaction Status History</h2>
        <div class="scroll">
            <table class="col-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Txn</th>
                        <th>Ref</th>
                        <th>UTR</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Service</th>
                        <th>Invoice</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($txns as $t): ?>
                    <tr>
                        <td><?=e($t['created_at'])?></td>
                        <td class="mono"><?=e($t['reference'])?></td>
                        <td class="mono"><?=e(trim((string)($t['invoice_ref'] ?? '')) !== '' ? $t['invoice_ref'] : '—')?></td>
                        <td class="mono"><?=e($t['customer_reported_utr'] ?? $t['rrn'] ?? '')?></td>
                        <td><?php $a = TransactionService::displayAmount($t); echo $a !== '' ? '₹'.e($a) : '—'; ?></td>
                        <td class="status-<?=e($t['status'])?>"><?=e($t['status'])?></td>
                        <td><?=e($t['service'] ?? 'UPI')?></td>
                        <td><?php if (strtoupper((string)$t['status']) === 'SUCCESS'): ?><div class="pay-invoice-actions" style="margin-top:0"><a class="col-btn-gray" href="<?=e(url('/p/'.$c['code'].'/result/'.$t['reference'].'/invoice.pdf'))?>" target="_blank" rel="noopener">Payment Success Invoice</a><?php $invoiceHref = url('/p/'.$c['code'].'/result/'.$t['reference'].'/invoice.pdf'); include __DIR__.'/_invoice_share.php'; ?></div><?php else: ?>—<?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($txns)): ?>
                    <tr><td colspan="8" class="muted">No transactions for this filter.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include __DIR__.'/_dash_foot.php'; ?>
