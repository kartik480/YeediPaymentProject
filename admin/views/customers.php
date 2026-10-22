<?php
include __DIR__.'/_head.php';
$edit = $edit ?? null;
$isEdit = is_array($edit);
?>
<h1>Customers</h1>

<?php if ($isEdit): ?>
<div class="card">
    <h2>Edit customer — <?=e($edit['code'])?></h2>
    <form method="post" action="<?=e(url('/admin/customers/'.$edit['code'].'/update'))?>">
        <?=csrf_field()?>
        <input type="hidden" name="return_to" value="list">
        <div class="grid">
            <label>Code
                <input class="mono" value="<?=e($edit['code'])?>" readonly>
            </label>
            <label>Customer name
                <input name="name" value="<?=e($edit['name'])?>" required>
            </label>
            <label>Mobile
                <input name="phone" value="<?=e($edit['phone'])?>" required>
            </label>
            <label>Login ID
                <input name="login_id" value="<?=e($edit['login_id'] ?? '')?>" minlength="4" maxlength="64" autocomplete="username" required>
            </label>
            <label>New password <span class="muted">(leave blank to keep current)</span>
                <input type="password" name="password" minlength="8" autocomplete="new-password" placeholder="Optional">
            </label>
            <label>Per transaction ₹
                <input name="per_txn_limit" inputmode="decimal" value="<?=e($edit['per_txn_limit'] ?? '')?>" placeholder="Per transaction ₹">
            </label>
            <label>Daily ₹
                <input name="daily_limit" inputmode="decimal" value="<?=e($edit['daily_limit'] ?? '')?>" placeholder="Daily ₹">
            </label>
            <label>Weekly ₹
                <input name="weekly_limit" inputmode="decimal" value="<?=e($edit['weekly_limit'] ?? '')?>" placeholder="Weekly ₹">
            </label>
            <label>Monthly ₹
                <input name="monthly_limit" inputmode="decimal" value="<?=e($edit['monthly_limit'] ?? '')?>" placeholder="Monthly ₹">
            </label>
            <label>Daily attempt count
                <input name="daily_txn_count_limit" inputmode="numeric" value="<?=e($edit['daily_txn_count_limit'] ?? '')?>" placeholder="Daily attempt count">
            </label>
            <label>Max pending
                <input name="max_pending_attempts" inputmode="numeric" value="<?=e($edit['max_pending_attempts'] ?? '5')?>">
            </label>
        </div>
        <p class="row">
            <button type="submit" class="br-save">Save changes</button>
            <a class="btn btn2" href="<?=e(url('/admin/customers'))?>">Cancel</a>
            <a class="btn btn2" href="<?=e(url('/admin/customers/'.$edit['code']))?>">Manage (API & transactions)</a>
        </p>
    </form>
    <form method="post" action="<?=e(url('/admin/customers/'.$edit['code'].'/toggle'))?>" style="margin-top:12px">
        <?=csrf_field()?>
        <input type="hidden" name="return_to" value="list">
        <button type="submit" class="btn2"><?=((int)$edit['active'] ? 'Disable customer pay link' : 'Enable customer pay link')?></button>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <h2>Add Customer + ID Limits</h2>
    <form method="post">
        <?=csrf_field()?>
        <div class="grid">
            <input name="name" placeholder="Customer name" required>
            <input name="phone" placeholder="Mobile" required>
            <input name="code" placeholder="Optional Customer ID">
            <input name="login_id" placeholder="Login ID" minlength="4" maxlength="64" autocomplete="username" required>
            <input type="password" name="password" placeholder="Password (8+ characters)" minlength="8" autocomplete="new-password" required>
            <input name="per_txn_limit" inputmode="decimal" placeholder="Per transaction ₹">
            <input name="daily_limit" inputmode="decimal" placeholder="Daily ₹">
            <input name="weekly_limit" inputmode="decimal" placeholder="Weekly ₹">
            <input name="monthly_limit" inputmode="decimal" placeholder="Monthly ₹">
            <input name="daily_txn_count_limit" inputmode="numeric" placeholder="Daily attempt count">
            <input name="max_pending_attempts" inputmode="numeric" value="5">
        </div>
        <p><button type="submit">Add Customer</button></p>
    </form>
    <p class="muted">Login credentials protect the customer dashboard. Limits are application controls. The bank-issued exact UPI payload is never modified.</p>
</div>

<div class="card">
    <form class="row" method="get" action="<?=e(url('/admin/customers'))?>">
        <input class="grow" name="q" value="<?=e($q ?? '')?>" placeholder="Search code/name/phone/login ID">
        <button type="submit">Search</button>
    </form>
</div>

<div class="card scroll">
    <table class="br-table">
        <tr>
            <th>Code</th>
            <th>Login ID</th>
            <th>Name</th>
            <th>Phone</th>
            <th>Account</th>
            <th>Status</th>
            <th>Service</th>
            <th>Per Txn</th>
            <th>Daily</th>
            <th>Weekly</th>
            <th>Monthly</th>
            <th>Permanent Link</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($customers as $c): ?>
        <tr>
            <td><?=e($c['code'])?></td>
            <td><?=e($c['login_id'] ?? '')?></td>
            <td><?=e($c['name'])?></td>
            <td><?=e($c['phone'])?></td>
            <td class="mono"><?=e($c['account_number'] ?? '')?></td>
            <td><?=((int)$c['active'] ? 'ACTIVE' : 'DISABLED')?></td>
            <td><?=!empty($c['service_activated']) ? 'ACTIVATED' : 'WAITING'?></td>
            <td><?=e($c['per_txn_limit'] ?? '')?></td>
            <td><?=e($c['daily_limit'] ?? '')?></td>
            <td><?=e($c['weekly_limit'] ?? '')?></td>
            <td><?=e($c['monthly_limit'] ?? '')?></td>
            <td class="mono"><a href="<?=e(pay_url('/p/'.$c['code']))?>" target="_blank" rel="noopener"><?=e(pay_url('/p/'.$c['code']))?></a></td>
            <td>
                <div class="row">
                    <a class="btn btn2" href="<?=e(url('/admin/customers?edit='.$c['code']))?>">Edit</a>
                    <a href="<?=e(url('/admin/customers/'.$c['code']))?>">Manage</a>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$customers): ?>
        <tr><td colspan="13" class="muted">No customers yet.</td></tr>
        <?php endif; ?>
    </table>
</div>
<?php include __DIR__.'/_foot.php'; ?>
