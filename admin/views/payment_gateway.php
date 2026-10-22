<?php
include __DIR__.'/_head.php';
$live = (int)($s['live_mode'] ?? 0) === 1;
$enabled = (int)($s['enabled'] ?? 0) === 1;
$hasSecret = trim((string)($s['key_secret_encrypted'] ?? '')) !== '';
$hasWebhook = trim((string)($s['webhook_secret_encrypted'] ?? '')) !== '';
$webhookUrl = pay_url('/api/razorpay/webhook');
?>
<h1>Payment Gateway — Razorpay</h1>

<?php if (!empty($notice)): ?><div class="card" style="border-left:4px solid #16a34a"><b><?=e($notice)?></b></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="card" style="border-left:4px solid #dc2626"><b><?=e($error)?></b></div><?php endif; ?>

<div class="card">
    <h2>Status</h2>
    <p>Mode: <b><?= $live ? 'LIVE' : 'TEST' ?></b> · Enabled: <b><?= $enabled ? 'YES' : 'NO' ?></b>
       · Key Secret: <b><?= $hasSecret ? 'stored' : 'missing' ?></b>
       · Webhook Secret: <b><?= $hasWebhook ? 'stored' : 'missing' ?></b>
       · Webhook confirmed: <b><?= (int)($s['webhook_configured'] ?? 0) ? 'YES' : 'NO' ?></b></p>
    <p class="muted">Integration: <b>Orders API + Checkout.js + verified webhook</b>. A payment is confirmed only by a
       signature-verified webhook or an authenticated server-side fetch — never by the browser alone.</p>
    <p>Webhook endpoint (set this in the Razorpay Dashboard → Settings → Webhooks):<br>
       <span class="mono"><?=e($webhookUrl)?></span><br>
       Subscribe to events: <span class="mono">payment.captured</span>, <span class="mono">payment.failed</span>, <span class="mono">order.paid</span>.</p>
</div>

<div class="card">
    <h2>Razorpay API keys</h2>
    <p class="muted">Get these from Razorpay Dashboard → Settings → API Keys. Secrets are stored encrypted and never shown again — leave a secret field blank to keep the current value.</p>
    <form method="post" action="<?=e(url('/admin/payment-gateway'))?>"><?=csrf_field()?>
        <div class="grid">
            <label><input type="checkbox" name="live_mode" value="1" <?= $live ? 'checked' : '' ?>> Live mode (uncheck for Test)</label>
            <label><input type="checkbox" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?>> Enabled (customers can pay)</label>
            <input name="key_id" value="<?=e((string)($s['key_id'] ?? ''))?>" placeholder="Key ID (rzp_test_xxx / rzp_live_xxx)" autocomplete="off">
            <input name="key_secret" value="" placeholder="<?= $hasSecret ? 'Key Secret (stored — leave blank to keep)' : 'Key Secret' ?>" autocomplete="off">
            <input name="webhook_secret" value="" placeholder="<?= $hasWebhook ? 'Webhook Secret (stored — leave blank to keep)' : 'Webhook Secret' ?>" autocomplete="off">
            <input name="currency" value="<?=e((string)($s['currency'] ?? 'INR'))?>" placeholder="Currency (INR)" maxlength="3" style="text-transform:uppercase">
            <input name="button_label" value="<?=e((string)($s['button_label'] ?? 'YEEDIPAY Payment'))?>" placeholder="Button label">
            <input name="min_amount" value="<?=e((string)($s['min_amount'] ?? '1.00'))?>" placeholder="Min amount (₹)">
            <input name="max_amount" value="<?=e((string)($s['max_amount'] ?? '100000.00'))?>" placeholder="Max amount (₹)">
            <label><input type="checkbox" name="webhook_configured" value="1" <?= (int)($s['webhook_configured'] ?? 0) ? 'checked' : '' ?>> I have configured the webhook in the Razorpay Dashboard</label>
        </div>
        <p style="margin-top:12px"><button>Save settings</button></p>
    </form>
    <p class="muted">Live mode can be enabled only when a webhook secret is stored and the webhook is confirmed — this guarantees server-side verification before any live payment is accepted.</p>
</div>

<div class="card">
    <h2>Reconciliation</h2>
    <p><a href="<?=e(url('/admin/razorpay'))?>">Open Razorpay transactions &amp; reconciliation →</a></p>
</div>
<?php include __DIR__.'/_foot.php'; ?>
