<?php
declare(strict_types=1);

require_once __DIR__.'/../app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

$db = Database::pdo();
BankService::ensureIciciEazypayStandees($db);

echo "ICICI Eazypay standee banks synced (VPA: ".BankService::ICICI_EAZYPAY_VPA.").\n";
foreach (BankService::iciciEazypayStandeeDefinitions() as $d) {
    echo '  - '.$d['code'].' '.$d['bank_name']."\n";
}
