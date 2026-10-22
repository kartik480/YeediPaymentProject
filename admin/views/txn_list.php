<?php
include __DIR__.'/_head.php';
$qs = http_build_query([
    'status' => $status ?? '',
    'bank_id' => $bank_id ?? '',
    'q' => $q ?? '',
    'start' => $start ?? '',
    'end' => $end ?? '',
]);
?>
<div class="txn-page">
    <div class="warn">Until IndusInd webhook/status API is live: customer pays on the static QR (stays PENDING). Check the bank app, then Confirm SUCCESS here. That updates customer dashboard, admin totals, email, and merchant service activation.</div>
    <form class="txn-filters" method="get" action="<?=e(url('/admin/transactions'))?>">
        <select name="status">
            <option value="">All status</option>
            <?php foreach (['SUCCESS','PENDING','FAILED','EXPIRED'] as $x): ?>
                <option value="<?=$x?>" <?=($status??'')===$x?'selected':''?>><?=$x?></option>
            <?php endforeach; ?>
        </select>
        <select name="bank_id">
            <option value="">All banks</option>
            <?php foreach ($banks as $b): ?>
                <option value="<?=e($b['id'])?>" <?=(string)($bank_id??'')===(string)$b['id']?'selected':''?>><?=e($b['bank_name'])?></option>
            <?php endforeach; ?>
        </select>
        <input class="grow" name="q" value="<?=e($q??'')?>" placeholder="Customer code / name / phone / email">
        <input type="date" name="start" value="<?=e($start??'')?>">
        <input type="date" name="end" value="<?=e($end??'')?>">
        <button type="submit" class="br-save">Search</button>
        <a class="txn-excel" href="<?=e(url('/admin/transactions.csv').($qs!==''?'?'.$qs:''))?>">Excel</a>
    </form>
    <div class="scroll">
        <table class="br-table">
            <tr>
                <th>Date (IST)</th>
                <th>Ref</th>
                <th>Customer</th>
                <th>Buyer name</th>
                <th>Bank</th>
                <th>Amount</th>
                <th>Status</th>
                <th>UTR number</th>
                <th>Screenshot</th>
                <th>Service</th>
                <th>Settlement</th>
                <th>Actions</th>
            </tr>
            <?php foreach ($txns as $t): ?>
            <tr>
                <td><?=e(format_datetime_ist((string)($t['created_at'] ?? '')))?></td>
                <td class="mono"><?=e($t['reference'])?></td>
                <td><?=e($t['customer_name'])?><div class="br-code"><?=e($t['customer_code'])?><?=!empty($t['customer_email'])?' · '.e($t['customer_email']):''?></div></td>
                <td><?=e(TransactionService::displayBuyerName($t))?></td>
                <td><?=e($t['bank_name'])?></td>
                <td><?php $amt=TransactionService::displayAmount($t); echo $amt!==''?'₹'.e($amt):'—'; ?></td>
                <td class="status-<?=e($t['status'])?>"><?=e($t['status'])?></td>
                <td class="mono"><?=e(TransactionService::displayVerifiedUtr($t))?></td>
                <td><?php if (!empty($t['utr_screenshot_path'])): ?><a href="<?=e(url('/admin/transactions/'.$t['id'].'/screenshot'))?>" target="_blank" rel="noopener">View</a><?php else: ?>—<?php endif; ?></td>
                <td><?=e($t['service'] ?? 'UPI')?></td>
                <td><?=e($t['settlement_status'] ?? 'UNSETTLED')?></td>
                <td>
                    <?php if (strtoupper((string)$t['status'])==='PENDING'): ?>
                    <form method="post" action="<?=e(url('/admin/transactions/'.$t['id'].'/confirm'))?>" class="row" style="margin:0">
                        <?=csrf_field()?>
                        <input name="rrn" value="<?=e($t['customer_reported_utr']??'')?>" placeholder="UTR / RRN" style="min-width:120px">
                        <button name="status" value="SUCCESS">Confirm</button>
                        <button class="btn2" name="status" value="FAILED">Fail</button>
                    </form>
                    <?php else: ?>
                    <a class="btn btn2" href="<?=e(url('/admin/customers/'.$t['customer_code']))?>">View</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$txns): ?>
            <tr><td colspan="12" class="muted">No transactions for this search.</td></tr>
            <?php endif; ?>
        </table>
    </div>
</div>
<?php include __DIR__.'/_foot.php'; ?>
