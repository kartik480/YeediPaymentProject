<?php include __DIR__.'/_super_head.php'; ?>
<div class="sa-card">
    <h1>Withdrawal <?=e($withdrawal['reference'] ?? '')?></h1>
    <p><b><?=e($withdrawal['customer_name'] ?? '')?></b> · <span class="mono"><?=e($withdrawal['customer_code'] ?? '')?></span>
        <?php if (!empty($withdrawal['customer_phone'])): ?> · <?=e($withdrawal['customer_phone'])?><?php endif; ?>
    </p>

    <div class="sa-stats sa-stats-3">
        <div class="sa-stat"><div class="label">Amount</div><div class="value">₹<?=amount_fmt($withdrawal['amount'] ?? 0)?></div></div>
        <div class="sa-stat"><div class="label">Status</div><div class="value"><?=e($withdrawal['status'] ?? '')?></div></div>
        <div class="sa-stat"><div class="label">Requested</div><div class="value" style="font-size:.95rem"><?=e($withdrawal['created_at'] ?? '')?></div></div>
    </div>

    <div class="sa-card" style="margin:14px 0;background:#121212">
        <h2>Bank account</h2>
        <p><b><?=e($withdrawal['account_holder'] ?? '')?></b></p>
        <p><?=e($withdrawal['bank_name'] ?? '')?></p>
        <p class="mono">A/c <?=e($withdrawal['account_number'] ?? '')?> · IFSC <?=e($withdrawal['ifsc'] ?? '')?></p>
    </div>

    <form method="post" action="<?=e(url('/super-admin/wallet/withdrawals/'.$withdrawal['id'].'/update'))?>" class="sa-form-grid">
        <?=csrf_field()?>
        <label>Status
            <select name="status">
                <?php foreach (WalletService::WITHDRAW_STATUSES as $s): ?>
                <option value="<?=e($s)?>" <?=($withdrawal['status'] ?? '') === $s ? 'selected' : ''?>><?=e($s)?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Bank transfer reference
            <input name="bank_ref" value="<?=e($withdrawal['bank_ref'] ?? '')?>" placeholder="UTR / NEFT ref when paid">
        </label>
        <label style="grid-column:1/-1">Admin note
            <textarea name="admin_note" rows="3"><?=e($withdrawal['admin_note'] ?? '')?></textarea>
        </label>
        <button type="submit">Update withdrawal</button>
    </form>

    <?php if (!empty($withdrawal['processed_by'])): ?>
    <p class="sa-note">Last processed by <?=e($withdrawal['processed_by'])?><?php if (!empty($withdrawal['processed_at'])): ?> on <?=e($withdrawal['processed_at'])?><?php endif; ?></p>
    <?php endif; ?>

    <p style="margin-top:16px"><a href="<?=e(url('/super-admin/wallet?tab=withdrawals'))?>">← All wallet transactions</a></p>
</div>
<?php include __DIR__.'/_super_foot.php'; ?>
