<?php include __DIR__.'/_super_head.php';
$month = $month ?? GstService::normalizeMonth('');
$summary = $summary ?? GstService::emptySummary($month);
$history = $history ?? [];
?>
<h1 class="sa-title">Monthly GST Summary</h1>
<?php if (!empty($error)): ?><div class="sa-error"><?=e($error)?></div><?php endif; ?>

<div class="sa-panel sa-gst-open">
    <form method="get" action="<?=e(url('/super-admin/gst'))?>" class="sa-gst-month-form">
        <label>GST Month
            <input type="month" name="month" value="<?=e($month)?>">
        </label>
        <button type="submit" class="sa-filter-btn">OPEN MONTH</button>
    </form>
    <p class="sa-billing-warn">● This page calculates GST from YEEDIPAY fee entries only. Final GST payable can differ because of eligible ITC, credit/debit notes, RCM, place-of-supply and return adjustments. Verify before filing/payment.</p>
</div>

<div class="sa-stats sa-stats-5">
    <div class="sa-stat">
        <div class="label">Taxable Fees</div>
        <div class="value">₹<?=amount_fmt($summary['taxable_fees'] ?? 0)?></div>
    </div>
    <div class="sa-stat">
        <div class="label">Output GST</div>
        <div class="value">₹<?=amount_fmt($summary['output_gst'] ?? 0)?></div>
    </div>
    <div class="sa-stat">
        <div class="label">Eligible ITC</div>
        <div class="value">₹<?=amount_fmt($summary['eligible_itc'] ?? 0)?></div>
    </div>
    <div class="sa-stat">
        <div class="label">Adjustment</div>
        <div class="value">₹<?=amount_fmt($summary['other_adjustment'] ?? 0)?></div>
    </div>
    <div class="sa-stat">
        <div class="label">Estimated Net Payable</div>
        <div class="value">₹<?=amount_fmt($summary['net_payable'] ?? 0)?></div>
    </div>
</div>

<div class="sa-panel sa-gst-detail">
    <p class="sa-gst-breakdown">
        CGST ₹<?=amount_fmt($summary['cgst'] ?? 0)?> |
        SGST ₹<?=amount_fmt($summary['sgst'] ?? 0)?> |
        IGST ₹<?=amount_fmt($summary['igst'] ?? 0)?> |
        Fee Entries <?=e((string)($summary['fee_entry_count'] ?? 0))?>
    </p>
    <form method="post" action="<?=e(url('/super-admin/gst/save'))?>">
        <?=csrf_field()?>
        <input type="hidden" name="month" value="<?=e($month)?>">
        <div class="sa-form-grid">
            <label>Eligible ITC for this month
                <input name="eligible_itc" inputmode="decimal" value="<?=e(amount_fmt($summary['eligible_itc'] ?? 0))?>">
            </label>
            <label>Other GST Adjustment (+/-)
                <input name="other_adjustment" inputmode="decimal" value="<?=e(amount_fmt($summary['other_adjustment'] ?? 0))?>">
            </label>
        </div>
        <div class="sa-gst-actions">
            <button type="submit" class="sa-filter-btn">SAVE &amp; RECALCULATE</button>
            <button type="submit" formaction="<?=e(url('/super-admin/gst/regenerate'))?>" class="sa-btn-gray">REGENERATE</button>
            <button type="submit" formaction="<?=e(url('/super-admin/gst/email'))?>" class="sa-btn-green">EMAIL NOW</button>
        </div>
    </form>
    <div class="sa-gst-export">
        <a class="sa-btn-gray" href="<?=e(url('/super-admin/gst/export.csv?month='.rawurlencode($month)))?>">Excel</a>
        <a class="sa-btn-gray" href="<?=e(url('/super-admin/gst/export.pdf?month='.rawurlencode($month)))?>">PDF</a>
    </div>
    <p class="sa-note sa-gst-meta">
        Generated: <?=e(GstService::formatGeneratedAt($summary['generated_at'] ?? null))?> |
        Emailed: <?=e(GstService::formatGeneratedAt($summary['emailed_at'] ?? null))?>
    </p>
</div>

<div class="sa-panel sa-links-panel">
    <h2 class="sa-section-title">Monthly History</h2>
    <div class="sa-txn-table-wrap scroll">
        <table class="sa-txn-table sa-gst-history">
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Taxable</th>
                    <th>Output GST</th>
                    <th>ITC</th>
                    <th>Net Estimate</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($history as $h): ?>
                <tr>
                    <td><a class="sa-month-link" href="<?=e(url('/super-admin/gst?month='.rawurlencode((string)$h['month_key'])))?>"><?=e((string)$h['month_key'])?></a></td>
                    <td>₹<?=amount_fmt($h['taxable_fees'] ?? 0)?></td>
                    <td>₹<?=amount_fmt($h['output_gst'] ?? 0)?></td>
                    <td>₹<?=amount_fmt($h['eligible_itc'] ?? 0)?></td>
                    <td><b>₹<?=amount_fmt($h['net_payable'] ?? 0)?></b></td>
                    <td><span class="sa-status-pill"><?=e(strtoupper((string)($h['status'] ?? 'GENERATED')))?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($history)): ?>
                <tr><td colspan="6" class="sa-empty">No GST summaries generated yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__.'/_super_foot.php'; ?>
