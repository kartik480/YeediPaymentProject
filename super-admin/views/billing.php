<?php include __DIR__.'/_super_head.php';
$settings = $settings ?? [];
$form = $form ?? $settings;
?>
<h1 class="sa-title">Merchant Billing &amp; Commission</h1>
<?php if (!empty($error)): ?><div class="sa-error"><?=e($error)?></div><?php endif; ?>

<div class="sa-panel sa-billing-form">
    <h2 class="sa-section-title">GST / Company Settings</h2>
    <p class="sa-billing-warn">⚠ VERIFY legal name, GSTIN, registered state code and GST rate with your CA/tax adviser before live billing.</p>
    <form method="post" action="<?=e(url('/super-admin/billing/settings'))?>">
        <?=csrf_field()?>
        <div class="sa-form-grid">
            <label>Legal Name
                <input name="legal_name" value="<?=e($form['legal_name'] ?? '')?>" required>
            </label>
            <label>GSTIN <span class="sa-hint-red">CHANGE REQUIRED</span>
                <input name="gstin" value="<?=e($form['gstin'] ?? '')?>" maxlength="15" placeholder="15-character GSTIN">
            </label>
            <label>Registered State Code (2 digits) <span class="sa-hint-red">CHANGE REQUIRED</span>
                <input name="registered_state_code" value="<?=e($form['registered_state_code'] ?? '')?>" maxlength="2" inputmode="numeric" placeholder="36">
            </label>
            <label>GST Rate % <span class="sa-hint-red">VERIFY</span>
                <input name="gst_rate" value="<?=e($form['gst_rate'] ?? '18.000')?>" inputmode="decimal">
            </label>
            <label>GST Summary Email <span class="sa-hint-red">CHANGE REQUIRED</span>
                <input type="email" name="gst_summary_email" value="<?=e($form['gst_summary_email'] ?? '')?>">
            </label>
            <label>Accounts Email
                <input type="email" name="accounts_email" value="<?=e($form['accounts_email'] ?? '')?>">
            </label>
            <label>Fee Invoice Prefix
                <input name="fee_invoice_prefix" value="<?=e($form['fee_invoice_prefix'] ?? 'YDFEE')?>" maxlength="32">
            </label>
            <label>Duplicate Scan Window (minutes)
                <input name="duplicate_scan_minutes" type="number" min="0" value="<?=e((string)($form['duplicate_scan_minutes'] ?? '10'))?>">
            </label>
        </div>
        <button type="submit" class="sa-filter-btn sa-save-settings-btn">SAVE SETTINGS</button>
        <p class="sa-note sa-billing-note">A repeated open from the same device/IP fingerprint inside the duplicate window is logged but not charged again.</p>
    </form>
</div>

<div class="sa-panel sa-links-panel">
    <h2 class="sa-section-title">Customer Commission Plans</h2>
    <div class="sa-txn-table-wrap scroll">
        <table class="sa-txn-table sa-billing-table">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Plan</th>
                    <th>Fixed Fee</th>
                    <th>% Fee</th>
                    <th>Setup</th>
                    <th>Merchant QR</th>
                    <th>Manage</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($plans ?? [] as $p): ?>
                <tr>
                    <td><?=e($p['name'])?><div class="sa-sub mono"><?=e($p['code'])?></div></td>
                    <td><?=e(strtoupper(trim((string)($p['plan'] ?? 'starter'))))?></td>
                    <td><?=e(BillingService::moneyDisplay($p['fixed_fee'] ?? null))?></td>
                    <td><?=e(BillingService::pctDisplay($p['pct_fee'] ?? null))?></td>
                    <td><?=e(BillingService::moneyDisplay($p['setup_fee'] ?? null))?></td>
                    <td><?=e(BillingService::moneyDisplay($p['merchant_qr_fee'] ?? null))?></td>
                    <td><a class="sa-link-btn" href="<?=e(admin_url('/admin/customers'))?>" target="_blank" rel="noopener">Admin</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($plans)): ?>
                <tr><td colspan="7" class="sa-empty">No customers yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__.'/_super_foot.php'; ?>
