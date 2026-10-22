<?php include __DIR__.'/_payout_head.php'; ?>
<div class="po-head">
    <h1>Source Bank Verification</h1>
</div>
<div class="po-card po-plan-card">
    <form method="post" action="<?=e(url('/admin/payout/bank-verification'))?>" class="po-form-grid">
        <?=csrf_field()?>
        <label>Company
            <select name="company" required>
                <option value=""></option>
                <?php foreach ($tenants ?? [] as $t): ?>
                <option value="<?=e($t['code'])?>"><?=e($t['name'])?> (<?=e($t['code'])?>)</option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Bank<input name="bank_name" required></label>
        <label>Account<input name="account_number" inputmode="numeric" required></label>
        <label>IFSC<input name="ifsc" maxlength="11" required></label>
        <div class="po-plan-actions"><button class="po-save" type="submit">Add source bank</button></div>
    </form>
</div>
<div class="po-card po-tenant-table scroll">
    <table class="po-table po-tenant-list">
        <thead>
            <tr>
                <th>Company</th>
                <th>Bank</th>
                <th>Account</th>
                <th>IFSC</th>
                <th>Status</th>
                <th>Verified</th>
                <th>Decision</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows ?? [] as $r):
            $verified = '—';
            if (!empty($r['verified_at'])) {
                try { $verified = (new DateTimeImmutable((string)$r['verified_at']))->format('d-m-Y H:i'); } catch (Throwable $e) { $verified = (string)$r['verified_at']; }
            }
        ?>
            <tr>
                <td><?=e($r['customer_name'])?></td>
                <td><?=e($r['bank_name'])?></td>
                <td class="mono"><?=e($r['account_number'])?></td>
                <td class="mono"><?=e($r['ifsc'])?></td>
                <td><?=e($r['status'])?></td>
                <td><?=e($verified)?></td>
                <td>
                    <form method="post" action="<?=e(url('/admin/payout/bank-verification/'.(int)$r['id'].'/decide'))?>" class="po-inline">
                        <?=csrf_field()?>
                        <input type="hidden" name="decision" value="APPROVED">
                        <button type="submit" class="po-link-btn">Approve</button>
                    </form>
                    <form method="post" action="<?=e(url('/admin/payout/bank-verification/'.(int)$r['id'].'/decide'))?>" class="po-inline">
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
