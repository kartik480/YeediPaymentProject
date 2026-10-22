<?php include __DIR__.'/_head.php'; ?>
<h1>Customer accounts</h1>
<p class="muted">Choose a customer. Dashboard, Account Statement, Billing, and Wallet then show only that customer.</p>
<div class="card">
    <form class="row" method="get" action="<?=e(url('/admin/accounts'))?>">
        <select name="code" required>
            <option value="">Select customer</option>
            <?php foreach ($customers as $c): ?>
            <option value="<?=e($c['code'])?>"><?=e($c['code'].' — '.$c['name'])?></option>
            <?php endforeach; ?>
        </select>
        <select name="go">
            <option value="dashboard" <?=($module ?? '') === 'dashboard' ? 'selected' : ''?>>Dashboard</option>
            <option value="statement" <?=($module ?? '') === 'statement' ? 'selected' : ''?>>Account Statement</option>
            <option value="billing" <?=($module ?? '') === 'billing' ? 'selected' : ''?>>Billing</option>
            <option value="wallet" <?=($module ?? '') === 'wallet' ? 'selected' : ''?>>Wallet</option>
        </select>
        <button>Open</button>
    </form>
</div>
<div class="card scroll">
    <table>
        <tr><th>Code</th><th>Name</th><th>Phone</th><th></th></tr>
        <?php foreach ($customers as $c): ?>
        <tr>
            <td class="mono"><?=e($c['code'])?></td>
            <td><?=e($c['name'])?></td>
            <td><?=e($c['phone'])?></td>
            <td>
                <a href="<?=e(url('/admin/accounts/'.$c['code']))?>">Dashboard</a>
                · <a href="<?=e(url('/admin/accounts/'.$c['code'].'/statement'))?>">Account Statement</a>
                · <a href="<?=e(url('/admin/accounts/'.$c['code'].'/billing'))?>">Billing</a>
                · <a href="<?=e(url('/admin/accounts/'.$c['code'].'/wallet'))?>">Wallet</a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$customers): ?>
        <tr><td colspan="4" class="muted">No customers yet.</td></tr>
        <?php endif; ?>
    </table>
</div>
<?php include __DIR__.'/_foot.php'; ?>
