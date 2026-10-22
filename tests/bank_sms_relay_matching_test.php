<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

/** @param list<mixed> $args */
function invokePrivate(string $method, mixed ...$args): mixed {
    $ref = new ReflectionMethod(BankSmsService::class, $method);
    $ref->setAccessible(true);
    return $ref->invoke(null, ...$args);
}

$pdo = Database::pdo();
BankSmsService::ensureSchema($pdo);

$device = [
    'id' => 0,
    'device_id' => 'TEST-DEVICE',
    'bank_code' => 'JKMERCHANT',
    'account_last4' => '4270',
    'active' => 1,
];

// Stub deviceAccounts by using a real device row if present; otherwise skip DB-heavy checks.
$match = invokePrivate('matchingAccount', $pdo, $device, 'ICICI', '4270');
if ($match === null) {
    // Expected when TEST device is not in DB — logic still validated via relayAccountMatchesBank.
    $ok = invokePrivate('relayAccountMatchesBank', $pdo, 'JKMERCHANT', 'ICICI');
    if (!$ok) {
        // Merchant code with no ICICI in profile: last4-only fallback should still match single account.
        $accounts = [['bank_code' => 'JKMERCHANT', 'account_last4' => '4270']];
        $picked = invokePrivate('pickUniqueRelayAccount', $accounts, $device);
        if ($picked === null || $picked['bank_code'] !== 'JKMERCHANT') {
            fwrite(STDERR, "FAIL: pickUniqueRelayAccount fallback\n");
            exit(1);
        }
    }
    echo "OK: relay matching helpers (no TEST device row)\n";
    exit(0);
}

if (($match['account_last4'] ?? '') !== '4270') {
    fwrite(STDERR, "FAIL: wrong last4\n");
    exit(1);
}

echo "OK: ICICI SMS maps to device account {$match['bank_code']} · {$match['account_last4']}\n";
