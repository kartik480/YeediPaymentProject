<?php
require __DIR__ . '/db.php';
require_once __DIR__ . '/payment_matcher.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['ok'=>false,'error'=>'method_not_allowed'], 405);

$raw = file_get_contents('php://input');
if ($raw === false || strlen($raw) < 2 || strlen($raw) > 8192) {
    json_response(['ok'=>false,'error'=>'invalid_body'], 400);
}

$deviceId = trim($_SERVER['HTTP_X_DEVICE_ID'] ?? '');
$timestamp = trim($_SERVER['HTTP_X_TIMESTAMP'] ?? '');
$signature = strtolower(trim($_SERVER['HTTP_X_SIGNATURE'] ?? ''));

if ($deviceId === '' || !ctype_digit($timestamp) || !preg_match('/^[a-f0-9]{64}$/', $signature)) {
    json_response(['ok'=>false,'error'=>'missing_auth_headers'], 401);
}

$cfg = yeedi_config();
if (abs(time() - (int)$timestamp) > (int)$cfg['allowed_clock_skew_seconds']) {
    json_response(['ok'=>false,'error'=>'stale_request'], 401);
}

$pdo = yeedi_db();
$stmt = $pdo->prepare('SELECT id, api_secret, active FROM sms_relay_devices WHERE device_id = ? LIMIT 1');
$stmt->execute([$deviceId]);
$device = $stmt->fetch();
if (!$device || (int)$device['active'] !== 1) json_response(['ok'=>false,'error'=>'unknown_device'], 401);

$expected = hash_hmac('sha256', $timestamp . "\n" . $raw, $device['api_secret']);
if (!hash_equals($expected, $signature)) json_response(['ok'=>false,'error'=>'bad_signature'], 401);

$data = json_decode($raw, true);
if (!is_array($data)) json_response(['ok'=>false,'error'=>'invalid_json'], 400);

$pdo->prepare('UPDATE sms_relay_devices SET last_seen_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$device['id']]);

if (!empty($data['is_test'])) {
    json_response(['ok'=>true,'test'=>true,'device_id'=>$deviceId]);
}

$eventId = strtolower(trim((string)($data['event_id'] ?? '')));
$bank = normalize_bank((string)($data['bank'] ?? ''));
$channel = strtoupper(trim((string)($data['channel'] ?? '')));
$amount = normalize_amount_2((string)($data['amount'] ?? ''));
$reference = normalize_reference((string)($data['reference'] ?? ''));
$accountLast4 = preg_replace('/\D/', '', (string)($data['account_last4'] ?? ''));
$sender = trim((string)($data['sender'] ?? ''));
$receivedAtText = trim((string)($data['received_at'] ?? ''));

if (!preg_match('/^[a-f0-9]{64}$/', $eventId)) json_response(['ok'=>false,'error'=>'bad_event_id'], 422);
if (!in_array($bank, ['ICICI','INDUSIND','RBL'], true)) json_response(['ok'=>false,'error'=>'unsupported_bank'], 422);
if ($channel !== 'UPI') json_response(['ok'=>false,'error'=>'unsupported_channel'], 422);
if ($amount === null) json_response(['ok'=>false,'error'=>'bad_amount'], 422);
if (!preg_match('/^[A-Z0-9]{10,35}$/', $reference)) json_response(['ok'=>false,'error'=>'bad_reference'], 422);
if ($accountLast4 !== '' && !preg_match('/^\d{4}$/', $accountLast4)) json_response(['ok'=>false,'error'=>'bad_account_mask'], 422);
if ($sender === '' || strlen($sender) > 80) json_response(['ok'=>false,'error'=>'bad_sender'], 422);

$senderUpper = strtoupper(trim($sender));
$allowedSenders = array_values(array_filter(array_map(
    static fn($v) => strtoupper(trim((string)$v)),
    $cfg['sender_ids'][$bank] ?? []
), static fn($v) => $v !== ''));
if (!$allowedSenders || !in_array($senderUpper, $allowedSenders, true)) {
    json_response(['ok'=>false,'error'=>'sender_not_allowlisted'], 422);
}

try {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/', $receivedAtText)) {
        json_response(['ok'=>false,'error'=>'bad_received_at'], 422);
    }
    $receivedAt = DateTimeImmutable::createFromFormat(
        '!Y-m-d\TH:i:s.v\Z',
        $receivedAtText,
        new DateTimeZone('UTC')
    );
    $dateErrors = DateTimeImmutable::getLastErrors();
    if (!$receivedAt || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))) {
        json_response(['ok'=>false,'error'=>'bad_received_at'], 422);
    }
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    if ($receivedAt > $now->modify('+5 minutes')) json_response(['ok'=>false,'error'=>'future_event'], 422);
    if ($receivedAt < $now->modify('-' . (int)$cfg['max_event_age_hours'] . ' hours')) {
        json_response(['ok'=>false,'error'=>'event_too_old'], 422);
    }
    $receivedAtUtc = $receivedAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
} catch (Throwable $e) {
    json_response(['ok'=>false,'error'=>'bad_received_at'], 422);
}

