<?php include __DIR__.'/_payout_head.php'; ?>
<div class="po-head">
    <h1>KYB Documents</h1>
</div>
<div class="po-card po-plan-card">
    <form method="post" action="<?=e(url('/admin/payout/kyb'))?>" class="po-form-grid" enctype="multipart/form-data">
        <?=csrf_field()?>
        <label>Company
            <select name="company" required>
                <option value=""></option>
                <?php foreach ($tenants ?? [] as $t): ?>
                <option value="<?=e($t['code'])?>"><?=e($t['name'])?> (<?=e($t['code'])?>)</option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Type
            <select name="doc_type" required>
                <?php foreach (KybService::TYPES as $key => $label): ?>
                <option value="<?=e($key)?>"><?=e($label)?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>File<input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/*" required></label>
        <div class="po-plan-actions"><button class="po-save" type="submit">Add document</button></div>
    </form>
</div>
<div class="po-card po-tenant-table scroll">
    <table class="po-table po-tenant-list">
        <thead>
            <tr>
                <th>Company</th>
                <th>Type</th>
                <th>File</th>
                <th>Status</th>
                <th>Review</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows ?? [] as $r): ?>
            <tr>
                <td><?=e($r['customer_name'])?></td>
                <td><?=e($r['doc_type'])?></td>
                <td><a href="<?=e(url('/admin/payout/kyb/'.(int)$r['id'].'/file'))?>" target="_blank" rel="noopener"><?=e($r['file_orig'] ?: 'Open')?></a></td>
                <td><?=e($r['status'])?></td>
                <td>
                    <form method="post" action="<?=e(url('/admin/payout/kyb/'.(int)$r['id'].'/review'))?>" class="po-inline">
                        <?=csrf_field()?>
                        <input type="hidden" name="decision" value="APPROVED">
                        <button type="submit" class="po-link-btn">Approve</button>
                    </form>
                    <form method="post" action="<?=e(url('/admin/payout/kyb/'.(int)$r['id'].'/review'))?>" class="po-inline">
                        <?=csrf_field()?>
                        <input type="hidden" name="decision" value="REJECTED">
                        <button type="submit" class="po-link-btn">Reject</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__.'/_payout_foot.php'; ?>
