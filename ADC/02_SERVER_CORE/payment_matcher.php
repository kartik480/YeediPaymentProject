<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/portal_sync.php';

/**
 * Customer-facing status helper. No customer UTR input is required.
 */
function get_payment_status(string $paymentId): array {
    $paymentId = trim($paymentId);
    if ($paymentId === '' || strlen($paymentId) > 64) {
        return ['ok'=>false,'error'=>'invalid_payment_id'];
    }

    $pdo = yeedi_db();
    $q = $pdo->prepare(
        'SELECT id, payment_id, receiving_account_id, bank_name, amount, status, created_at, expires_at,
                sms_confirmed_at, paid_at
         FROM qr_payment_orders WHERE payment_id = ? LIMIT 1'
    );
    $q->execute([$paymentId]);
    $row = $q->fetch();
    if (!$row) return ['ok'=>false,'error'=>'payment_not_found'];

    // REVIEW_REQUIRED is a virtual customer status: do not mark every same-amount order
    // as paid/reviewed. An ambiguous SMS event may belong to only one of them.
    $publicStatus = $row['status'];
    if (in_array($row['status'], ['PENDING','EXPIRED'], true)) {
        $cfg = yeedi_config();
        $preGrace = max(0, (int)$cfg['payment_preorder_grace_seconds']);
        $postGrace = max(0, (int)$cfg['payment_post_expiry_grace_seconds']);
        $created = new DateTimeImmutable($row['created_at'], new DateTimeZone('UTC'));
        $expiry = new DateTimeImmutable($row['expires_at'], new DateTimeZone('UTC'));
        $windowStart = $created->modify('-' . $preGrace . ' seconds')->format('Y-m-d H:i:s');
        $windowEnd = $expiry->modify('+' . $postGrace . ' seconds')->format('Y-m-d H:i:s');

        $amb = $pdo->prepare(
            "SELECT id FROM bank_sms_events
             WHERE match_state = 'AMBIGUOUS'
               AND matched_payment_id IS NULL
               AND bank_name = ? AND receiving_account_id = ? AND amount = ?
               AND received_at >= ? AND received_at <= ?
             LIMIT 1"
        );
        $amb->execute([
            normalize_bank($row['bank_name']), $row['receiving_account_id'],
            normalize_amount_2((string)$row['amount']), $windowStart, $windowEnd
        ]);
        if ($amb->fetch()) {
            // Even if a previous status poll already marked the order EXPIRED, a bank SMS
            // that actually arrived inside the valid payment window must still be reviewable.
            $publicStatus = 'REVIEW_REQUIRED';
        } elseif ($row['status'] === 'PENDING' && time() > ($expiry->getTimestamp() + $postGrace)) {
            $u = $pdo->prepare(
                "UPDATE qr_payment_orders SET status = 'EXPIRED' WHERE id = ? AND status = 'PENDING'"
            );
            $u->execute([$row['id']]);
            if ($u->rowCount() === 1) {
                $row['status'] = 'EXPIRED';
                $publicStatus = 'EXPIRED';
            }
        }
    }

    return [
        'ok'=>true,
        'payment_id'=>$row['payment_id'],
        'bank'=>normalize_bank($row['bank_name']),
        'amount'=>$row['amount'],
        'status'=>$publicStatus,
        'sms_confirmed_at'=>$row['sms_confirmed_at'],
        'paid_at'=>$row['paid_at'],
    ];
}

/**
 * Called by webhook_bank_sms.php after a verified CREDIT SMS event is stored.
 *
 * Safe automatic rule:
 *   bank + receiving account + exact amount + payment time window must resolve
 *   to EXACTLY ONE pending/review payment. An order previously marked EXPIRED may
 *   still be revived when the SMS itself arrived inside the allowed payment window
 *   but phone/network upload was delayed.
 *
 * Exactly 1 candidate => SUCCESS and SMS UTR/reference is attached automatically.
 * 0 candidates        => event remains UNMATCHED; no payment is changed.
 * 2+ candidates       => event is AMBIGUOUS; customer status becomes virtual REVIEW_REQUIRED.
 *
 * We deliberately DO NOT guess when two customers paid the same amount into the
 * same receiving account during overlapping payment windows.
 */
