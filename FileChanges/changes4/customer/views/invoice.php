<?php
include __DIR__.'/_portal_head.php';
$split = $split ?? ['base' => 0, 'gst' => 0, 'total' => 0];
$when = Portal::when((string)($t['updated_at'] ?? $t['created_at'] ?? ''), 'd-m-Y');
$invoiceNo = trim((string)($t['invoice_ref'] ?? ''));
if ($invoiceNo === '') $invoiceNo = 'INV-'.$t['reference'];
$billTo = trim((string)($t['payer_vpa'] ?? ''));
if ($billTo === '') $billTo = trim((string)($t['customer_reported_email'] ?? ''));
if ($billTo === '') $billTo = 'Payer';
$gstin = trim((string)($c['gst'] ?? ''));
$legal = trim((string)($c['legal_name'] ?? ''));
if ($legal === '') $legal = (string)$c['name'];
$uri = TransactionService::scanUri($t);
?>
<section class="yp-sheet yp-invoice">
    <div class="yp-invoice-top">
        <div class="yp-seller">
            <div class="yp-mark"><?=e(Portal::mark($legal))?></div>
            <div>
                <h1><?=e($legal)?></h1>
                <p><?=e((string)$c['phone'])?><?php if (trim((string)($c['email'] ?? '')) !== ''): ?> · <?=e((string)$c['email'])?><?php endif; ?></p>
                <?php if ($gstin !== ''): ?><p>GSTIN: <?=e($gstin)?></p><?php endif; ?>
            </div>
        </div>
        <div class="yp-invoice-meta">
            <b><?=e(Portal::t('invoice'))?></b>
            <span>Invoice No: <?=e($invoiceNo)?></span>
            <span>Invoice Date: <?=e($when)?></span>
            <span>Payment ID: <?=e((string)$t['reference'])?></span>
        </div>
    </div>
    <div class="yp-invoice-grid">
        <div>
            <h2>Bill To</h2>
            <p class="yp-billto"><?=e($billTo)?></p>
            <table class="yp-table yp-inv-table">
                <thead>
                    <tr>
                        <th>SL.No</th>
                        <th>Description</th>
                        <th>Qty</th>
                        <th>Rate (₹)</th>
                        <th>Amount (₹)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td>Service Charges</td>
                        <td>1</td>
                        <td><?=e(Portal::money((float)$split['base']))?></td>
                        <td><?=e(Portal::money((float)$split['base']))?></td>
                    </tr>
                </tbody>
            </table>
            <div class="yp-totals">
                <div><span>Subtotal</span><b>₹<?=e(Portal::money((float)$split['base']))?></b></div>
                <div><span>GST @ 18%</span><b>₹<?=e(Portal::money((float)$split['gst']))?></b></div>
                <div class="yp-grand"><span>Total Amount</span><b>₹<?=e(Portal::money((float)$split['total']))?></b></div>
            </div>
            <p class="yp-note">GST is included in the amount received. This breakup does not add a charge on top of the payment.</p>
        </div>
        <aside class="yp-invoice-qr">
            <?php if ($uri !== ''): ?>
            <div class="yp-qrbox"><div id="qr" class="qrbox" data-logo="<?=e(brand_logo_url())?>"></div></div>
            <?php endif; ?>
            <p class="mono"><?=e((string)$t['reference'])?></p>
            <button class="yp-btn yp-btn-sm yp-no-print" type="button" onclick="window.print()">Save PDF</button>
        </aside>
    </div>
</section>
<?php if ($uri !== ''): ?>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script src="<?=e(asset_url('qr-brand.js'))?>"></script>
<script>
(function () {
  var box = document.getElementById('qr');
  if (box && window.yeediQr) yeediQr(box, <?=json_encode($uri)?>, 168);
})();
</script>
<?php endif; ?>
<?php include __DIR__.'/_portal_foot.php'; ?>
