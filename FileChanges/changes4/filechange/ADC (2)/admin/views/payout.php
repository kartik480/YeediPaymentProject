<?php include __DIR__.'/_payout_head.php'; ?>
<div class="po-head">
    <div>
        <h1>Home</h1>
        <p class="po-sub">Monitor payout activity, bank processing and reconciliation health.</p>
    </div>
    <a class="po-btn" href="<?=e(url('/admin/payout/reports'))?>">View Reports</a>
</div>
<div class="po-stats">
    <div class="po-stat">
        <div class="k">TENANTS</div>
        <div class="n"><?=e((string)($tenants ?? 0))?></div>
        <div class="s">Current operating view</div>
    </div>
    <div class="po-stat">
        <div class="k">PAYOUTS</div>
        <div class="n"><?=e((string)($payouts ?? 0))?></div>
        <div class="s">Updated from platform records</div>
    </div>
    <div class="po-stat">
        <div class="k">SUCCESS</div>
        <div class="n"><?=e((string)($success ?? 0))?></div>
        <div class="s">Updated from platform records</div>
    </div>
</div>
<?php include __DIR__.'/_payout_foot.php'; ?>
