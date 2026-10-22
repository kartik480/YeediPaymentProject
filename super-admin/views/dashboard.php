<?php include __DIR__.'/_super_head.php'; ?>
<h1 class="sa-title">Merchant Collections Dashboard</h1>

<div class="sa-stats">
    <div class="sa-stat">
        <div class="label">Today Attempts</div>
        <div class="value"><?=e((string)($stats['today_attempts'] ?? 0))?></div>
    </div>
    <div class="sa-stat">
        <div class="label">Today Pending</div>
        <div class="value">₹<?=amount_fmt($stats['today_pending'] ?? 0)?></div>
    </div>
    <div class="sa-stat">
        <div class="label">Today Success</div>
        <div class="value">₹<?=amount_fmt($stats['today_success'] ?? 0)?></div>
    </div>
    <div class="sa-stat">
        <div class="label">All Customer Ledger Balance</div>
        <div class="value">₹<?=amount_fmt($stats['ledger_balance'] ?? 0)?></div>
    </div>
</div>

<div class="sa-panel">
    <p>Current operating mode: <b><?=e($stats['operating_mode'] ?? '—')?></b></p>
    <p class="sa-warn">● SUCCESS must be clicked only after the credit is visible in the bank/UPI account and the UTR matches.</p>
    <a class="sa-btn" href="<?=e(url('/super-admin/customer-accounts'))?>">OPEN CUSTOMER ACCOUNTS</a>
    <p class="sa-note">Customer Ledger Balance is the YEEDIPAY internal collection ledger, not the live balance of the pooled bank account.</p>
</div>
<?php include __DIR__.'/_super_foot.php'; ?>
