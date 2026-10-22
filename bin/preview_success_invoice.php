<?php
declare(strict_types=1);

/**
 * Build a local HTML preview of the post-SUCCESS print receipt (no MySQL required).
 * Usage: php bin/preview_success_invoice.php
 *        php bin/preview_success_invoice.php YDPT0003... CUST0003
 */
require dirname(__DIR__).'/app/bootstrap.php';

$refArg = isset($argv[1]) ? strtoupper(trim((string)$argv[1])) : '';
$codeArg = isset($argv[2]) ? strtoupper(trim((string)$argv[2])) : '';

$c = null;
$t = null;

if ($refArg !== '') {
    try {
        $db = Database::pdo();
        $t = TransactionService::txnByRef($db, $refArg);
        if ($t && strtoupper((string)$t['status']) === 'SUCCESS') {
            $code = $codeArg !== '' ? $codeArg : (string)($t['customer_code'] ?? '');
            if ($code !== '') {
                $c = TransactionService::customerByCode($db, $code);
            }
        }
    } catch (Throwable $e) {
        fwrite(STDERR, 'Database unavailable — using sample data.'."\n");
    }
}

if (!$c || !$t) {
    $c = [
        'name' => 'SIRI ENTERPRISES',
        'code' => 'CUST0003',
        'account_number' => 'YD000359841094',
    ];
    $t = [
        'reference' => 'YDPT0003KKK10091127403F48F2',
        'status' => 'SUCCESS',
        'requested_amount' => '1.00',
        'confirmed_amount' => '1.00',
        'updated_at' => '2026-10-09 11:28:19',
        'bank_name' => 'ICICI Bank',
        'payee_name' => 'K K K ENTERPRISES',
        'vpa' => 'msgreenrisingagrilimited.eazypay@icici',
        'verified_utr' => '664807226545',
        'buyer_name' => 'Suresh Sir',
    ];
    if ($refArg !== '') {
        fwrite(STDERR, 'Could not load SUCCESS txn '.$refArg.' — wrote sample layout instead.'."\n");
    }
}

$out = dirname(__DIR__).'/storage/preview-payment-success-invoice.html';
if (!is_dir(dirname($out))) {
    mkdir(dirname($out), 0755, true);
}
file_put_contents($out, InvoiceService::printHtml($c, $t, false));

$pdfOut = dirname(__DIR__).'/storage/sample-payment-success-invoice.pdf';
file_put_contents($pdfOut, InvoiceService::render($c, $t)->bytes());

echo 'HTML preview:'."\n".$out."\n";
echo 'Sample PDF:'."\n".$pdfOut."\n";
echo 'Open the PDF to see the invoice. HTML: Print (Ctrl+P) matches the same layout.'."\n";
