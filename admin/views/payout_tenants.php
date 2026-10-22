<?php
include __DIR__.'/_payout_head.php';
$plans = TenantService::PLANS;
?>
<div class="po-head">
    <h1>Tenant Companies</h1>
</div>
<div class="po-card po-tenant-card">
    <h2>Create tenant</h2>
    <form method="post" action="<?=e(url('/admin/payout/tenants'))?>" class="po-form-grid" autocomplete="off">
        <?=csrf_field()?>
        <label>Brand name<input name="brand_name" required></label>
        <label>Legal name<input name="legal_name"></label>
        <label>PAN<input name="pan" maxlength="10"></label>
        <label>GST<input name="gst" maxlength="15"></label>
        <label>Plan
            <select name="plan">
                <?php foreach ($plans as $key => $label): ?>
                <option value="<?=e($key)?>"<?=$key==='starter'?' selected':''?>><?=e($label)?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Per transaction limit<input name="per_txn_limit" inputmode="decimal" value="200000"></label>
        <label>Daily limit<input name="daily_limit" inputmode="decimal" value="1000000"></label>
        <label>Subscription ends<input type="datetime-local" name="subscription_ends"></label>
        <label>Admin name<input name="admin_name"></label>
        <label>Admin email<input type="email" name="admin_email" required></label>
        <label>Admin password<input type="password" name="admin_password" autocomplete="new-password" required minlength="8"></label>
        <div class="po-form-submit"><button class="po-create" type="submit">Create tenant</button></div>
    </form>
</div>
<?php include __DIR__.'/_payout_foot.php'; ?>
