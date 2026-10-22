<?php
include __DIR__.'/_dash_head.php';
$balance = $balance ?? ['available_balance' => 0, 'pending_balance' => 0];
?>
<div class="pay dash col-dash">
    <h1 class="col-title">Withdraw from Wallet</h1>

    <div class="col-stats col-stats-2">
        <div class="col-stat">
            <div class="label">Available to withdraw</div>
            <div class="value">₹<?=amount_fmt($balance['available_balance'] ?? 0)?></div>
        </div>
    </div>

    <div class="col-panel">
        <?php if (!empty($error)): ?><div class="error"><?=e($error)?></div><?php endif; ?>
        <?php if (!empty($ok)): ?><div class="ok"><?=e($ok)?></div><?php endif; ?>
        <form method="post" action="<?=e(url($custBase.'/wallet/withdraw'))?>" class="col-filter-form stmt-filter-form">
            <?=csrf_field()?>
            <label>Amount (INR)
                <input type="number" name="amount" min="1" step="0.01" required placeholder="Enter amount">
            </label>
            <label>Account holder name
                <input type="text" name="account_holder" value="<?=e($c['name'] ?? '')?>" required maxlength="180">
            </label>
            <label>Bank name
                <input type="text" name="bank_name" required maxlength="120" placeholder="e.g. HDFC Bank">
            </label>
            <label>Account number
                <input type="text" name="account_number" required maxlength="32" inputmode="numeric" autocomplete="off">
            </label>
            <label>IFSC code
                <input type="text" name="ifsc" required maxlength="16" style="text-transform:uppercase" placeholder="e.g. HDFC0001234">
            </label>
            <div class="col-filter-actions" style="grid-column:1/-1">
                <button type="submit" class="col-btn-gold">Request withdrawal</button>
                <a class="col-btn-outline" href="<?=e(url($custBase.'/wallet'))?>">Cancel</a>
            </div>
        </form>
        <p class="col-note">Your request will be reviewed by admin. Amount is reserved from your wallet until approved or rejected.</p>
    </div>
</div>
<?php include __DIR__.'/_dash_foot.php'; ?>
