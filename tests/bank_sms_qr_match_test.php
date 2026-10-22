<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

/** @param list<mixed> $args */
function invokePrivate(string $method, mixed ...$args): mixed {
    $ref = new ReflectionMethod(BankSmsService::class, $method);
    $ref->setAccessible(true);
    return $ref->invoke(null, ...$args);
}

$smsUtc = new DateTimeImmutable('2026-10-11 07:20:00', new DateTimeZone('UTC'));

// Laptop: old QR + I have paid (no real pay). Computer: fresh QR + real pay (no ack).
$pool = [
    [
        'id' => 1,
        'reference' => 'YDP00001JKM1011070000AAAA01',
        'customer_id' => 10,
        'customer_paid_ack_at' => '2026-10-11 07:18:00',
        'created_at' => '2026-10-11 07:00:00',
    ],
    [
        'id' => 2,
        'reference' => 'YDP00002JKM1011070000BBBB02',
        'customer_id' => 20,
        'customer_paid_ack_at' => null,
        'created_at' => '2026-10-11 07:19:30',
    ],
];

$pick = invokePrivate('pickBestPaymentForSms', $pool, $smsUtc);
if (!$pick || (int)$pick['id'] !== 2) {
    fwrite(STDERR, "FAIL: expected computer/fresh QR payment id=2, got ".json_encode($pick)."\n");
    exit(1);
}

echo "OK: bank SMS matches freshest QR session, not stale I-have-paid\n";
