<?php include __DIR__.'/_payout_head.php'; ?>
<div class="po-head">
    <div>
        <h1>Support</h1>
        <p class="po-sub">Operator help for payout processing and reconciliation exceptions.</p>
    </div>
    <a class="po-btn" href="<?=e(url('/admin/audit'))?>">Open audit</a>
</div>
<div class="po-card">
    <div class="po-alert"><b>Control rule:</b> Never mark a payout successful only because a bank file was generated or uploaded. Success is recorded only after bank MIS/status evidence is reconciled.</div>
    <p>Use <a href="<?=e(url('/admin/transactions'))?>">Transactions</a> for pending collections, <a href="<?=e(url('/admin/settlements'))?>">Settlements</a> for payout batches, and <a href="<?=e(url('/admin/reconciliation'))?>">Reconciliation</a> for bank evidence.</p>
</div>
<?php include __DIR__.'/_payout_foot.php'; ?>
