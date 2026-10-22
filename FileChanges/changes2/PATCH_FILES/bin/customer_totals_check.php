<?php
declare(strict_types=1);

require_once __DIR__.'/../app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$code = strtoupper(trim((string)($argv[1] ?? '')));
if ($code === '') {
    fwrite(STDERR, "Usage: php bin/customer_totals_check.php CUST0003\n");
    exit(2);
}

$db = Database::pdo();
TransactionService::ensureSchema($db);

$c = TransactionService::customerByCode($db, $code);
if (!$c) {
    fwrite(STDERR, "Customer not found: {$code}\n");
    exit(3);
}

$totals = TransactionService::collectionTotals($db, (int)$c['id']);

$st = $db->prepare("SELECT
    COUNT(CASE WHEN status='PENDING' THEN 1 END) pending_count,
    COALESCE(SUM(CASE WHEN status='PENDING' THEN requested_amount ELSE 0 END),0) pending_amount,
    COUNT(CASE WHEN status='SUCCESS' THEN 1 END) success_count,
    COALESCE(SUM(CASE WHEN status='SUCCESS' THEN COALESCE(confirmed_amount,requested_amount,0) ELSE 0 END),0) confirmed_amount,
    COUNT(CASE WHEN status='FAILED' THEN 1 END) failed_count,
    COUNT(CASE WHEN status='EXPIRED' THEN 1 END) expired_count,
    COALESCE(SUM(CASE WHEN status='SUCCESS' AND COALESCE(settlement_status,'UNSETTLED')='SETTLED' THEN COALESCE(confirmed_amount,requested_amount,0) ELSE 0 END),0) settled_amount
    FROM transactions
    WHERE customer_id=?");
$st->execute([(int)$c['id']]);
$raw = $st->fetch() ?: [];

$fmt = static fn($v): string => number_format((float)$v, 2, '.', '');

$checks = [
    'pending' => abs((float)$totals['pending'] - (float)($raw['pending_amount'] ?? 0)) < 0.005,
    'confirmed' => abs((float)$totals['confirmed'] - (float)($raw['confirmed_amount'] ?? 0)) < 0.005,
    'settled' => abs((float)$totals['settled'] - (float)($raw['settled_amount'] ?? 0)) < 0.005,
    'ledger' => abs((float)$totals['ledger_balance'] - ((float)$totals['confirmed'] - (float)$totals['settled'] - (float)$totals['refunded'])) < 0.005,
    'failed_not_refund' => abs((float)$totals['refunded']) < 0.005,
];

$ok = !in_array(false, $checks, true);

echo "YEEDIPAY CUSTOMER TOTALS CHECK\n";
echo "Customer        : {$code}\n";
echo "SUCCESS count   : ".(int)($raw['success_count'] ?? 0)."\n";
echo "Confirmed       : ₹".$fmt($totals['confirmed'])."\n";
echo "PENDING count   : ".(int)($raw['pending_count'] ?? 0)."\n";
echo "Pending         : ₹".$fmt($totals['pending'])."\n";
echo "FAILED count    : ".(int)($raw['failed_count'] ?? 0)." (NOT refund)\n";
echo "EXPIRED count   : ".(int)($raw['expired_count'] ?? 0)." (NOT refund)\n";
echo "Refunded        : ₹".$fmt($totals['refunded'])."\n";
echo "Settled         : ₹".$fmt($totals['settled'])."\n";
echo "Ledger Balance  : ₹".$fmt($totals['ledger_balance'])."\n";
foreach ($checks as $name => $pass) {
    echo str_pad($name, 18).': '.($pass ? 'PASS' : 'FAIL')."\n";
}
echo $ok ? "TOTALS CHECK PASS\n" : "TOTALS CHECK FAIL\n";
exit($ok ? 0 : 1);
