<?php include __DIR__.'/_super_head.php'; ?>
<div class="sa-login">
    <h1>Super Admin Login</h1>
    <?php if (!empty($error)): ?><div class="sa-error"><?=e($error)?></div><?php endif; ?>
    <form method="post">
        <?=csrf_field()?>
        <input name="username" placeholder="Username" required autocomplete="username">
        <input type="password" name="password" placeholder="Password" required autocomplete="current-password">
        <button type="submit">Login</button>
    </form>
</div>
<?php include __DIR__.'/_super_foot.php'; ?>
