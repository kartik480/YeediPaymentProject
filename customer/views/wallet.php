<?php
include __DIR__.'/_dash_head.php';
$balance = $balance ?? ['available_balance' => 0, 'pending_balance' => 0];
$recharges = $recharges ?? [];
$withdrawals = $withdrawals ?? [];
?>
<div class="pay dash col-dash">
    <h1 class="col-title">Wallet</h1>
    <?php if (!empty($_GET['withdraw'])): ?><div class="ok">Withdrawal request <?=e((string)$_GET['withdraw'])?> submitted. Admin will process it shortly.</div><?php endif; ?>

    <div class="col-stats col-stats-3">
        <div class="col-stat">
            <div class="label">Available Balance</div>
            <div class="value">₹<?=amount_fmt($balance['available_balance'] ?? 0)?></div>
        </div>
        <div class="col-stat">
            <div class="label">Pending Withdrawals</div>
            <div class="value">₹<?=amount_fmt($balance['pending_balance'] ?? 0)?></div>
        </div>
        <div class="col-stat">
            <div class="label">Customer</div>
            <div class="value" style="font-size:1rem"><?=e($c['code'])?></div>
        </div>
    </div>

    <div class="col-panel">
        <div class="col-filter-actions" style="display:flex;gap:12px;flex-wrap:wrap">
            <a class="col-btn-gold" href="<?=e(url($custBase.'/wallet/recharge'))?>">Recharge</a>
            <a class="col-btn-outline" href="<?=e(url($custBase.'/wallet/withdraw'))?>">Withdraw</a>
        </div>
        <p class="col-note">Recharge your wallet via UPI bank QR. Withdrawals are sent to your bank account after admin approval.</p>
    </div>

    <div class="col-panel col-history">
        <h2 class="col-section-title">Recent recharges</h2>
        <div class="col-table-wrap">
            <table class="col-table">
                <tr>
                    <th>Date</th>
                    <th>Reference</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th></th>
                </tr>
                <?php foreach ($recharges as $r): ?>
                <tr>
                    <td><?=e($r['created_at'])?></td>
                    <td class="mono"><?=e($r['reference'])?></td>
                    <td>₹<?=amount_fmt($r['amount'])?></td>
                    <td class="<?=e(WalletService::statusClass((string)$r['status']))?>"><?=e($r['status'])?></td>
                    <td><?php if (($r['status'] ?? '') === 'PENDING' && !empty($r['txn_reference'])): ?>
                        <a href="<?=e(url($custBase.'/wallet/recharge/'.$r['txn_reference']))?>">Pay</a>
                    <?php endif; ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recharges)): ?>
                <tr><td colspan="5" class="muted">No recharges yet.</td></tr>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <div class="col-panel col-history">
        <h2 class="col-section-title">Withdrawal requests</h2>
        <div class="col-table-wrap">
            <table class="col-table">
                <tr>
                    <th>Date</th>
                    <th>Reference</th>
                    <th>Amount</th>
                    <th>Bank</th>
                    <th>Status</th>
                </tr>
                <?php foreach ($withdrawals as $w): ?>
                <tr>
                    <td><?=e($w['created_at'])?></td>
                    <td class="mono"><?=e($w['reference'])?></td>
                    <td>₹<?=amount_fmt($w['amount'])?></td>
                    <td><?=e($w['bank_name'])?> · <?=e(mask_account($w['account_number'] ?? ''))?></td>
                    <td class="<?=e(WalletService::statusClass((string)$w['status']))?>"><?=e($w['status'])?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($withdrawals)): ?>
                <tr><td colspan="5" class="muted">No withdrawal requests yet.</td></tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>
<?php include __DIR__.'/_dash_foot.php'; ?>
