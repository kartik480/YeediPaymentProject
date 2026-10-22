<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/payment_matcher.php';

function authenticate_merchant_request(string $rawBody): array {
    $clientId = request_header('X-Client-Id');
    $timestamp = request_header('X-Timestamp');
    $signature = strtolower(request_header('X-Signature'));

    if ($clientId === '' || !ctype_digit($timestamp) || !preg_match('/^[a-f0-9]{64}$/', $signature)) {
        json_response(['ok'=>false,'error'=>'missing_auth_headers'], 401);
    }
    $skew = (int)(yeedi_config()['allowed_clock_skew_seconds'] ?? 300);
    if (abs(time() - (int)$timestamp) > $skew) {
        json_response(['ok'=>false,'error'=>'stale_request'], 401);
    }

    $pdo = yeedi_db();
    $q = $pdo->prepare('SELECT id, client_id, api_secret, active FROM merchant_clients WHERE client_id = ? LIMIT 1');
    $q->execute([$clientId]);
    $client = $q->fetch();
    if (!$client || (int)$client['active'] !== 1) json_response(['ok'=>false,'error'=>'unknown_client'], 401);

    $expected = hash_hmac('sha256', $timestamp . "\n" . $rawBody, (string)$client['api_secret']);
    if (!hash_equals($expected, $signature)) json_response(['ok'=>false,'error'=>'bad_signature'], 401);
    return $client;
}

function create_payment_order(int $merchantClientDbId, string $merchantOrderId, string $amount, string $preferredBank = ''): array {
    $merchantOrderId = trim($merchantOrderId);
    $amount = normalize_amount_2($amount);
    $preferredBank = normalize_bank($preferredBank);

    if ($merchantClientDbId <= 0) return ['ok'=>false,'error'=>'invalid_client'];
    if ($merchantOrderId === '' || strlen($merchantOrderId) > 100) return ['ok'=>false,'error'=>'invalid_merchant_order_id'];
    if ($amount === null) return ['ok'=>false,'error'=>'invalid_amount'];
    if ($preferredBank !== '' && !in_array($preferredBank, ['ICICI','INDUSIND','RBL'], true)) {
        return ['ok'=>false,'error'=>'unsupported_bank'];
    }

    $pdo = yeedi_db();
    $cfg = yeedi_config();
    $expiryMinutes = max(1, min(60, (int)($cfg['order_expiry_minutes'] ?? 10)));
    $collisionGuard = !empty($cfg['collision_guard_enabled']);
    // Keep the exact-amount slot reserved through the late-SMS grace period too.
    // Otherwise an expired ₹500 order and a new ₹500 order can overlap while the
    // previous bank SMS is still legally matchable, creating avoidable ambiguity.
    $postExpiryGrace = max(0, (int)($cfg['payment_post_expiry_grace_seconds'] ?? 300));
    $slotCutoffUtc = gmdate('Y-m-d H:i:s', time() - $postExpiryGrace);

    try {
        $pdo->beginTransaction();

        $dupe = $pdo->prepare(
            'SELECT payment_id, status FROM qr_payment_orders WHERE merchant_client_id = ? AND merchant_order_id = ? LIMIT 1 FOR UPDATE'
        );
        $dupe->execute([$merchantClientDbId, $merchantOrderId]);
        if ($dupe->fetch()) {
            $pdo->rollBack();
            return ['ok'=>false,'error'=>'merchant_order_exists'];
        }

        $sql = 'SELECT id, account_key, bank_name, account_label, account_last4, upi_id, payee_name, qr_image_url
                FROM bank_receiving_accounts WHERE active = 1';
        $params = [];
        if ($preferredBank !== '') {
            $sql .= ' AND bank_name = ?';
            $params[] = $preferredBank;
        }
        $sql .= ' ORDER BY sort_order, id FOR UPDATE';
        $q = $pdo->prepare($sql);
        $q->execute($params);
        $accounts = $q->fetchAll();
        if (!$accounts) {
            $pdo->rollBack();
            return ['ok'=>false,'error'=>'no_receiving_account_available'];
        }

        $selected = null;
        foreach ($accounts as $account) {
            if (!$collisionGuard) {
                $selected = $account;
                break;
            }
            // Exact-amount slot guard: one active same-amount order per receiving account.
            // This prevents two ₹500 orders from becoming indistinguishable when bank SMS
            // contains only amount/account/time and no YEEDI order id.
            $busy = $pdo->prepare(
                "SELECT id FROM qr_payment_orders
                 WHERE receiving_account_id = ? AND amount = ?
                   AND status IN ('PENDING','EXPIRED')
                   AND expires_at >= ?
                 LIMIT 1"
            );
            $busy->execute([(int)$account['id'], $amount, $slotCutoffUtc]);
            if (!$busy->fetch()) {
                $selected = $account;
                break;
            }
        }

        if (!$selected) {
            $pdo->rollBack();
            return [
                'ok'=>false,
                'error'=>'amount_slot_busy',
                'message'=>'Same amount is already active on all configured receiving accounts. Retry after current slot expires.',
            ];
        }

        $paymentId = 'YDP' . gmdate('ymdHis') . strtoupper(bin2hex(random_bytes(5)));
        $publicToken = random_token(32);
        $tokenHash = public_token_hash($publicToken);

        $ins = $pdo->prepare(
            "INSERT INTO qr_payment_orders
             (payment_id, merchant_client_id, merchant_order_id, public_token_hash,
              receiving_account_id, bank_name, amount, status, created_at, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'PENDING', UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? MINUTE))"
        );
        $ins->execute([
            $paymentId, $merchantClientDbId, $merchantOrderId, $tokenHash,
            (int)$selected['id'], normalize_bank($selected['bank_name']), $amount, $expiryMinutes,
        ]);

        $pdo->commit();
        return [
            'ok'=>true,
            'payment_id'=>$paymentId,
            'public_token'=>$publicToken,
            'merchant_order_id'=>$merchantOrderId,
            'amount'=>$amount,
            'status'=>'PENDING',
            'expires_in_seconds'=>$expiryMinutes * 60,
            'bank'=>normalize_bank($selected['bank_name']),
            'receiving_account_key'=>$selected['account_key'],
            'account_label'=>$selected['account_label'],
            'account_last4'=>$selected['account_last4'],
            'upi_id'=>$selected['upi_id'],
            'payee_name'=>$selected['payee_name'],
            'qr_image_url'=>$selected['qr_image_url'],
            'upi_uri'=>build_upi_uri($selected['upi_id'], $selected['payee_name'], $amount, $paymentId),
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok'=>false,'error'=>'database_error'];
    }
}

