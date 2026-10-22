<?php
$portalLayout = 'bare';
$forgot = !empty($forgot);
include __DIR__.'/_portal_head.php';
?>
<section class="yp-login">
    <form class="yp-card yp-login-card" method="post" action="<?=e(url('/login'))?>">
        <?=csrf_field()?>
        <?php if (!empty($next)): ?><input type="hidden" name="next" value="<?=e($next)?>"><?php endif; ?>
        <h1><img class="yp-logo" src="<?=e(brand_logo_url())?>" alt="YEEDI Payment Solution Limited"></h1>
        <p class="yp-login-sub"><?=e(Portal::t('login_title'))?></p>
        <?php if (!empty($error)): ?><div class="yp-error"><?=e($error)?></div><?php endif; ?>
        <?php if ($forgot): ?><p class="yp-note"><?=e(Portal::t('forgot_help'))?></p><?php endif; ?>
        <label class="yp-field">
            <span class="yp-field-ico" aria-hidden="true">
                <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 7 9-7"/></svg>
            </span>
            <input name="login" type="text" autocomplete="username" placeholder="<?=e(Portal::t('email'))?>" value="<?=e($login_value ?? '')?>" required>
        </label>
        <label class="yp-field">
            <span class="yp-field-ico" aria-hidden="true">
                <svg viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
            </span>
            <input name="password" type="password" autocomplete="current-password" placeholder="<?=e(Portal::t('password'))?>" required>
        </label>
        <button class="yp-btn" type="submit"><?=e(Portal::t('login_btn'))?></button>
        <p class="yp-forgot"><a href="<?=e(url('/login?forgot=1'.(!empty($next) ? '&next='.rawurlencode((string)$next) : '')))?>"><?=e(Portal::t('forgot'))?></a></p>
    </form>
</section>
<?php include __DIR__.'/_portal_foot.php'; ?>
