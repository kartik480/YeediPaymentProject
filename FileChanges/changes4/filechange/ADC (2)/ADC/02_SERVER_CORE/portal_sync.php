<?php
function portal_sync_order_id(int $orderDbId): void {
    if ($orderDbId <= 0) return;
    try {
        $pdo = yeedi_db();
        $q = $pdo->prepare('SELECT merchant_order_id, amount, submitted_utr, status FROM qr_payment_orders WHERE id=? LIMIT 1');
        $q->execute([$orderDbId]);
        $row = $q->fetch();
        if (!$row || strtoupper((string)$row['status']) !== 'SUCCESS') return;
        $ref = trim((string)$row['merchant_order_id']);
        if ($ref === '') return;
        $utr = trim((string)($row['submitted_utr'] ?? ''));
        $pdo->prepare(
            "UPDATE transactions
             SET status='SUCCESS', confirmed_amount=?, rrn=IFNULL(rrn, NULLIF(?, '')),
                 upi_txn_id=IFNULL(upi_txn_id, NULLIF(?, '')), sms_match='SUCCESS',
                 confirmation_source='ADC_BANK_SMS', updated_at=UTC_TIMESTAMP()
             WHERE reference=? AND status IN ('PENDING','EXPIRED')"
        )->execute([$row['amount'], $utr, $utr, $ref]);
    } catch (Throwable $e) {
    }
}

function portal_record_relay_sms(int $eventDbId): void {
    if ($eventDbId <= 0) return;
    try {
        $pdo = yeedi_db();
        if (!$pdo->query("SHOW TABLES LIKE 'portal_bank_sms_events'")->fetchColumn()) return;
        $q = $pdo->prepare(
            'SELECT e.event_id, e.device_id, e.bank_name, e.amount, e.reference_no, e.account_last4,
                    e.sender_id, e.received_at, e.match_state,
                    a.account_key, o.payment_id, o.merchant_order_id
             FROM bank_sms_events e
             LEFT JOIN bank_receiving_accounts a ON a.id = e.receiving_account_id
             LEFT JOIN qr_payment_orders o ON o.id = e.matched_payment_id
             WHERE e.id = ? LIMIT 1'
        );
        $q->execute([$eventDbId]);
        $e = $q->fetch();
        if (!$e) return;
        $state = strtoupper((string)$e['match_state']);
        $status = $state === 'MATCHED' ? 'SUCCESS' : ($state === 'AMBIGUOUS' ? 'REVIEW_REQUIRED' : 'UNMATCHED');
        $ref = trim((string)($e['merchant_order_id'] ?: $e['payment_id']));
        $txnId = null;
        if ($ref !== '') {
            $t = $pdo->prepare('SELECT id FROM transactions WHERE reference=? LIMIT 1');
            $t->execute([$ref]);
            $found = $t->fetchColumn();
            $txnId = $found ? (int)$found : null;
        }
        $last4 = preg_replace('/\D/', '', (string)$e['account_last4']) ?? '';
        if (!preg_match('/^\d{4}$/', $last4)) $last4 = '0000';
        $pdo->prepare(
            'INSERT INTO portal_bank_sms_events
             (device_id, android_event_id, bank_code, amount, utr, account_last4, sender_id, sms_time, raw_body, match_status, transaction_id, candidate_refs, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE match_status=VALUES(match_status), transaction_id=VALUES(transaction_id), candidate_refs=VALUES(candidate_refs), sms_time=VALUES(sms_time)'
        )->execute([
            (string)$e['device_id'],
            (string)$e['event_id'],
            strtoupper((string)($e['account_key'] ?: $e['bank_name'])),
            $e['amount'],
            strtoupper((string)$e['reference_no']),
            $last4,
            (string)$e['sender_id'],
            (string)$e['received_at'],
            null,
            $status,
            $txnId,
            $ref !== '' ? $ref : null,
        ]);
    } catch (Throwable $e) {
    }
}

function portal_sync_review(array $paymentIds): void {
    $ids = array_values(array_filter(array_map('strval', $paymentIds)));
    if (!$ids) return;
    try {
        $pdo = yeedi_db();
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare(
            "UPDATE transactions SET sms_match='REVIEW_REQUIRED', updated_at=UTC_TIMESTAMP()
             WHERE reference IN ($marks) AND status='PENDING' AND (sms_match IS NULL OR sms_match='')"
        )->execute($ids);
    } catch (Throwable $e) {
    }
}