function get_public_payment_status(string $paymentId, string $publicToken): array {
    $paymentId = trim($paymentId);
    $publicToken = trim($publicToken);
    if ($paymentId === '' || strlen($paymentId) > 64 || $publicToken === '' || strlen($publicToken) > 128) {
        return ['ok'=>false,'error'=>'invalid_request'];
    }

    $pdo = yeedi_db();

    // First authenticate the public payment link. The raw token is never stored.
    $auth = $pdo->prepare(
        'SELECT id, public_token_hash FROM qr_payment_orders WHERE payment_id = ? LIMIT 1'
    );
    $auth->execute([$paymentId]);
    $authRow = $auth->fetch();
    if (!$authRow || !hash_equals((string)$authRow['public_token_hash'], public_token_hash($publicToken))) {
        return ['ok'=>false,'error'=>'payment_not_found'];
    }

    // Reuse V5 matcher/status semantics. This can also change PENDING -> EXPIRED.
    $core = get_payment_status($paymentId);
    if (empty($core['ok'])) return $core;

    // Read the row AFTER core status evaluation so an SMS match racing with this poll is reflected.
    $q = $pdo->prepare(
        'SELECT o.id, o.payment_id, o.public_token_hash, o.bank_name, o.amount, o.status,
                o.submitted_utr, o.customer_ack_at, o.created_at, o.expires_at,
                o.sms_confirmed_at, o.paid_at,
                a.account_key, a.account_label, a.account_last4, a.upi_id, a.payee_name, a.qr_image_url
         FROM qr_payment_orders o
         JOIN bank_receiving_accounts a ON a.id = o.receiving_account_id
         WHERE o.payment_id = ? LIMIT 1'
    );
    $q->execute([$paymentId]);
    $row = $q->fetch();
    if (!$row) return ['ok'=>false,'error'=>'payment_not_found'];

    $pdo->prepare('UPDATE qr_payment_orders SET last_status_poll_at = UTC_TIMESTAMP() WHERE id = ?')
        ->execute([(int)$row['id']]);

    $expiryTs = (new DateTimeImmutable($row['expires_at'], new DateTimeZone('UTC')))->getTimestamp();
    $expiresIn = max(0, $expiryTs - time());

    // Prefer an actual DB SUCCESS that may have landed between the two reads.
    $status = (string)$row['status'];
    if ($status !== 'SUCCESS') {
        $status = (string)$core['status'];
    }

    $cfg = yeedi_config();
    $bufferSeconds = max(5, min(120, (int)($cfg['customer_verification_buffer_seconds'] ?? 30)));
    $delayedPollSeconds = max(2, min(15, (int)($cfg['customer_delayed_poll_seconds'] ?? 3)));
    $acknowledged = !empty($row['customer_ack_at']);
    $verificationState = 'AWAITING_PAYMENT';
    $verificationRemaining = 0;
    $customerMessage = 'Complete the UPI payment, then tap I HAVE PAID.';
    $keepPolling = true;
    $doNotPayAgain = false;

    if ($status === 'SUCCESS') {
        $verificationState = 'CONFIRMED';
        $customerMessage = 'Payment confirmed from verified bank SMS.';
        $keepPolling = false;
        $doNotPayAgain = true;
    } elseif ($status === 'REVIEW_REQUIRED') {
        $verificationState = 'REVIEW_REQUIRED';
        $customerMessage = 'Bank credit was received, but automatic matching needs review. Do not pay again.';
        $keepPolling = true;
        $doNotPayAgain = true;
    } elseif ($status === 'EXPIRED') {
        $verificationState = 'EXPIRED';
        $customerMessage = 'Payment window expired. If you already paid, do not pay again; delayed bank confirmation can still be reviewed.';
        $keepPolling = true;
        $doNotPayAgain = $acknowledged;
    } elseif ($acknowledged) {
        $ackTs = (new DateTimeImmutable($row['customer_ack_at'], new DateTimeZone('UTC')))->getTimestamp();
        $elapsed = max(0, time() - $ackTs);
        if ($elapsed < $bufferSeconds) {
            $verificationState = 'VERIFYING';
            $verificationRemaining = max(0, $bufferSeconds - $elapsed);
            $customerMessage = 'Verifying your payment with the bank SMS relay. Please do not refresh or pay again.';
        } else {
            $verificationState = 'WAITING_BANK_SMS';
            $customerMessage = 'Bank SMS confirmation is delayed. We are continuing verification. Please do not refresh or pay again.';
        }
        $doNotPayAgain = true;
    }

    return [
        'ok'=>true,
        'payment_id'=>$paymentId,
        'status'=>$status,
        'verification_state'=>$verificationState,
        'verification_buffer_seconds'=>$bufferSeconds,
        'verification_remaining_seconds'=>$verificationRemaining,
        'recommended_poll_seconds'=>$verificationState === 'WAITING_BANK_SMS' ? $delayedPollSeconds : (int)($cfg['customer_poll_seconds'] ?? 2),
        'customer_message'=>$customerMessage,
        'keep_polling'=>$keepPolling,
        'do_not_pay_again'=>$doNotPayAgain,
        'amount'=>(string)$row['amount'],
        'bank'=>normalize_bank($row['bank_name']),
        'account_key'=>$row['account_key'],
        'account_label'=>$row['account_label'],
        'account_last4'=>$row['account_last4'],
        'upi_id'=>$row['upi_id'],
        'payee_name'=>$row['payee_name'],
        'qr_image_url'=>$row['qr_image_url'],
        'upi_uri'=>build_upi_uri($row['upi_id'], $row['payee_name'], (string)$row['amount'], $paymentId),
        'customer_acknowledged'=>$acknowledged,
        'customer_ack_at'=>$acknowledged ? $row['customer_ack_at'] . 'Z' : null,
        'expires_at'=>$row['expires_at'] . 'Z',
        'expires_in_seconds'=>$expiresIn,
        'utr'=>$status === 'SUCCESS' ? $row['submitted_utr'] : null,
        'paid_at'=>$status === 'SUCCESS' && $row['paid_at'] ? $row['paid_at'] . 'Z' : null,
        'delivery_ready'=>$status === 'SUCCESS',
        'server_time'=>gmdate('Y-m-d\TH:i:s\Z'),
    ];
}

function acknowledge_customer_paid(string $paymentId, string $publicToken): array {
    // Authenticate and read the current state first.
    $status = get_public_payment_status($paymentId, $publicToken);
    if (empty($status['ok'])) return $status;

    // If already successful, keep it idempotent and return SUCCESS.
    if (($status['status'] ?? '') === 'SUCCESS') return $status;

    $pdo = yeedi_db();
    $q = $pdo->prepare(
        'UPDATE qr_payment_orders
         SET customer_ack_at = COALESCE(customer_ack_at, UTC_TIMESTAMP())
         WHERE payment_id = ? AND public_token_hash = ?'
    );
    $q->execute([$paymentId, public_token_hash($publicToken)]);

    // IMPORTANT: I HAVE PAID never creates SUCCESS. It starts the 30-second
    // customer verification buffer. Only a verified bank CREDIT SMS can create SUCCESS.
    $status = get_public_payment_status($paymentId, $publicToken);
    $status['customer_acknowledged'] = true;
    return $status;
}
