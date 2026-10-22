<?php
$portalLayout = 'bare';
$sent = !empty($sent);
$forgot = !empty($forgot);
$legacy = !empty($c) && empty($_SESSION['portal_otp_for']);
include __DIR__.'/_portal_head.php';
?>
<section class="yp-login">
    <form class="yp-card yp-login-card" method="post" action="<?=e($sent ? ($legacy ? url('/c/'.$c['code'].'/verify-otp') : url('/login/verify')) : ($legacy ? url('/c/'.$c['code'].'/request-otp') : url('/login')))?>">
        <?=csrf_field()?>
        <?php if (!$legacy && !$sent): ?><input type="hidden" name="forgot" value="<?=$forgot ? '1' : ''?>"><?php endif; ?>
        <?php if (!empty($next)): ?><input type="hidden" name="next" value="<?=e($next)?>"><?php endif; ?>
        <h1><img class="yp-logo" src="<?=e(brand_logo_url())?>" alt="YEEDI Payment Solution Limited"></h1>
        <p class="yp-login-sub"><?=e(Portal::t('login_title'))?></p>
        <?php if (!empty($error)): ?><div class="yp-error"><?=e($error)?></div><?php endif; ?>
        <?php if ($sent): ?>
            <p class="yp-note">OTP sent to <?=e($masked_phone ?? 'your mobile')?>.</p>
            <?php if (!empty($dev_otp)): ?><p class="yp-note">Local test code: <b class="mono"><?=e($dev_otp)?></b></p><?php endif; ?>
            <label class="yp-field">
                <span class="yp-sr">OTP</span>
                <input name="otp" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="6-digit OTP" required>
            </label>
            <button class="yp-btn" type="submit"><?=e(Portal::t('verify'))?></button>
            <p class="yp-forgot"><a href="<?=e(url('/login?reset=1'.(!empty($next) ? '&next='.rawurlencode((string)$next) : '')))?>">Send a new OTP</a></p>
        <?php else: ?>
            <label class="yp-field">
                <span class="yp-field-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 7 9-7"/></svg>
                </span>
                <input name="login" type="text" autocomplete="username" placeholder="<?=e(Portal::t('email'))?>" value="<?=e($login_value ?? '')?>" required>
            </label>
            <?php if (!$forgot && !$legacy): ?>
            <label class="yp-field">
                <span class="yp-field-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
                </span>
                <input name="password" type="password" autocomplete="current-password" placeholder="<?=e(Portal::t('password'))?>">
            </label>
            <?php endif; ?>
            <button class="yp-btn" type="submit"><?=e($forgot ? 'Send OTP' : Portal::t('login_btn'))?></button>
            <?php if (!$legacy): ?>
            <p class="yp-forgot"><a href="<?=e(url('/login?forgot=1'.(!empty($next) ? '&next='.rawurlencode((string)$next) : '')))?>"><?=e(Portal::t('forgot'))?></a></p>
            <?php endif; ?>
        <?php endif; ?>
    </form>
</section>
<?php include __DIR__.'/_portal_foot.php'; ?>
