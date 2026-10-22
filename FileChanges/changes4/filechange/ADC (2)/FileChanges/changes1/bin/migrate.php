<?php
declare(strict_types=1);

require_once __DIR__.'/../app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

$db = Database::pdo();
$files = [
    __DIR__.'/../database/migrate_transactions.sql',
    __DIR__.'/../database/migrate_banks_routing.sql',
    __DIR__.'/../database/migrate_settlements.sql',
    __DIR__.'/../database/migrate_payment_confirm.sql',
    __DIR__.'/../database/migrate_sms_amount_match.sql',
    __DIR__.'/../database/migrate_account_number.sql',
    __DIR__.'/../database/migrate_platform_updates.sql',
];

$ok = 0;
$skip = 0;
$fail = 0;

foreach ($files as $file) {
    if (!is_file($file)) {
        echo "Skip missing file: {$file}\n";
        continue;
    }
    echo 'Applying '.basename($file)." ...\n";
    $sql = (string)file_get_contents($file);
    $sql = preg_replace('/^\s*(?:--|#).*$/m', '', $sql) ?? $sql;
    $parts = preg_split('/;\s*(?:\r?\n|$)/', $sql);
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '' || str_starts_with($part, '--')) {
            continue;
        }
        try {
            $db->exec($part);
            $ok++;
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (
                str_contains($msg, 'Duplicate column')
                || str_contains($msg, 'Duplicate key name')
                || str_contains($msg, 'already exists')
                || str_contains($msg, 'Duplicate foreign key')
                || str_contains($msg, 'Duplicate entry')
            ) {
                $skip++;
                continue;
            }
            $fail++;
            fwrite(STDERR, "  Error: {$msg}\n");
        }
    }
}

BillingService::ensureSchema($db);
GstService::ensureSchema($db);
PricingService::ensureSchema($db);
SettlementService::ensureSchema($db);
ConnectorService::ensureSchema($db);
BankVerificationService::ensureSchema($db);
KybService::ensureSchema($db);
EmailService::ensureSchema($db);
TransactionService::ensureSchema($db);
SuperAdminService::ensureCustomerSchema($db);
SuperAdminService::ensureAdminUserSchema($db);
TicketService::ensureSchema($db);
WalletService::ensureSchema($db);
BankSmsService::ensureSchema($db);

echo "Migration finished. Applied: {$ok}, skipped (already present): {$skip}, errors: {$fail}\n";
if ($fail > 0) exit(1);
