<?php include __DIR__.'/_head.php'; ?>
<div class="txn-page">
    <form class="txn-filters" method="post" action="<?=e(url('/admin/settlements'))?>">
        <?=csrf_field()?>
        <input type="date" name="start" value="<?=e($start??'')?>" required>
        <input type="date" name="end" value="<?=e($end??'')?>" required>
        <input name="fee" inputmode="decimal" placeholder="Fee" value="<?=e($fee??'')?>">
        <input name="tax" inputmode="decimal" placeholder="Tax" value="<?=e($tax??'')?>">
        <button type="submit" class="br-save">Request Settlement</button>
    </form>
    <div class="scroll">
        <table class="br-table">
            <tr>
                <th>Ref</th>
                <th>Range</th>
                <th>Count</th>
                <th>Gross</th>
                <th>Net</th>
                <th>Status</th>
                <th>Bank Ref</th>
                <th>Action</th>
            </tr>
            <?php foreach ($rows as $s): ?>
            <tr>
                <td class="mono"><?=e($s['reference'])?></td>
                <td><?=e(SettlementService::rangeLabel($s))?></td>
                <td><?=e($s['txn_count'])?></td>
                <td>₹<?=e(amount_fmt($s['gross_amount']))?></td>
                <td>₹<?=e(amount_fmt($s['net_amount']))?></td>
                <td class="status-<?=e($s['status'])?>"><?=e($s['status'])?></td>
                <td class="mono"><?=e($s['bank_ref'] ?? '')?></td>
                <td>
                    <div class="row">
                        <a class="btn btn2" href="<?=e(url('/admin/settlements/'.$s['id']))?>">View</a>
                        <?php if ($s['status']==='REQUESTED'): ?>
                        <form method="post" action="<?=e(url('/admin/settlements/'.$s['id'].'/settle'))?>" class="row">
                            <?=csrf_field()?>
                            <input name="bank_ref" placeholder="Bank Ref">
                            <button type="submit" class="br-save">Settle</button>
                        </form>
                        <form method="post" action="<?=e(url('/admin/settlements/'.$s['id'].'/cancel'))?>">
                            <?=csrf_field()?>
                            <button type="submit" class="btn2">Cancel</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
            <tr><td colspan="8" class="muted">No settlement batches yet. Choose a date range and request one.</td></tr>
            <?php endif; ?>
        </table>
    </div>
</div>
<?php include __DIR__.'/_foot.php'; ?>
