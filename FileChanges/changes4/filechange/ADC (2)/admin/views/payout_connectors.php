<?php include __DIR__.'/_payout_head.php'; ?>
<div class="po-head">
    <div>
        <h1>Connector Verification</h1>
        <p class="po-sub">Verify only after bank/UAT connectivity and authorization are confirmed. Verification starts monthly connector billing.</p>
    </div>
</div>
<div class="po-card po-plan-card">
    <form method="post" action="<?=e(url('/admin/payout/connectors'))?>" class="po-form-grid">
        <?=csrf_field()?>
        <label>Tenant
            <select name="tenant" required>
                <option value=""></option>
                <?php foreach ($tenants ?? [] as $t): ?>
                <option value="<?=e($t['code'])?>"><?=e($t['name'])?> (<?=e($t['code'])?>)</option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Bank
            <select name="bank_id" required>
                <option value=""></option>
                <?php foreach ($banks ?? [] as $b): ?>
                <option value="<?=e((string)$b['id'])?>"><?=e($b['bank_name'])?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Mode
            <select name="mode">
                <option value="UAT">UAT</option>
                <option value="LIVE">LIVE</option>
            </select>
        </label>
        <div class="po-plan-actions"><button class="po-save" type="submit">Add connector</button></div>
    </form>
</div>
<div class="po-card po-tenant-table scroll">
    <table class="po-table po-tenant-list">
        <thead>
            <tr>
                <th>Tenant</th>
                <th>Bank</th>
                <th>Mode</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows ?? [] as $r): ?>
            <tr>
                <td><?=e($r['customer_name'])?></td>
                <td><?=e($r['bank_name'])?></td>
                <td><?=e($r['mode'])?></td>
                <td><?=e($r['status'])?></td>
                <td>
                    <form method="post" action="<?=e(url('/admin/payout/connectors/'.(int)$r['id'].'/verify'))?>" class="po-inline">
                        <?=csrf_field()?>
                        <button type="submit" class="po-link-btn"><?=strtoupper((string)$r['status'])==='VERIFIED'?'Unverify':'Verify'?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__.'/_payout_foot.php'; ?>
