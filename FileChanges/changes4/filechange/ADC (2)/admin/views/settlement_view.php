<?php include __DIR__.'/_head.php'; ?>
<div class="txn-page">
    <div class="row">
        <h2 class="grow"><?=e($s['reference'])?></h2>
        <a class="txn-excel" href="<?=e(url('/admin/settlements'))?>">Back</a>
    </div>
    <p><?=e(SettlementService::rangeLabel($s))?> · <?=e($s['txn_count'])?> payments · Gross ₹<?=e(amount_fmt($s['gross_amount']))?> · Fee ₹<?=e(amount_fmt($s['fee_amount']))?> · Tax ₹<?=e(amount_fmt($s['tax_amount']))?> · Net ₹<?=e(amount_fmt($s['net_amount']))?></p>
    <p>Status: <b class="status-<?=e($s['status'])?>"><?=e($s['status'])?></b><?php if (!empty($s['bank_ref'])): ?> · Bank ref: <span class="mono"><?=e($s['bank_ref'])?></span><?php endif; ?></p>
    <div class="scroll">
        <table class="br-table">
            <tr><th>Ref</th><th>Customer</th><th>Bank</th><th>Amount</th><th>Status</th><th>Settlement</th></tr>
            <?php foreach ($items as $t): ?>
            <tr>
                <td class="mono"><?=e($t['reference'])?></td>
                <td><?=e($t['customer_name'])?><div class="br-code"><?=e($t['customer_code'])?></div></td>
                <td><?=e($t['bank_name'])?></td>
                <td><?php $amt=TransactionService::displayAmount($t); echo $amt!==''?'₹'.e($amt):'—'; ?></td>
                <td class="status-<?=e($t['status'])?>"><?=e($t['status'])?></td>
                <td><?=e($t['settlement_status'] ?? '')?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<?php include __DIR__.'/_foot.php'; ?>
