<?php include __DIR__.'/_super_head.php';
$form = $form ?? [
    'code' => '',
    'name' => '',
    'email' => '',
    'min_txn' => '101',
    'max_txn' => '200000',
    'daily_limit' => '0',
    'weekly_limit' => '0',
    'monthly_limit' => '0',
    'callback_url' => '',
];
?>
<?php if (!empty($error)): ?><div class="sa-error"><?=e($error)?></div><?php endif; ?>
<div class="sa-panel sa-customer-form">
    <h2 class="sa-section-title">Create Customer</h2>
    <form method="post" action="<?=e(url('/super-admin/customers'))?>">
        <?=csrf_field()?>
        <div class="sa-form-grid">
            <label>Customer ID / Ref
                <input name="code" value="<?=e($form['code'])?>" placeholder="Auto-generated if blank">
            </label>
            <label>Name
                <input name="name" value="<?=e($form['name'])?>" required>
            </label>
            <label>Email
                <input type="email" name="email" value="<?=e($form['email'])?>" placeholder="merchant@example.com">
            </label>
            <label>Login Password (10+ chars)
                <input type="password" name="password" autocomplete="new-password" minlength="10" required>
            </label>
            <label>Min Txn
                <input name="min_txn" inputmode="decimal" value="<?=e($form['min_txn'])?>">
            </label>
            <label>Max Txn
                <input name="max_txn" inputmode="decimal" value="<?=e($form['max_txn'])?>">
            </label>
            <label>Daily Limit (0=no cap)
                <input name="daily_limit" inputmode="decimal" value="<?=e($form['daily_limit'])?>">
            </label>
            <label>Weekly Limit
                <input name="weekly_limit" inputmode="decimal" value="<?=e($form['weekly_limit'])?>">
            </label>
            <label>Monthly Limit
                <input name="monthly_limit" inputmode="decimal" value="<?=e($form['monthly_limit'])?>">
            </label>
            <label>Service Callback URL (optional)
                <input name="callback_url" value="<?=e($form['callback_url'])?>" placeholder="https://merchant.example/payment-success">
            </label>
        </div>
        <button type="submit" class="sa-filter-btn sa-create-btn">Create Customer</button>
    </form>
</div>

<div class="sa-panel sa-links-panel">
    <h2 class="sa-section-title">Permanent Customer Links</h2>
    <p class="sa-links-note">The link never changes when you change/add bank routes.</p>
    <div class="sa-txn-table-wrap scroll">
        <table class="sa-txn-table sa-links-table">
            <thead>
                <tr>
                    <th>Ref</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Permanent Payment Link</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($customers ?? [] as $c): ?>
                <tr>
                    <td class="mono"><?=e($c['code'])?></td>
                    <td><?=e($c['name'])?></td>
                    <td><?=e(trim((string)($c['email'] ?? '')) !== '' ? $c['email'] : '—')?></td>
                    <td class="mono"><a class="sa-link-btn" href="<?=e(pay_url('/p/'.$c['code']))?>" target="_blank" rel="noopener"><?=e(pay_url('/p/'.$c['code']))?></a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($customers)): ?>
                <tr><td colspan="4" class="sa-empty">No customers yet. Create one above.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__.'/_super_foot.php'; ?>
