<?php include __DIR__.'/_super_head.php';
$form = $form ?? [
    'name' => '',
    'email' => '',
    'role' => 'CHECKER',
];
$roles = SuperAdminService::adminUserRoles();
?>
<?php if (!empty($error)): ?><div class="sa-error"><?=e($error)?></div><?php endif; ?>

<div class="sa-panel sa-admin-form">
    <h2 class="sa-section-title">Create Admin User</h2>
    <p class="sa-admin-note">Use a separate <b>CHECKER</b> account for daily bank-credit confirmation if you want maker-checker separation.</p>
    <form method="post" action="<?=e(url('/super-admin/admin-users'))?>" autocomplete="off">
        <?=csrf_field()?>
        <div class="sa-form-grid sa-form-grid-4">
            <label>Name
                <input name="name" value="<?=e($form['name'])?>" required>
            </label>
            <label>Email
                <input type="email" name="email" value="<?=e($form['email'])?>" required>
            </label>
            <label>Password
                <input type="password" name="password" autocomplete="new-password" minlength="10" required>
            </label>
            <label>Role
                <select name="role">
                    <?php foreach ($roles as $role): ?>
                    <option value="<?=e($role)?>" <?=($form['role'] ?? 'CHECKER') === $role ? 'selected' : ''?>><?=e($role)?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <button type="submit" class="sa-filter-btn sa-create-btn">Create Admin</button>
    </form>
</div>

<div class="sa-panel sa-links-panel">
    <div class="sa-txn-table-wrap scroll">
        <table class="sa-txn-table sa-admin-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Active</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users ?? [] as $u): ?>
                <tr>
                    <td><?=e(SuperAdminService::adminUserDisplayName($u))?></td>
                    <td><?=e(SuperAdminService::adminUserDisplayEmail($u))?></td>
                    <td><span class="sa-mode-pill"><?=e(SuperAdminService::adminUserDisplayRole($u))?></span></td>
                    <td><?=!empty($u['active']) ? 'YES' : 'NO'?></td>
                    <td><?=e((string)($u['created_at'] ?? ''))?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($users)): ?>
                <tr><td colspan="5" class="sa-empty">No admin users yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__.'/_super_foot.php'; ?>
