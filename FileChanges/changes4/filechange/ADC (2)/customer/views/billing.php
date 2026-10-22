<?php
include __DIR__.'/_dash_head.php';
$summary = $summary ?? ['total_fees' => 0, 'total_gst' => 0, 'qr_scans' => 0, 'plan' => 'NONE'];
$entries = $entries ?? [];
?>
<div class="pay dash col-dash bill-dash">
    <h1 class="col-title">Platform Fees &amp; GST</h1>

    <div class="col-stats cust-bill-stats">
        <div class="col-stat">
            <div class="label">Total Fees</div>
            <div class="value">₹<?=amount_fmt($summary['total_fees'] ?? 0)?></div>
        </div>
        <div class="col-stat">
            <div class="label">GST Included/Added</div>
            <div class="value">₹<?=amount_fmt($summary['total_gst'] ?? 0)?></div>
        </div>
        <div class="col-stat">
            <div class="label">Chargeable QR Scans</div>
            <div class="value"><?=e((string)($summary['qr_scans'] ?? 0))?></div>
        </div>
        <div class="col-stat">
            <div class="label">Plan</div>
            <div class="value cust-plan-value"><?=e($summary['plan'] ?? 'NONE')?></div>
        </div>
    </div>

    <div class="col-panel bill-note-panel">
        <p class="col-note bill-note">Your billing plan and each GST fee entry are shown below. Platform fees also appear as debits in your Account Statement.</p>
        <div class="col-filter-actions bill-export-actions">
            <a class="col-btn-gray" href="<?=e(url($custBase.'/billing.csv'))?>">Excel</a>
            <a class="col-btn-gray" href="<?=e(url($custBase.'/billing.pdf'))?>">PDF</a>
        </div>
    </div>

    <div class="col-panel col-history">
        <div class="scroll">
            <table class="col-table bill-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Invoice</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Taxable</th>
                        <th>GST</th>
                        <th>Gross</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($entries as $row): ?>
                    <tr>
                        <td><?=e($row['date'])?></td>
                        <td class="mono"><?=e($row['invoice'] !== '' ? $row['invoice'] : '—')?></td>
                        <td><?=e($row['type'])?></td>
                        <td><?=e($row['description'])?></td>
                        <td>₹<?=e(amount_fmt($row['taxable'] ?? 0))?></td>
                        <td>₹<?=e(amount_fmt($row['gst'] ?? 0))?></td>
                        <td>₹<?=e(amount_fmt($row['gross'] ?? 0))?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($entries)): ?>
                    <tr><td colspan="7" class="muted">No fee entries yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include __DIR__.'/_dash_foot.php'; ?>
