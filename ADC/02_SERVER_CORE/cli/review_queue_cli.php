<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/db.php';

$pdo = yeedi_db();
$cfg = yeedi_config();
$preGrace = max(0, (int)$cfg['payment_preorder_grace_seconds']);
$postGrace = max(0, (int)$cfg['payment_post_expiry_grace_seconds']);

$events = $pdo->query(
    "SELECT e.id AS event_db_id, e.receiving_account_id, e.bank_name, e.amount, e.reference_no,
            e.account_last4, e.received_at, e.match_state, a.account_key
     FROM bank_sms_events e
     LEFT JOIN bank_receiving_accounts a ON a.id = e.receiving_account_id
     WHERE e.match_state IN ('AMBIGUOUS','UNMATCHED') AND e.matched_payment_id IS NULL
     ORDER BY e.received_at, e.id"
)->fetchAll();

if (!$events) {
    echo "No unresolved UNMATCHED/AMBIGUOUS SMS events.\n";
    exit(0);
}

foreach ($events as $e) {
    echo "EVENT {$e['event_db_id']} | {$e['match_state']} | {$e['bank_name']} | {$e['account_key']} | ₹{$e['amount']} | Ref {$e['reference_no']} | {$e['received_at']} UTC\n";
    $received = new DateTimeImmutable($e['received_at'], new DateTimeZone('UTC'));
    $latestCreated = $received->modify('+' . $preGrace . ' seconds')->format('Y-m-d H:i:s');
    $earliestExpiry = $received->modify('-' . $postGrace . ' seconds')->format('Y-m-d H:i:s');

    $q = $pdo->prepare(
        "SELECT payment_id, status, created_at, expires_at
         FROM qr_payment_orders
         WHERE bank_name = ? AND receiving_account_id = ? AND amount = ? AND status IN ('PENDING','EXPIRED')
           AND created_at <= ? AND expires_at >= ?
         ORDER BY created_at, id"
    );
    $q->execute([
        $e['bank_name'], $e['receiving_account_id'], $e['amount'],
        $latestCreated, $earliestExpiry
    ]);
    foreach ($q->fetchAll() as $o) {
        echo "  candidate: {$o['payment_id']} | {$o['status']} | {$o['created_at']} -> {$o['expires_at']} UTC\n";
    }
}
