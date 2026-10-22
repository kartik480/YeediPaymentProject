<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$pdo = Database::pdo();
BankSmsService::ensureSchema($pdo);
TransactionService::ensureSchema($pdo);

$issues = [];
$info = [];

$tables = ['bank_sms_devices', 'bank_sms_device_accounts', 'portal_bank_sms_events', 'transactions', 'bank_profiles'];
foreach ($tables as $t) {
    $ok = (bool)$pdo->query("SHOW TABLES LIKE " . $pdo->quote($t))->fetchColumn();
    if (!$ok) $issues[] = "Missing table: $t";
}

$cols = [];
foreach ($pdo->query('SHOW COLUMNS FROM transactions') as $r) {
    $cols[strtolower((string)$r['Field'])] = true;
}
foreach (['customer_paid_ack_at', 'sms_match', 'rrn', 'upi_txn_id'] as $c) {
    if (!isset($cols[$c])) $issues[] = "transactions missing column: $c";
}

$deviceId = 'YEEDI-EF427D43';
$st = $pdo->prepare('SELECT * FROM bank_sms_devices WHERE device_id=? LIMIT 1');
$st->execute([$deviceId]);
$dev = $st->fetch();
if (!$dev) {
    $issues[] = "No bank_sms_devices row for $deviceId";
} else {
    $info[] = "Device {$dev['device_id']}: active=" . (int)$dev['active'] . ", bank={$dev['bank_code']}, last4={$dev['account_last4']}, last_seen=" . ($dev['last_seen_at'] ?? 'null');
    $st2 = $pdo->prepare('SELECT bank_code, account_last4 FROM bank_sms_device_accounts WHERE device_row_id=?');
    $st2->execute([(int)$dev['id']]);
    foreach ($st2->fetchAll() as $a) {
        $info[] = "  + extra account {$a['bank_code']} · {$a['account_last4']}";
    }
}

$utr = '665027560222';
$st = $pdo->prepare('SELECT id, bank_code, amount, match_status, transaction_id, sms_time FROM portal_bank_sms_events WHERE utr=? LIMIT 1');
$st->execute([$utr]);
$ev = $st->fetch();
if ($ev) {
    $info[] = "Event UTR $utr: status={$ev['match_status']}, bank={$ev['bank_code']}, amt={$ev['amount']}, txn_id=" . ($ev['transaction_id'] ?? 'null') . ", sms_time={$ev['sms_time']}";
} else {
    $info[] = "Event UTR $utr: not in DB (local copy may differ from live server)";
}

$st = $pdo->query("SELECT t.id, t.bank_id, t.reference, t.status, t.requested_amount, t.customer_paid_ack_at, t.sms_match, t.created_at, b.code bank_code, b.vpa
                   FROM transactions t
                   JOIN bank_profiles b ON b.id=t.bank_id
                   WHERE t.status='PENDING' AND t.customer_paid_ack_at IS NOT NULL
                   ORDER BY t.id DESC LIMIT 8");
$pending = $st->fetchAll();
$info[] = 'Recent pending + I HAVE PAID: ' . count($pending);
foreach ($pending as $p) {
    $info[] = "  {$p['reference']} ₹{$p['requested_amount']} bank={$p['bank_code']} vpa={$p['vpa']} ack={$p['customer_paid_ack_at']} sms_match=" . ($p['sms_match'] ?? 'null');
}

if ($dev && $ev) {
    $relayIds = (new ReflectionMethod(BankSmsService::class, 'relayCollectBankIds'))->invoke(null, $pdo, (string)$ev['bank_code']);
    $info[] = 'relayCollectBankIds for event bank ' . $ev['bank_code'] . ': ' . implode(',', $relayIds);
    foreach ($pending as $p) {
        $linked = in_array((int)$p['bank_id'], $relayIds, true);
        $info[] = '  txn ' . $p['reference'] . ' bank_id=' . $p['bank_id'] . ' linked_to_sms=' . ($linked ? 'yes' : 'NO');
    }
}

$codes = ['JKMERCHANT', 'KKKENT', 'ICICI'];
foreach ($codes as $code) {
    $st = $pdo->prepare('SELECT id, code, bank_name, vpa, enabled FROM bank_profiles WHERE code=? LIMIT 1');
    $st->execute([$code]);
    $bp = $st->fetch();
    if ($bp) {
        $fam = BankSmsService::relayBankFamily($pdo, $code);
        $info[] = "Profile $code: id={$bp['id']} enabled=" . (int)$bp['enabled'] . " vpa={$bp['vpa']} relayFamily=$fam";
    }
}

$dup = $pdo->query("SELECT ROUND(requested_amount,2) amt, COUNT(*) c
                    FROM transactions
                    WHERE status='PENDING' AND customer_paid_ack_at IS NOT NULL
                    GROUP BY ROUND(requested_amount,2)
                    HAVING c > 1
                    ORDER BY c DESC LIMIT 5")->fetchAll();
foreach ($dup as $d) {
    $issues[] = (int)$d['c'] . ' stuck pending payments with I HAVE PAID at ₹' . $d['amt'] . ' — SMS auto-match needs exactly one (or clear old ones).';
}

$old = (int)$pdo->query("SELECT COUNT(*) FROM transactions
                         WHERE status='PENDING' AND customer_paid_ack_at IS NOT NULL
                           AND customer_paid_ack_at < (UTC_TIMESTAMP() - INTERVAL 1 DAY)")->fetchColumn();
if ($old > 0) {
    $issues[] = "$old pending payment(s) still waiting with I HAVE PAID older than 24h — cancel or fail them in Admin.";
}

if ($dev && isset($ev['bank_code'])) {
    $amount = number_format((float)$ev['amount'], 2, '.', '');
    $relayIds = (new ReflectionMethod(BankSmsService::class, 'relayCollectBankIds'))->invoke(null, $pdo, (string)$ev['bank_code']);
    $cnt = (new ReflectionMethod(BankSmsService::class, 'pendingAckCountForAmount'))->invoke(null, $pdo, $amount, $relayIds);
    $info[] = "Pending ack count for ₹$amount on relay-linked banks: $cnt";
    if ($cnt === 0) {
        $issues[] = 'No pending payment with I HAVE PAID for this SMS amount on linked bank profiles (logic issue or customer did not press I HAVE PAID).';
    } elseif ($cnt > 1) {
        $issues[] = "Multiple ($cnt) pending ack payments for same amount — SMS stays UNMATCHED/REVIEW until only one remains.";
    }
}

echo "=== Bank SMS DB check ===\n";
foreach ($info as $line) echo $line . "\n";
if ($issues) {
    echo "\nISSUES:\n";
    foreach ($issues as $i) echo " - $i\n";
    exit(1);
}
echo "\nSchema looks OK; no blocking DB structure issues detected.\n";