if ($accountLast4 !== '') {
    $a = $pdo->prepare(
        'SELECT id FROM bank_receiving_accounts
         WHERE bank_name = ? AND account_last4 = ? AND active = 1 LIMIT 1'
    );
    $a->execute([$bank, $accountLast4]);
    $account = $a->fetch();
    if (!$account) json_response(['ok'=>false,'error'=>'receiving_account_not_configured'], 422);
} else {
    $a = $pdo->prepare('SELECT id FROM bank_receiving_accounts WHERE bank_name = ? AND active = 1 ORDER BY id');
    $a->execute([$bank]);
    $accounts = $a->fetchAll();
    if (count($accounts) !== 1 || empty($cfg['allow_missing_account_mask_for_single_account'])) {
        json_response(['ok'=>false,'error'=>'account_mask_required'], 422);
    }
    $account = $accounts[0];
}

try {
    $insert = $pdo->prepare(
        'INSERT INTO bank_sms_events
        (event_id, device_id, receiving_account_id, bank_name, amount, reference_no,
         account_last4, sender_id, received_at, match_state)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, \'UNMATCHED\')'
    );
    $insert->execute([
        $eventId, $deviceId, $account['id'], $bank,
        $amount, $reference, $accountLast4 === '' ? null : $accountLast4,
        $sender, $receivedAtUtc,
    ]);
    $eventDbId = (int)$pdo->lastInsertId();

    // No customer UTR and no CSV step: verified CREDIT SMS is auto-matched here.
    $match = auto_match_bank_event($eventDbId);
    portal_record_relay_sms($eventDbId);
    if (empty($match['ok'])) {
        // Event is already stored, but matching had a temporary/internal failure.
        // Return a retriable HTTP status so Android WorkManager retries. The duplicate
        // branch below will re-run auto_match_bank_event() against the stored event.
        json_response([
            'ok'=>false,
            'stored'=>true,
            'event_db_id'=>$eventDbId,
            'error'=>'match_retry_required',
            'match'=>$match,
        ], 503);
    }
    json_response([
        'ok'=>true,
        'stored'=>true,
        'event_db_id'=>$eventDbId,
        'match'=>$match,
    ]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        // Idempotent Android retry. Re-run matching because the first request may have
        // stored the event successfully but failed during the later match transaction.
        $q = $pdo->prepare(
            'SELECT id, event_id, receiving_account_id, bank_name, amount, reference_no,
                    account_last4, match_state, matched_payment_id
             FROM bank_sms_events
             WHERE event_id = ? OR (bank_name = ? AND reference_no = ?) LIMIT 1'
        );
        $q->execute([$eventId, $bank, $reference]);
        $existing = $q->fetch();
        if (!$existing) {
            json_response(['ok'=>false,'error'=>'duplicate_event_lookup_failed'], 500);
        }

        // Same bank reference with a different amount/account is not an idempotent retry.
        // Reject the conflict for manual investigation instead of silently accepting it.
        if ((string)$existing['amount'] !== (string)$amount
            || (int)$existing['receiving_account_id'] !== (int)$account['id']) {
            json_response([
                'ok'=>false,
                'error'=>'duplicate_reference_conflict',
                'existing_event_id'=>(int)$existing['id'],
            ], 409);
        }

        $match = auto_match_bank_event((int)$existing['id']);
        portal_record_relay_sms((int)$existing['id']);
        if (empty($match['ok'])) {
            json_response([
                'ok'=>false,
                'stored'=>false,
                'duplicate'=>true,
                'existing'=>$existing,
                'error'=>'match_retry_required',
                'match'=>$match,
            ], 503);
        }
        json_response([
            'ok'=>true,
            'stored'=>false,
            'duplicate'=>true,
            'existing'=>$existing,
            'match'=>$match,
        ]);
    }
    json_response(['ok'=>false,'error'=>'database_error'], 500);
} catch (Throwable $e) {
    json_response(['ok'=>false,'error'=>'server_error'], 500);
}
