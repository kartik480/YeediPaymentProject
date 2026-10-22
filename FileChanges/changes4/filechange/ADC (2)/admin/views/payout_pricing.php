<?php
include __DIR__.'/_payout_head.php';
$fields = PricingService::FIELDS;
$charges = PricingService::CHARGES;
?>
<div class="po-head">
    <h1>Pricing Configuration</h1>
</div>
<?php foreach ($plans ?? [] as $key => $plan): ?>
<div class="po-card po-plan-card">
    <h2><?=e(ucfirst($key))?></h2>
    <form method="post" action="<?=e(url('/admin/payout/pricing/plan'))?>" class="po-form-grid">
        <?=csrf_field()?>
        <input type="hidden" name="plan_key" value="<?=e($key)?>">
        <?php foreach ($fields as $field => $meta): ?>
        <label><?=e($meta['label'])?>
            <input type="number" name="<?=e($field)?>" step="<?=e($meta['step'])?>" min="0" value="<?=e((string)($plan[$field] ?? ''))?>">
        </label>
        <?php endforeach; ?>
        <div class="po-plan-actions"><button class="po-save" type="submit">Save Plan</button></div>
    </form>
</div>
<?php endforeach; ?>
<div class="po-card po-plan-card">
    <h2>Tenant price override</h2>
    <form method="post" action="<?=e(url('/admin/payout/pricing/override'))?>" class="po-form-grid">
        <?=csrf_field()?>
        <label>Tenant
            <select name="tenant">
                <option value=""></option>
                <?php foreach ($tenants ?? [] as $t): ?>
                <option value="<?=e($t['code'])?>"><?=e($t['name'])?> (<?=e($t['code'])?>)</option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Charge
            <select name="charge">
                <?php foreach ($charges as $ck => $cl): ?>
                <option value="<?=e($ck)?>"<?=$ck==='BASE_SUBSCRIPTION'?' selected':''?>><?=e($cl)?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Unit price<input name="unit_price" inputmode="decimal"></label>
        <div class="po-plan-actions"><button class="po-save" type="submit">Save Override</button></div>
    </form>
</div>
<div class="po-card po-tenant-table scroll">
    <table class="po-table po-tenant-list">
        <thead>
            <tr><th>Tenant</th><th>Charge</th><th>Unit Price</th></tr>
        </thead>
        <tbody>
        <?php foreach ($overrides ?? [] as $o): ?>
            <tr>
                <td><?=e($o['customer_name'])?> <span class="po-muted mono"><?=e($o['customer_code'])?></span></td>
                <td><?=e($o['charge_key'])?></td>
                <td>₹<?=amount_fmt($o['unit_price'])?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__.'/_payout_foot.php'; ?>