function auto_match_bank_event(int $eventDbId): array {
    if ($eventDbId <= 0) return ['ok'=>false,'error'=>'invalid_event_id'];

    $pdo = yeedi_db();
    $cfg = yeedi_config();

    try {
        $pdo->beginTransaction();

        $e = $pdo->prepare(
            'SELECT id, bank_name, receiving_account_id, amount, reference_no,
                    received_at, matched_payment_id, match_state
             FROM bank_sms_events WHERE id = ? LIMIT 1 FOR UPDATE'
        );
        $e->execute([$eventDbId]);
        $event = $e->fetch();
        if (!$event) {
            $pdo->rollBack();
            return ['ok'=>false,'error'=>'event_not_found'];
        }

        if (!empty($event['matched_payment_id']) && $event['match_state'] === 'MATCHED') {
            $pdo->commit();
            return [
                'ok'=>true,
                'status'=>'SUCCESS',
                'match_state'=>'MATCHED',
                'already_matched'=>true,
                'matched_payment_id'=>(int)$event['matched_payment_id'],
            ];
        }

        // Safety invariant: ambiguity is sticky. Once an event had 2+ eligible orders,
        // later status changes/cancellations must NOT cause the same event to auto-attach
        // to whichever order happens to remain. Only explicit admin manual matching may
        // resolve an AMBIGUOUS event.
        if ($event['match_state'] === 'AMBIGUOUS') {
            $pdo->commit();
            return [
                'ok'=>true,
                'match_state'=>'AMBIGUOUS',
                'sticky_ambiguity'=>true,
            ];
        }

        $preGrace = max(0, (int)$cfg['payment_preorder_grace_seconds']);
        $postGrace = max(0, (int)$cfg['payment_post_expiry_grace_seconds']);

        $received = new DateTimeImmutable($event['received_at'], new DateTimeZone('UTC'));
        // received_at >= created_at - preGrace  <=> created_at <= received_at + preGrace
        $latestCreated = $received->modify('+' . $preGrace . ' seconds')->format('Y-m-d H:i:s');
        // received_at <= expires_at + postGrace <=> expires_at >= received_at - postGrace
        $earliestExpiry = $received->modify('-' . $postGrace . ' seconds')->format('Y-m-d H:i:s');

        $q = $pdo->prepare(
            'SELECT id, payment_id, status
             FROM qr_payment_orders
             WHERE bank_name = ?
               AND receiving_account_id = ?
               AND amount = ?
               AND status IN (\'PENDING\', \'EXPIRED\')
               AND created_at <= ?
               AND expires_at >= ?
             ORDER BY created_at, id
             FOR UPDATE'
        );
        $q->execute([
            normalize_bank($event['bank_name']),
            $event['receiving_account_id'],
            normalize_amount_2((string)$event['amount']),
            $latestCreated,
            $earliestExpiry,
        ]);
        $candidates = $q->fetchAll();
        $count = count($candidates);

        if ($count === 0) {
            $pdo->prepare(
                'UPDATE bank_sms_events SET match_state = \'UNMATCHED\' WHERE id = ?'
            )->execute([$eventDbId]);
            $pdo->commit();
            return ['ok'=>true,'match_state'=>'UNMATCHED','candidate_count'=>0];
        }

        if ($count > 1) {
            $pdo->prepare(
                'UPDATE bank_sms_events SET match_state = \'AMBIGUOUS\' WHERE id = ?'
            )->execute([$eventDbId]);
            $pdo->commit();
            $ids = array_column($candidates, 'payment_id');
            portal_sync_review($ids);
            return [
                'ok'=>true,
                'match_state'=>'AMBIGUOUS',
                'candidate_count'=>$count,
                'candidate_payment_ids'=>$ids,
            ];
        }

        $order = $candidates[0];

        $markEvent = $pdo->prepare(
            'UPDATE bank_sms_events
             SET matched_payment_id = ?, matched_at = UTC_TIMESTAMP(), match_state = \'MATCHED\'
             WHERE id = ? AND matched_payment_id IS NULL'
        );
        $markEvent->execute([$order['id'], $eventDbId]);
        if ($markEvent->rowCount() !== 1) throw new RuntimeException('event_race');

        $updateOrder = $pdo->prepare(
            'UPDATE qr_payment_orders
             SET status = \'SUCCESS\', submitted_utr = ?,
                 sms_confirmed_at = COALESCE(sms_confirmed_at, UTC_TIMESTAMP()),
                 paid_at = COALESCE(paid_at, UTC_TIMESTAMP())
             WHERE id = ? AND status IN (\'PENDING\', \'EXPIRED\')'
        );
        $updateOrder->execute([$event['reference_no'], $order['id']]);
        if ($updateOrder->rowCount() !== 1) throw new RuntimeException('order_race');

        $pdo->commit();
        portal_sync_order_id((int)$order['id']);
        return [
            'ok'=>true,
            'status'=>'SUCCESS',
            'match_state'=>'MATCHED',
            'candidate_count'=>1,
            'payment_id'=>$order['payment_id'],
            'matched_payment_id'=>(int)$order['id'],
        ];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($e->getCode() === '23000') return ['ok'=>false,'error'=>'duplicate_reference'];
        return ['ok'=>false,'error'=>'auto_match_database_error'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok'=>false,'error'=>'auto_match_error'];
    }
}

/**
 * Admin-only fallback for AMBIGUOUS cases. This is intentionally CLI/admin-side;
 * customers are never asked to paste UTR.
 */
function manual_match_event_to_payment(int $eventDbId, string $paymentId): array {
    $paymentId = trim($paymentId);
    if ($eventDbId <= 0 || $paymentId === '' || strlen($paymentId) > 64) {
        return ['ok'=>false,'error'=>'invalid_input'];
    }

    $pdo = yeedi_db();
    $cfg = yeedi_config();
    try {
        $pdo->beginTransaction();

        $e = $pdo->prepare(
            'SELECT id, bank_name, receiving_account_id, amount, reference_no, received_at,
                    matched_payment_id, match_state
             FROM bank_sms_events WHERE id = ? LIMIT 1 FOR UPDATE'
        );
        $e->execute([$eventDbId]);
        $event = $e->fetch();
        if (!$event) { $pdo->rollBack(); return ['ok'=>false,'error'=>'event_not_found']; }
        if (!empty($event['matched_payment_id'])) {
            $pdo->rollBack();
            return ['ok'=>false,'error'=>'event_already_matched'];
        }

        $q = $pdo->prepare(
            'SELECT id, payment_id, receiving_account_id, bank_name, amount, status, created_at, expires_at
             FROM qr_payment_orders WHERE payment_id = ? LIMIT 1 FOR UPDATE'
        );
        $q->execute([$paymentId]);
        $order = $q->fetch();
        if (!$order) { $pdo->rollBack(); return ['ok'=>false,'error'=>'payment_not_found']; }
        if (!in_array($order['status'], ['PENDING','EXPIRED'], true)) {
            $pdo->rollBack();
            return ['ok'=>false,'error'=>'payment_not_matchable','status'=>$order['status']];
        }

        if (normalize_bank($order['bank_name']) !== normalize_bank($event['bank_name'])
            || (int)$order['receiving_account_id'] !== (int)$event['receiving_account_id']
            || normalize_amount_2((string)$order['amount']) !== normalize_amount_2((string)$event['amount'])) {
            $pdo->rollBack();
            return ['ok'=>false,'error'=>'bank_account_amount_mismatch'];
        }

        $received = new DateTimeImmutable($event['received_at'], new DateTimeZone('UTC'));
        $created = new DateTimeImmutable($order['created_at'], new DateTimeZone('UTC'));
        $expires = new DateTimeImmutable($order['expires_at'], new DateTimeZone('UTC'));
        if ($received < $created->modify('-' . (int)$cfg['payment_preorder_grace_seconds'] . ' seconds')
            || $received > $expires->modify('+' . (int)$cfg['payment_post_expiry_grace_seconds'] . ' seconds')) {
            $pdo->rollBack();
            return ['ok'=>false,'error'=>'event_outside_payment_window'];
        }

        $pdo->prepare(
            'UPDATE bank_sms_events
             SET matched_payment_id = ?, matched_at = UTC_TIMESTAMP(), match_state = \'MATCHED\'
             WHERE id = ? AND matched_payment_id IS NULL'
        )->execute([$order['id'], $eventDbId]);

        $pdo->prepare(
            'UPDATE qr_payment_orders
             SET status = \'SUCCESS\', submitted_utr = ?,
                 sms_confirmed_at = COALESCE(sms_confirmed_at, UTC_TIMESTAMP()),
                 paid_at = COALESCE(paid_at, UTC_TIMESTAMP())
             WHERE id = ?'
        )->execute([$event['reference_no'], $order['id']]);

        $pdo->commit();
        portal_sync_order_id((int)$order['id']);
        return ['ok'=>true,'status'=>'SUCCESS','payment_id'=>$paymentId];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok'=>false,'error'=>'manual_match_error'];
    }
}
