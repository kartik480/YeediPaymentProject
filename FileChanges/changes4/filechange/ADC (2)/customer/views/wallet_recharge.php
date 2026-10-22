<?php
include __DIR__.'/_dash_head.php';
$form_action = url($custBase.'/wallet/recharge');
$show_top_dashboard = false;
$pay_shell = true;
$footer_link = ['href' => url($custBase.'/wallet'), 'label' => '← Back to wallet'];
?>
<div class="pay dash col-dash">
    <h1 class="col-title">Recharge Wallet</h1>
    <p class="col-note">Select amount and proceed to scan the bank QR code. Balance updates after payment confirmation.</p>
    <?php
    include __DIR__.'/_payment_start.php';
    ?>
</div>
<?php include __DIR__.'/_dash_foot.php'; ?>
