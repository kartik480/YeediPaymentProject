<?php
declare(strict_types=1);

/**
 * Bank credit-SMS auto match.
 * The Android relay POSTs /api/bank-sms. Signature is
 * HMAC-SHA256(secret, timestamp + newline + nonce + newline + raw JSON).
 * Customer verification rule:
 *   authenticated bank/SIM UPI CREDIT SMS + exact receiving account last-4
 *   + exact amount + mandatory bank UTR/RRN + one unambiguous pending payment.
 * Matching prefers the pending QR whose generation time is closest to the bank
 * credit (the scan-and-pay session). "I HAVE PAID" breaks ties when several
 * same-amount QRs are still open; it must not steal a credit from a fresher QR
 * on another customer/device that was actually paid.
 * Ambiguous same-amount payments stay REVIEW_REQUIRED instead of false success.
 */
final class BankSmsService {
    public const QR_TTL_SECONDS = 300;
    public const MATCH_WINDOW_SECONDS = 1800;
    /** Customer may tap I HAVE PAID several minutes after the bank SMS. */
    public const ACK_AFTER_SMS_SECONDS = 10800;
    public const CLOCK_SKEW_SECONDS = 120;
    public const HMAC_SKEW_SECONDS = 300;
    public const FUTURE_EVENT_TOLERANCE_SECONDS = 300;

    public static function qrTtlSeconds(): int {
        return max(60,min(900,Env::int('PAYMENT_QR_TTL_SECONDS',self::QR_TTL_SECONDS)));
    }

    /** Max seconds after QR generation that a bank credit may still belong to that attempt. */
    private static function qrToPaySeconds(): int {
        return max(self::MATCH_WINDOW_SECONDS, min(14400, Env::int('BANK_SMS_QR_TO_PAY_SECONDS', 7200)));
    }

    /** When two pending QRs compete, require this many seconds gap on QR-age to auto-pick. */
    private static function qrMatchSeparationSeconds(): int {
        return max(90, min(600, Env::int('BANK_SMS_QR_MATCH_SEPARATION_SECONDS', 180)));
    }

    private static bool $schemaReady = false;

    public static function ensureSchema(PDO $db): void {
        if (self::$schemaReady) return;
        $db->exec("CREATE TABLE IF NOT EXISTS bank_sms_devices (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            device_id VARCHAR(64) NOT NULL,
            secret_encrypted TEXT NOT NULL,
            bank_code VARCHAR(32) NOT NULL,
            account_last4 CHAR(4) NOT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_bank_sms_device (device_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE IF NOT EXISTS portal_bank_sms_events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            device_id VARCHAR(64) NOT NULL,
            android_event_id CHAR(64) NULL,
            bank_code VARCHAR(32) NOT NULL,
            amount DECIMAL(14,2) NOT NULL,
            utr VARCHAR(40) NULL,
            account_last4 CHAR(4) NOT NULL,
            sms_time DATETIME NOT NULL,
            raw_body TEXT NULL,
            match_status VARCHAR(32) NOT NULL,
            transaction_id BIGINT UNSIGNED NULL,
            candidate_refs VARCHAR(500) NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uq_bank_sms_utr (utr),
            UNIQUE KEY uq_bank_sms_android_event (device_id, android_event_id),
            INDEX idx_bank_sms_status (match_status, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE IF NOT EXISTS bank_sms_nonces (
            nonce VARCHAR(80) PRIMARY KEY,
            created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE IF NOT EXISTS bank_sms_device_accounts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            device_row_id BIGINT UNSIGNED NOT NULL,
            bank_code VARCHAR(32) NOT NULL,
            account_last4 CHAR(4) NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uq_device_bank_last4 (device_row_id, bank_code, account_last4),
            INDEX idx_device_accounts (device_row_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $devCols = [];
        foreach ($db->query('SHOW COLUMNS FROM bank_sms_devices') as $r) {
            $devCols[strtolower((string)$r['Field'])] = true;
        }
        if (!isset($devCols['last_seen_at'])) {
            $db->exec('ALTER TABLE bank_sms_devices ADD COLUMN last_seen_at DATETIME NULL');
        }
        $eventCols = [];
        foreach ($db->query('SHOW COLUMNS FROM portal_bank_sms_events') as $r) {
            $eventCols[strtolower((string)$r['Field'])] = [
                'type' => strtolower((string)$r['Type']),
                'null' => strtoupper((string)($r['Null'] ?? 'NO')),
            ];
        }
        if (isset($eventCols['utr'])) {
            $utrType = (string)$eventCols['utr']['type'];
            $utrNull = (string)$eventCols['utr']['null'];
            if ($utrType !== 'varchar(40)' || $utrNull !== 'YES') {
                $db->exec('ALTER TABLE portal_bank_sms_events MODIFY utr VARCHAR(40) NULL');
            }
        }
        if (!isset($eventCols['sender_id'])) {
            $db->exec('ALTER TABLE portal_bank_sms_events ADD COLUMN sender_id VARCHAR(80) NULL AFTER account_last4');
        }
        if (!isset($eventCols['android_event_id'])) {
            $db->exec('ALTER TABLE portal_bank_sms_events ADD COLUMN android_event_id CHAR(64) NULL AFTER device_id');
        }
        $eventIndexes = [];
        foreach ($db->query('SHOW INDEX FROM portal_bank_sms_events') as $r) {
            $eventIndexes[strtolower((string)$r['Key_name'])] = true;
        }
        if (!isset($eventIndexes['uq_bank_sms_android_event'])) {
            try {
                $db->exec('ALTER TABLE portal_bank_sms_events ADD UNIQUE KEY uq_bank_sms_android_event(device_id, android_event_id)');
            } catch (Throwable $e) {
                // Legacy duplicate rows can be cleaned manually; matching still fails closed.
            }
        }
        if (!isset($eventIndexes['idx_bank_sms_lookup'])) {
            try {
                $db->exec('ALTER TABLE portal_bank_sms_events ADD INDEX idx_bank_sms_lookup(bank_code, amount, sms_time)');
            } catch (Throwable $e) {
                // Performance index only; matching remains correct without it.
            }
        }
        if (!isset($eventCols['candidate_customers'])) {
            $db->exec('ALTER TABLE portal_bank_sms_events ADD COLUMN candidate_customers VARCHAR(500) NULL AFTER candidate_refs');
        }
        $cols = [];
        foreach ($db->query('SHOW COLUMNS FROM transactions') as $r) {
            $cols[strtolower((string)$r['Field'])] = true;
        }
        if (!isset($cols['sms_match'])) {
            $db->exec('ALTER TABLE transactions ADD COLUMN sms_match VARCHAR(32) NULL');
        }
        self::provisionRelayDevice($db);
        self::$schemaReady = true;
    }

    /** Register the office relay phone from storage/adc_relay_device.txt when that file exists. */
    private static function provisionRelayDevice(PDO $db): void {
        $path = dirname(__DIR__).'/storage/adc_relay_device.txt';
        if (!is_file($path)) return;
        $deviceId = '';
        $secret = '';
        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (str_starts_with($line, 'DEVICE_ID=')) $deviceId = trim(substr($line, 10));
            if (str_starts_with($line, 'API_SECRET=')) $secret = trim(substr($line, 11));
        }
        if ($deviceId === '' || $secret === '') return;
        $bank = $db->query("SELECT code FROM bank_profiles WHERE enabled=1 AND (code LIKE '%ICICI%' OR bank_name LIKE '%ICICI%' OR vpa LIKE '%icici%') ORDER BY id LIMIT 1")->fetch();
        if (!$bank) {
            $bank = $db->query('SELECT code FROM bank_profiles WHERE enabled=1 ORDER BY id LIMIT 1')->fetch();
        }
        if (!$bank) return;
        $code = (string)$bank['code'];
        $last4 = '4270';
        $existing = $db->prepare('SELECT id, secret_encrypted, active FROM bank_sms_devices WHERE device_id=? LIMIT 1');
        $existing->execute([$deviceId]);
        $row = $existing->fetch();
        if (!$row) {
            $db->prepare('INSERT INTO bank_sms_devices(name,device_id,secret_encrypted,bank_code,account_last4,active,created_at,updated_at) VALUES(?,?,?,?,?,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())')
                ->execute(['Office parser', $deviceId, Crypto::encrypt($secret), $code, $last4]);
        } else {
            $current = Crypto::decrypt((string)$row['secret_encrypted']);
            if (!hash_equals($secret, $current) || !(int)$row['active']) {
                $db->prepare('UPDATE bank_sms_devices SET secret_encrypted=?, active=1, bank_code=?, account_last4=?, updated_at=UTC_TIMESTAMP() WHERE id=?')
                    ->execute([Crypto::encrypt($secret), $code, $last4, (int)$row['id']]);
            }
        }
        if ($db->query("SHOW TABLES LIKE 'sms_relay_devices'")->fetchColumn()) {
            $relay = $db->prepare('SELECT id, api_secret, active FROM sms_relay_devices WHERE device_id=? LIMIT 1');
            $relay->execute([$deviceId]);
            $adc = $relay->fetch();
            if (!$adc) {
                $db->prepare('INSERT INTO sms_relay_devices(device_id,device_label,api_secret,active,created_at) VALUES(?,?,?,1,UTC_TIMESTAMP())')
                    ->execute([$deviceId, 'Office parser', $secret]);
            } elseif (!hash_equals($secret, (string)$adc['api_secret']) || !(int)$adc['active']) {
                $db->prepare('UPDATE sms_relay_devices SET api_secret=?, active=1, device_label=? WHERE id=?')
                    ->execute([$secret, 'Office parser', (int)$adc['id']]);
            }
        }
    }

    public static function qrSecondsLeft(array $t): int {
        if (strtoupper((string)($t['status'] ?? '')) !== 'PENDING') return 0;
        $created = strtotime((string)($t['created_at'] ?? '').' UTC');
        if ($created === false) return self::qrTtlSeconds();
        return max(0, self::qrTtlSeconds() - (time() - $created));
    }

    /** waiting | review | unmatched | success | closed */
    public static function customerView(array $t): string {
        $st = strtoupper((string)($t['status'] ?? ''));
        if ($st === 'SUCCESS') return 'success';
        if (in_array($st, ['FAILED', 'EXPIRED'], true)) return 'closed';
        if (strtoupper((string)($t['sms_match'] ?? '')) === 'REVIEW_REQUIRED') return 'review';
        // Once the customer explicitly acknowledges payment, do not turn the page
        // into "unmatched" merely because the initial QR display timer elapsed.
        // The bank relay can arrive later while preserving the original SMS event time.
        if (!empty($t['customer_paid_ack_at'])) return 'waiting';
        if (self::qrSecondsLeft($t) <= 0) return 'unmatched';
        return 'waiting';
    }

    public static function formatLocal(?string $utc): string {
        $utc = trim((string)$utc);
        if ($utc === '') return '';
        try {
            $dt = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
        } catch (Throwable $e) {
            return $utc;
        }
        $tz = new DateTimeZone(Env::get('APP_TIMEZONE', 'Asia/Kolkata') ?: 'Asia/Kolkata');
        return $dt->setTimezone($tz)->format('d-m-Y H:i:s');
    }

    public static function displayUtr(array $t): string {
        foreach (['rrn', 'upi_txn_id'] as $k) {
            $v = trim((string)($t[$k] ?? ''));
            if ($v !== '') return $v;
        }
        return '';
    }

    /** @return array{device_id:string,secret:string} */
    public static function createDevice(PDO $db, string $name, string $bankCode, string $last4): array {
        self::ensureSchema($db);
        $name = trim($name);
        $bankCode = strtoupper(trim($bankCode));
        $last4 = trim($last4);
        if ($name === '' || strlen($name) > 120) throw new InvalidArgumentException('Enter a parser name');
        if (!preg_match('/^\d{4}$/', $last4)) throw new InvalidArgumentException('Account last 4 digits are required');
        $bank = self::bankByCode($db, $bankCode);
        if (!$bank) throw new InvalidArgumentException('Choose an enabled bank');
        $deviceId = 'YP'.strtoupper(bin2hex(random_bytes(4)));
        $secret = bin2hex(random_bytes(24));
        $st = $db->prepare('INSERT INTO bank_sms_devices(name,device_id,secret_encrypted,bank_code,account_last4,active,created_at,updated_at) VALUES(?,?,?,?,?,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
        $st->execute([$name, $deviceId, Crypto::encrypt($secret), (string)$bank['code'], $last4]);
        return ['device_id' => $deviceId, 'secret' => $secret];
    }

    public static function toggleDevice(PDO $db, int $id): void {
        self::ensureSchema($db);
        $db->prepare('UPDATE bank_sms_devices SET active=1-active, updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$id]);
    }

    /** @return list<array<string,mixed>> */
    public static function devices(PDO $db): array {
        self::ensureSchema($db);
        $rows = $db->query('SELECT id,name,device_id,bank_code,account_last4,active,created_at,last_seen_at FROM bank_sms_devices ORDER BY id DESC')->fetchAll();
        $extras = $db->query('SELECT device_row_id,bank_code,account_last4 FROM bank_sms_device_accounts ORDER BY id')->fetchAll();
        $by = [];
        foreach ($extras as $extra) {
            $by[(int)$extra['device_row_id']][] = [
                'bank_code' => (string)$extra['bank_code'],
                'account_last4' => (string)$extra['account_last4'],
            ];
        }
        foreach ($rows as &$row) {
            $accounts = [[
                'bank_code' => (string)$row['bank_code'],
                'account_last4' => (string)$row['account_last4'],
            ]];
            foreach ($by[(int)$row['id']] ?? [] as $extra) {
                $accounts[] = $extra;
            }
            $row['accounts'] = $accounts;
        }
        unset($row);
        return $rows;
    }

    public static function addAccount(PDO $db, int $deviceRowId, string $bankCode, string $last4): void {
        self::ensureSchema($db);
        $device = self::deviceById($db, $deviceRowId);
        if (!$device) throw new InvalidArgumentException('Parser phone not found');
        $bankCode = strtoupper(trim($bankCode));
        $last4 = trim($last4);
        if (!preg_match('/^\d{4}$/', $last4)) throw new InvalidArgumentException('Account last 4 digits are required');
        $bank = self::bankByCode($db, $bankCode);
        if (!$bank) throw new InvalidArgumentException('Choose an enabled bank');
        $bankCode = (string)$bank['code'];
        $newFamily = self::accountBankKey($db, $bankCode);
        foreach (self::deviceAccounts($db, $device) as $account) {
            $sameLast4 = hash_equals((string)$account['account_last4'], $last4);
            $sameCode = strtoupper((string)$account['bank_code']) === $bankCode;
            $sameFamily = self::accountBankKey($db, (string)$account['bank_code']) === $newFamily;
            if ($sameLast4 && ($sameCode || $sameFamily)) {
                $label = (string)$account['bank_code'].' · '.$last4;
                if ($sameFamily && $newFamily !== '' && $newFamily !== self::bankKey($bankCode)) {
                    $label .= ' (same as '.$newFamily.' receiving account)';
                }
                throw new InvalidArgumentException('That receiving account is already on this phone: '.$label);
            }
        }
        $db->prepare('INSERT INTO bank_sms_device_accounts(device_row_id,bank_code,account_last4,created_at) VALUES(?,?,?,UTC_TIMESTAMP())')->execute([$deviceRowId, $bankCode, $last4]);
    }

    /** @return list<array<string,mixed>> */
    public static function events(PDO $db, int $limit = 80): array {
        self::ensureSchema($db);
        $limit = max(1, min(200, $limit));
        $rows = $db->query(
            'SELECT e.*, t.reference, c.code customer_code
             FROM portal_bank_sms_events e
             LEFT JOIN transactions t ON t.id=e.transaction_id
             LEFT JOIN customers c ON c.id=t.customer_id
             ORDER BY e.id DESC LIMIT '.$limit
        )->fetchAll();
        $seen = [];
        foreach ($rows as $row) $seen[strtoupper((string)$row['utr'])] = true;
        if (!$db->query("SHOW TABLES LIKE 'bank_sms_events'")->fetchColumn()) return $rows;
        $cols = [];
        foreach ($db->query('SHOW COLUMNS FROM bank_sms_events') as $c) $cols[strtolower((string)$c['Field'])] = true;
        if (!isset($cols['reference_no'], $cols['match_state'])) return $rows;
        $extra = $db->query(
            'SELECT e.id, e.device_id, COALESCE(a.account_key, e.bank_name) bank_code, e.amount, e.reference_no utr,
                    IFNULL(e.account_last4, \'\') account_last4, e.sender_id, e.received_at sms_time, e.match_state match_status,
                    o.merchant_order_id reference
             FROM bank_sms_events e
             LEFT JOIN bank_receiving_accounts a ON a.id=e.receiving_account_id
             LEFT JOIN qr_payment_orders o ON o.id=e.matched_payment_id
             ORDER BY e.id DESC LIMIT '.$limit
        )->fetchAll();
        foreach ($extra as $row) {
            $utr = strtoupper((string)$row['utr']);
            if ($utr === '' || isset($seen[$utr])) continue;
            $state = strtoupper((string)$row['match_status']);
            $row['match_status'] = $state === 'MATCHED' ? 'SUCCESS' : ($state === 'AMBIGUOUS' ? 'REVIEW_REQUIRED' : 'UNMATCHED');
            $row['id'] = 0;
            $rows[] = $row;
            $seen[$utr] = true;
        }
        usort($rows, static fn(array $a, array $b): int => strcmp((string)($b['sms_time'] ?? ''), (string)($a['sms_time'] ?? '')));
        return array_slice($rows, 0, $limit);
    }

    /** @return list<array<string,mixed>> */
    public static function pendingPayments(PDO $db): array {
        self::ensureSchema($db);
        return $db->query("SELECT t.id,t.reference,t.requested_amount,t.sms_match,t.created_at,b.bank_name,b.code bank_code FROM transactions t JOIN bank_profiles b ON b.id=t.bank_id WHERE t.status='PENDING' AND t.customer_paid_ack_at IS NOT NULL AND t.sms_match='REVIEW_REQUIRED' ORDER BY t.id DESC LIMIT 100")->fetchAll();
    }

    public static function ingestHttp(PDO $db): never {
        self::ensureSchema($db);
        $raw = (string)file_get_contents('php://input');
        $deviceId = trim((string)($_SERVER['HTTP_X_DEVICE_ID'] ?? ''));
        $timestamp = trim((string)($_SERVER['HTTP_X_TIMESTAMP'] ?? ''));
        $nonce = trim((string)($_SERVER['HTTP_X_NONCE'] ?? ''));
        $signature = strtolower(trim((string)($_SERVER['HTTP_X_SIGNATURE'] ?? '')));
        if ($deviceId === '' || $timestamp === '' || $signature === '') {
            json_response(['ok' => false, 'error' => 'Missing HMAC headers'], 401);
        }
        if (!preg_match('/^\d{10,13}$/', $timestamp)) {
            json_response(['ok' => false, 'error' => 'Invalid timestamp'], 401);
        }
        $ts = (int)$timestamp;
        if ($ts > 20000000000) $ts = (int)floor($ts / 1000);
        if (abs(time() - $ts) > self::HMAC_SKEW_SECONDS) {
            json_response(['ok' => false, 'error' => 'Stale timestamp'], 401);
        }
        $device = self::activeDevice($db, $deviceId);
        if (!$device) json_response(['ok' => false, 'error' => 'Unknown device'], 401);
        $secret = Crypto::decrypt((string)$device['secret_encrypted']);
        if ($secret === '') json_response(['ok' => false, 'error' => 'Device secret unavailable'], 401);
        if ($nonce === '') {
            $expected = hash_hmac('sha256', $timestamp."\n".$raw, $secret);
        } else {
            if (!preg_match('/^[A-Za-z0-9_-]{8,80}$/', $nonce)) {
                json_response(['ok' => false, 'error' => 'Invalid nonce'], 401);
            }
            $expected = hash_hmac('sha256', $timestamp."\n".$nonce."\n".$raw, $secret);
        }
        if (!hash_equals($expected, $signature)) {
            json_response(['ok' => false, 'error' => 'Bad signature'], 401);
        }
        if ($nonce !== '') {
            $db->exec('DELETE FROM bank_sms_nonces WHERE created_at < (UTC_TIMESTAMP() - INTERVAL 1 DAY)');
            try {
                $db->prepare('INSERT INTO bank_sms_nonces(nonce,created_at) VALUES(?,UTC_TIMESTAMP())')->execute([$nonce]);
            } catch (PDOException $e) {
                json_response(['ok' => false, 'error' => 'Replayed nonce'], 401);
            }
        }
        $db->prepare('UPDATE bank_sms_devices SET last_seen_at=UTC_TIMESTAMP(), updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([(int)$device['id']]);
        $data = json_decode($raw, true);
        if (!is_array($data)) json_response(['ok' => false, 'error' => 'Invalid JSON'], 400);
        if (!empty($data['is_test'])) {
            json_response(['ok' => true, 'test' => true, 'device_id' => $deviceId]);
        }
        $channel = strtoupper(trim((string)($data['channel'] ?? 'UPI')));
        if ($channel !== 'UPI') json_response(['ok' => false, 'error' => 'Unsupported channel'], 422);
        if (!isset($data['utr']) && isset($data['reference'])) $data['utr'] = $data['reference'];
        if (trim((string)($data['sms_time'] ?? '')) === '' && isset($data['received_at'])) {
            $data['sms_time'] = $data['received_at'];
        }
        $data['device_id'] = $deviceId;
        $data['sender'] = trim((string)($data['sender'] ?? ''));
        $data['event_id'] = strtolower(trim((string)($data['event_id'] ?? '')));
        try {
            for ($try = 0; $try < 2; $try++) {
                try {
                    $out = self::ingest($db, $data, $device);
                    json_response($out, 200);
                } catch (InvalidArgumentException $e) {
                    json_response(['ok' => false, 'error' => $e->getMessage()], 400);
                } catch (Throwable $e) {
                    if ($db->inTransaction()) {
                        try { $db->rollBack(); } catch (Throwable $ignored) {}
                    }
                    $msg = $e->getMessage();
                    $retryable = str_contains($msg, '1213') || str_contains($msg, '1205') || stripos($msg, 'deadlock') !== false || stripos($msg, 'lock wait') !== false;
                    if (!$retryable || $try === 1) break;
                    usleep(200000);
                }
            }
            $kept = self::keepAcceptedSms($db, $data, $device);
            if ($kept) json_response($kept, 200);
            json_response(['ok' => false, 'error' => 'Could not process SMS'], 500);
        } catch (Throwable $e) {
            json_response(['ok' => false, 'error' => 'Could not process SMS'], 500);
        }
    }

    public static function deviceById(PDO $db, int $id): ?array {
        self::ensureSchema($db);
        $st = $db->prepare('SELECT * FROM bank_sms_devices WHERE id=?');
        $st->execute([$id]);
        $r = $st->fetch();
        return $r ?: null;
    }

    /**
     * Admin console uses the same matcher as the Android parser.
     * @param array<string,mixed> $device
     * @return array<string,mixed>
     */
    public static function ingestParsedSms(PDO $db, array $device, string $rawSms): array {
        self::ensureSchema($db);
        $parsed = self::parseCreditSms($rawSms);
        $last4 = (string)$parsed['account_last4'];
        $hits = [];
        foreach (self::deviceAccounts($db, $device) as $account) {
            if (hash_equals($account['account_last4'], $last4)) $hits[] = $account;
        }
        if (!$hits) throw new InvalidArgumentException('SMS account last 4 does not match this phone');
        if (count($hits) > 1) throw new InvalidArgumentException('Account last 4 matches more than one bank on this phone');
        return self::ingest($db, [
            'bank' => (string)$hits[0]['bank_code'],
            'amount' => $parsed['amount'],
            'utr' => $parsed['utr'],
            'account_last4' => $last4,
            'sms_time' => $parsed['sms_time'],
            'device_id' => (string)$device['device_id'],
            'raw_sms' => $rawSms,
        ], $device);
    }

    /**
     * @param array<string,mixed> $in
     * @param array<string,mixed> $device
     * @return array<string,mixed>
     */
    public static function ingest(PDO $db, array $in, array $device): array {
        self::ensureSchema($db);
        TransactionService::ensureSchema($db);

        $bankCode = strtoupper(trim((string)($in['bank'] ?? '')));
        $utr = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string)($in['utr'] ?? '')) ?? '');
        $last4 = trim((string)($in['account_last4'] ?? ''));
        $amount = self::normalizeAmount($in['amount'] ?? '');
        $smsUtc = self::parseSmsTime((string)($in['sms_time'] ?? ''));
        $smsEpoch = $smsUtc->getTimestamp();
        $nowEpoch = time();
        $maxAgeHours = (int)(Env::get('BANK_SMS_MAX_EVENT_AGE_HOURS', '24') ?: 24);
        $maxAgeHours = max(1, min(168, $maxAgeHours));
        if ($smsEpoch > $nowEpoch + self::FUTURE_EVENT_TOLERANCE_SECONDS) {
            throw new InvalidArgumentException('SMS time is too far in the future');
        }
        if ($smsEpoch < $nowEpoch - ($maxAgeHours * 3600)) {
            throw new InvalidArgumentException('SMS event is too old');
        }
        $rawSms = trim((string)($in['raw_sms'] ?? ''));
        if ($rawSms !== '' && strlen($rawSms) > 4000) $rawSms = substr($rawSms, 0, 4000);
        $sender = trim((string)($in['sender'] ?? ''));
        if (strlen($sender) > 80) $sender = substr($sender, 0, 80);
        $eventKey = strtolower(trim((string)($in['event_id'] ?? '')));
        if ($eventKey !== '' && !preg_match('/^[a-f0-9]{64}$/', $eventKey)) $eventKey = '';

        if ($utr === '') {
            throw new InvalidArgumentException('Bank UTR/RRN reference is required');
        }
        if (!preg_match('/^[A-Z0-9]{8,35}$/', $utr)) {
            throw new InvalidArgumentException('Bank reference format is not valid');
        }
        if (!preg_match('/^\d{4}$/', $last4)) {
            throw new InvalidArgumentException('Account last 4 is required');
        }
        if (!hash_equals((string)$device['device_id'], trim((string)($in['device_id'] ?? '')))) {
            throw new InvalidArgumentException('Device does not match');
        }
        if (!(int)$device['active']) throw new InvalidArgumentException('Device is disabled');

        // Exact receiving account is mandatory for this locked flow.
        // The SMS must contain account last-4 and it must match the account registered
        // for this relay phone and bank profile.
        $account = self::matchingAccount($db, $device, $bankCode, $last4);
        if (!$account) throw new InvalidArgumentException('Bank/account cannot be uniquely mapped to this relay phone');
        $bankCode = strtoupper((string)$account['bank_code']);
        $last4 = (string)$account['account_last4'];

        $banks = self::matchingBanks($db, $bankCode);
        if (!$banks) throw new InvalidArgumentException('No enabled bank matches '.$bankCode);
        $ids = self::relayCollectBankIds($db, $bankCode);

        $existing = null;
        if ($utr !== '') $existing = self::eventByUtr($db, $utr);
        if (!$existing && $eventKey !== '') $existing = self::eventByAndroidEvent($db, (string)$device['device_id'], $eventKey);
        if ($existing && strtoupper((string)$existing['match_status']) === 'SUCCESS') {
            return self::eventResult($existing, true);
        }

        if ($utr !== '' && !$existing) {
            $used = $db->prepare("SELECT reference FROM transactions
                WHERE rrn=? OR upi_txn_id=?
                LIMIT 1");
            $used->execute([$utr, $utr]);
            if ($used->fetchColumn()) throw new InvalidArgumentException('Bank reference was already used');
        }

        self::warmSchemas($db);
        $db->beginTransaction();
        try {
            $again = null;
            if ($utr !== '') $again = self::eventByUtr($db, $utr, true);
            if (!$again && $eventKey !== '') $again = self::eventByAndroidEvent($db, (string)$device['device_id'], $eventKey, true);
            if ($again && strtoupper((string)$again['match_status']) === 'SUCCESS') {
                $db->commit();
                return self::eventResult($again, true);
            }

            $hits = self::resolvePaymentsForSms($db, $amount, $ids, $smsUtc);
            if (count($hits) === 1) {
                $lockTxn = $db->prepare('SELECT id, status FROM transactions WHERE id=? FOR UPDATE');
                $lockTxn->execute([(int)$hits[0]['id']]);
                $locked = $lockTxn->fetch();
                if (!$locked || strtoupper((string)$locked['status']) !== 'PENDING') {
                    $hits = [];
                }
            }

            $refs = array_map(static fn(array $r): string => (string)$r['reference'], $hits);
            $refText = substr(implode(',', $refs), 0, 500);
            $status = count($hits) === 1 ? 'SUCCESS' : (count($hits) > 1 ? 'REVIEW_REQUIRED' : 'UNMATCHED');
            $txnId = count($hits) === 1 ? (int)$hits[0]['id'] : null;
            $codeRows = $hits;
            if (!$codeRows) {
                $codeRows = self::pendingPoolForSms($db, $amount, $ids, $smsUtc);
            }
            $customerCodes = self::customerCodesFromTxnRows($db, $codeRows);

            if ($again) {
                $db->prepare('UPDATE portal_bank_sms_events
                              SET bank_code=?, amount=?, utr=?, account_last4=?, sender_id=?,
                                  match_status=?, transaction_id=?, candidate_refs=?, candidate_customers=?, sms_time=?
                              WHERE id=?')->execute([
                    $bankCode,
                    $amount,
                    $utr !== '' ? $utr : null,
                    $last4,
                    $sender !== '' ? $sender : null,
                    $status,
                    $txnId,
                    $refText !== '' ? $refText : null,
                    $customerCodes !== '' ? $customerCodes : null,
                    $smsUtc->format('Y-m-d H:i:s'),
                    (int)$again['id'],
                ]);
                $eventId = (int)$again['id'];
            } else {
                $ins = $db->prepare('INSERT INTO portal_bank_sms_events(
                    device_id,android_event_id,bank_code,amount,utr,account_last4,sender_id,
                    sms_time,raw_body,match_status,transaction_id,candidate_refs,candidate_customers,created_at
                ) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP())');
                $ins->execute([
                    (string)$device['device_id'],
                    $eventKey !== '' ? $eventKey : null,
                    $bankCode,
                    $amount,
                    $utr !== '' ? $utr : null,
                    $last4,
                    $sender !== '' ? $sender : null,
                    $smsUtc->format('Y-m-d H:i:s'),
                    $rawSms !== '' ? $rawSms : null,
                    $status,
                    $txnId,
                    $refText !== '' ? $refText : null,
                    $customerCodes !== '' ? $customerCodes : null,
                ]);
                $eventId = (int)$db->lastInsertId();
            }

            if ($status === 'SUCCESS') {
            self::confirmFromSmsEvent($db, $eventId, [
                'amount' => $amount,
                'utr' => $utr,
                'account_last4' => $last4,
                'bank_code' => $bankCode,
            ], $hits[0]);
            self::clearSameCustomerReview($db, (int)($hits[0]['customer_id'] ?? 0), $amount, (int)$hits[0]['id']);
            } elseif ($status === 'REVIEW_REQUIRED') {
                $idList = array_map(static fn(array $r): int => (int)$r['id'], $hits);
                $m2 = implode(',', array_fill(0, count($idList), '?'));
                $db->prepare("UPDATE transactions
                              SET sms_match='REVIEW_REQUIRED', updated_at=UTC_TIMESTAMP()
                              WHERE id IN ($m2)")->execute($idList);
            }

            if ($db->inTransaction()) $db->commit();
            $saved = self::eventById($db, $eventId);
            if ($status === 'UNMATCHED' && $saved) {
                $pool = self::pendingPoolForSms($db, $amount, $ids, $smsUtc);
                $best = self::pickBestPaymentForSms($pool, $smsUtc);
                if ($best && self::transactionEligibleForSmsCredit($best, $smsUtc)) {
                    try {
                        self::matchAcknowledgedTransaction($db, $best);
                        $saved = self::eventById($db, $eventId) ?: $saved;
                    } catch (Throwable $e) {
                        error_log('Bank SMS post-ingest rematch failed: '.$e->getMessage());
                    }
                }
            }
            return self::eventResult($saved ?: [
                'match_status' => $status,
                'utr' => $utr,
                'reference' => $refs[0] ?? null,
                'candidate_refs' => $refText,
            ], false);
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            if ($e instanceof PDOException) {
                $msg = $e->getMessage();
                if ($utr !== '' && str_contains($msg, 'uq_bank_sms_utr')) {
                    $dup = self::eventByUtr($db, $utr);
                    if ($dup) return self::eventResult($dup, true);
                }
                if ($eventKey !== '' && str_contains($msg, 'uq_bank_sms_android_event')) {
                    $dup = self::eventByAndroidEvent($db, (string)$device['device_id'], $eventKey);
                    if ($dup) return self::eventResult($dup, true);
                }
            }
            if ($e instanceof InvalidArgumentException || $e instanceof RuntimeException) throw $e;
            throw new RuntimeException('Could not process bank SMS');
        }
    }

    /**
     * When a customer taps I HAVE PAID, tag open bank SMS rows with their customer code
     * so admin matching and reverse lookup can prefer that payer.
     *
     * @param array<string,mixed> $t
     */
    public static function tagUnmatchedSmsForCustomerAck(PDO $db, array $t): void {
        self::ensureSchema($db);
        $customerId = (int)($t['customer_id'] ?? 0);
        if ($customerId <= 0) return;
        $code = strtoupper(trim((string)($t['customer_code'] ?? '')));
        if ($code === '') {
            $st = $db->prepare('SELECT code FROM customers WHERE id=? LIMIT 1');
            $st->execute([$customerId]);
            $code = strtoupper(trim((string)$st->fetchColumn()));
        }
        if ($code === '') return;
        $amount = number_format((float)($t['requested_amount'] ?? 0), 2, '.', '');
        if ((float)$amount < 0.01) return;
        $bank = self::bankById($db, (int)($t['bank_id'] ?? 0));
        if (!$bank) return;
        $created = new DateTimeImmutable((string)$t['created_at'], new DateTimeZone('UTC'));
        $ack = new DateTimeImmutable((string)$t['customer_paid_ack_at'], new DateTimeZone('UTC'));
        $from = $created->modify('-'.self::CLOCK_SKEW_SECONDS.' seconds')->format('Y-m-d H:i:s');
        $to = $ack->modify('+'.self::ACK_AFTER_SMS_SECONDS.' seconds')->format('Y-m-d H:i:s');
        $st = $db->prepare("SELECT id, bank_code, candidate_customers FROM portal_bank_sms_events
                            WHERE transaction_id IS NULL
                              AND match_status IN ('UNMATCHED','REVIEW_REQUIRED')
                              AND ROUND(amount, 2) = ROUND(?, 2)
                              AND sms_time>=? AND sms_time<=?");
        $st->execute([$amount, $from, $to]);
        foreach ($st->fetchAll() as $row) {
            $ids = self::relayCollectBankIds($db, (string)($row['bank_code'] ?? ''));
            if (!in_array((int)$bank['id'], $ids, true)) continue;
            $merged = self::mergeCustomerCodes((string)($row['candidate_customers'] ?? ''), [$code]);
            $db->prepare('UPDATE portal_bank_sms_events SET candidate_customers=? WHERE id=?')
                ->execute([$merged !== '' ? $merged : null, (int)$row['id']]);
        }
    }

    /**
     * Called immediately after the customer taps I HAVE PAID.
     * If the bank SMS reached the server first, this performs the reverse lookup.
     */
    public static function matchAcknowledgedTransaction(PDO $db, array $t): array {
        self::ensureSchema($db);
        TransactionService::ensureSchema($db);
        if (strtoupper((string)($t['status'] ?? '')) !== 'PENDING' || empty($t['customer_paid_ack_at'])) {
            return $t;
        }

        $bank = self::bankById($db, (int)$t['bank_id']);
        if (!$bank) return $t;
        $amount = number_format((float)$t['requested_amount'], 2, '.', '');

        $created = new DateTimeImmutable((string)$t['created_at'], new DateTimeZone('UTC'));
        $ack = new DateTimeImmutable((string)$t['customer_paid_ack_at'], new DateTimeZone('UTC'));
        $from = $created->modify('-'.self::CLOCK_SKEW_SECONDS.' seconds')->format('Y-m-d H:i:s');
        $to = $ack->modify('+'.self::ACK_AFTER_SMS_SECONDS.' seconds')->format('Y-m-d H:i:s');

        $st = $db->prepare("SELECT * FROM portal_bank_sms_events
                            WHERE transaction_id IS NULL
                              AND match_status IN ('UNMATCHED','REVIEW_REQUIRED')
                              AND utr IS NOT NULL AND utr<>''
                              AND account_last4 REGEXP '^[0-9]{4}$'
                              AND ROUND(amount, 2) = ROUND(?, 2)
                              AND sms_time>=?
                              AND sms_time<=?
                            ORDER BY sms_time ASC, id ASC");
        $st->execute([$amount, $from, $to]);
        $events = array_values(array_filter($st->fetchAll(), static function(array $event) use ($db, $bank): bool {
            $ids = BankSmsService::relayCollectBankIds($db, (string)$event['bank_code']);
            return in_array((int)$bank['id'], $ids, true);
        }));

        if (!$events) {
            return TransactionService::txnByRef($db, (string)$t['reference']) ?: $t;
        }

        $ackCustomerCode = strtoupper(trim((string)($t['customer_code'] ?? '')));
        if ($ackCustomerCode !== '') {
            $scoped = array_values(array_filter($events, static function (array $event) use ($ackCustomerCode): bool {
                $candidates = strtoupper(trim((string)($event['candidate_customers'] ?? '')));
                if ($candidates === '') return true;
                foreach (explode(',', $candidates) as $part) {
                    if (strtoupper(trim($part)) === $ackCustomerCode) return true;
                }
                return false;
            }));
            if ($scoped) $events = $scoped;
        }

        usort($events, static function (array $a, array $b): int {
            $cmp = strcmp((string)($b['sms_time'] ?? ''), (string)($a['sms_time'] ?? ''));
            return $cmp !== 0 ? $cmp : ((int)$b['id'] <=> (int)$a['id']);
        });

        $event = null;
        $ambiguous = false;
        foreach ($events as $candidate) {
            $smsUtc = new DateTimeImmutable((string)$candidate['sms_time'], new DateTimeZone('UTC'));
            $ids = self::relayCollectBankIds($db, (string)$candidate['bank_code']);
            if (!$ids) continue;
            $hits = self::resolvePaymentsForSms($db, $amount, $ids, $smsUtc);

            if (count($hits) === 1 && (int)$hits[0]['id'] === (int)$t['id']) {
                $event = $candidate;
                break;
            }
            if (!$hits && in_array((int)$bank['id'], $ids, true)) {
                $pool = self::pendingPoolForSms($db, $amount, $ids, $smsUtc);
                $best = self::pickBestPaymentForSms($pool, $smsUtc);
                if ($best && (int)$best['id'] === (int)$t['id']) {
                    $event = $candidate;
                    break;
                }
            }
            if (count($hits) > 1) {
                $ambiguous = true;
                $refs = array_map(static fn(array $r): string => (string)$r['reference'], $hits);
                $refText = substr(implode(',', $refs), 0, 500);
                $db->prepare("UPDATE portal_bank_sms_events
                              SET match_status='REVIEW_REQUIRED', candidate_refs=?
                              WHERE id=? AND transaction_id IS NULL")->execute([
                    $refText !== '' ? $refText : null,
                    (int)$candidate['id'],
                ]);
            }
        }

        if (!$event && $events) {
            $ids = self::relayCollectBankIds($db, (string)$events[0]['bank_code']);
            if ($ids && in_array((int)$bank['id'], $ids, true)) {
                $smsUtc = new DateTimeImmutable((string)$events[0]['sms_time'], new DateTimeZone('UTC'));
                $pool = self::pendingPoolForSms($db, $amount, $ids, $smsUtc);
                $best = self::pickBestPaymentForSms($pool, $smsUtc);
                if ($best && (int)$best['id'] === (int)$t['id']) {
                    $event = $events[0];
                }
            }
        }

        if (!$event) {
            if ($ambiguous) {
                $db->prepare("UPDATE transactions
                              SET sms_match='REVIEW_REQUIRED', updated_at=UTC_TIMESTAMP()
                              WHERE id=? AND status='PENDING'")->execute([(int)$t['id']]);
            }
            return TransactionService::txnByRef($db, (string)$t['reference']) ?: $t;
        }

        self::warmSchemas($db);
        $db->beginTransaction();
        try {
            $lock = $db->prepare('SELECT * FROM portal_bank_sms_events WHERE id=? FOR UPDATE');
            $lock->execute([(int)$event['id']]);
            $lockedEvent = $lock->fetch();
            if (!$lockedEvent || !empty($lockedEvent['transaction_id'])) {
                if ($db->inTransaction()) $db->commit();
                return TransactionService::txnByRef($db, (string)$t['reference']) ?: $t;
            }
            $lockedTxn = $db->prepare("SELECT id,reference,bank_id,status,requested_amount
                                       FROM transactions WHERE id=? FOR UPDATE");
            $lockedTxn->execute([(int)$t['id']]);
            $tx = $lockedTxn->fetch();
            if (!$tx || strtoupper((string)$tx['status']) !== 'PENDING') {
                if ($db->inTransaction()) $db->commit();
                return TransactionService::txnByRef($db, (string)$t['reference']) ?: $t;
            }
            self::confirmFromSmsEvent($db, (int)$event['id'], $lockedEvent, $tx);
            self::clearSameCustomerReview($db, (int)($t['customer_id'] ?? 0), $amount, (int)$t['id']);
            if ($db->inTransaction()) $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }

        return TransactionService::txnByRef($db, (string)$t['reference']) ?: $t;
    }

    private static function warmSchemas(PDO $db): void {
        self::ensureSchema($db);
        TransactionService::ensureSchema($db);
        WalletService::ensureSchema($db);
        EmailService::ensureSchema($db);
    }

    private static function confirmFromSmsEvent(PDO $db, int $eventId, array $event, array $t): void {
        $bank = self::bankById($db, (int)$t['bank_id']);
        if (!$bank) throw new RuntimeException('Bank not found');
        $amount = number_format((float)$event['amount'], 2, '.', '');
        if (number_format((float)$t['requested_amount'], 2, '.', '') !== $amount) {
            throw new RuntimeException('Bank SMS amount mismatch');
        }
        $utr = strtoupper(trim((string)($event['utr'] ?? '')));
        if ($utr !== '') {
            $used = $db->prepare('SELECT reference FROM transactions
                                  WHERE (rrn=? OR upi_txn_id=?) AND id<>? LIMIT 1');
            $used->execute([$utr, $utr, (int)$t['id']]);
            if ($used->fetchColumn()) throw new RuntimeException('Bank reference is already linked to another payment');
        }

        [$ok, $msg] = ReconciliationService::apply($db, $bank, [
            'reference' => (string)$t['reference'],
            'status' => 'SUCCESS',
            'amount' => $amount,
            'rrn' => $utr,
            'upi_txn_id' => $utr,
            'raw' => [
                'source' => 'BANK_SMS_AMOUNT_MATCH',
                'event_id' => $eventId,
                'account_last4' => (string)($event['account_last4'] ?? ''),
            ],
        ], 'BANK_SMS_AUTO');
        if (!$ok) throw new RuntimeException($msg);

        $db->prepare("UPDATE transactions
                      SET sms_match='SUCCESS', updated_at=UTC_TIMESTAMP()
                      WHERE id=?")->execute([(int)$t['id']]);
        $matchedCode = strtoupper(trim((string)($t['customer_code'] ?? '')));
        if ($matchedCode === '' && !empty($t['customer_id'])) {
            $st = $db->prepare('SELECT code FROM customers WHERE id=? LIMIT 1');
            $st->execute([(int)$t['customer_id']]);
            $matchedCode = strtoupper(trim((string)$st->fetchColumn()));
        }
        $db->prepare("UPDATE portal_bank_sms_events
                      SET match_status='SUCCESS', transaction_id=?, candidate_refs=?, candidate_customers=?
                      WHERE id=?")->execute([
            (int)$t['id'],
            (string)$t['reference'],
            $matchedCode !== '' ? $matchedCode : null,
            $eventId,
        ]);
    }

    public static function attachManually(PDO $db, int $eventId, string $reference): string {
        throw new RuntimeException('Manual payment confirmation is disabled. Verified Android bank SMS matching is required.');
    }

    /** @return array{amount:string,utr:string,account_last4:string,sms_time:string} */
    public static function parseCreditSms(string $body): array {
        $body = trim($body);
        if ($body === '') throw new InvalidArgumentException('Paste the bank credit SMS');
        if (!preg_match('/credit(?:ed)?/i', $body)) throw new InvalidArgumentException('SMS is not a credit message');
        $last4 = self::accountLast4FromSms($body) ?? '';
        if (!preg_match('/credited with\s*(?:INR|Rs\.?)\s*([0-9,]+(?:\.\d{1,2})?)/i', $body, $amt)
            && !preg_match('/(?:INR|Rs\.?)\s*([0-9,]+(?:\.\d{1,2})?)/i', $body, $amt)) {
            throw new InvalidArgumentException('Could not read the credited amount');
        }
        $utr = '';
        if (preg_match('/(?:UPI|UTR|RRN)[:\/\s-]*([A-Z0-9]{8,35})/i', $body, $ref)
            || preg_match('/reference\s+number\s+([A-Z0-9]{8,35})/i', $body, $ref)) {
            $utr = strtoupper((string)$ref[1]);
        }
        if (preg_match('/(\d{2}-\d{2}-\d{4}\s+\d{2}:\d{2}:\d{2})/', $body, $when)) {
            $smsTime = $when[1];
        } elseif (preg_match('/(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})/', $body, $when)) {
            $smsTime = $when[1];
        } elseif (preg_match('/(\d{2}-\d{2}-\d{4})/', $body, $when)) {
            $tz = new DateTimeZone(Env::get('APP_TIMEZONE', 'Asia/Kolkata') ?: 'Asia/Kolkata');
            $smsTime = (new DateTimeImmutable('now', $tz))->format('d-m-Y H:i:s');
        } else {
            $tz = new DateTimeZone(Env::get('APP_TIMEZONE', 'Asia/Kolkata') ?: 'Asia/Kolkata');
            $smsTime = (new DateTimeImmutable('now', $tz))->format('Y-m-d H:i:s');
        }
        return [
            'amount' => self::normalizeAmount(str_replace(',', '', $amt[1])),
            'utr' => $utr,
            'account_last4' => $last4,
            'sms_time' => $smsTime,
        ];
    }

    private static function accountLast4FromSms(string $body): ?string {
        $patterns = [
            '/account\s+X+(\d{4,16})/i',
            '/(?:a\/c|ac)\s*(?:X+|\*+)?\s*(\d{4,16})/i',
            '/X{4,}(\d{4,16})/',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $body, $acct)) {
                return substr($acct[1], -4);
            }
        }
        return null;
    }

    private static function normalizeAmount(mixed $raw): string {
        $s = str_replace(',', '', trim((string)$raw));
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $s)) throw new InvalidArgumentException('Amount format is not valid');
        $n = round((float)$s, 2);
        if ($n < 0.01 || $n > 10000000) throw new InvalidArgumentException('Amount is out of range');
        return number_format($n, 2, '.', '');
    }

    private static function parseSmsTime(string $raw): DateTimeImmutable {
        $raw = trim($raw);
        $tz = new DateTimeZone(Env::get('APP_TIMEZONE', 'Asia/Kolkata') ?: 'Asia/Kolkata');
        $utc = new DateTimeZone('UTC');
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,3})?Z$/', $raw)) {
            $dt = new DateTimeImmutable($raw);
            return $dt->setTimezone($utc);
        }
        $formats = ['Y-m-d H:i:s', 'd-m-Y H:i:s', 'd/m/Y H:i:s', 'Y-m-d\TH:i:s'];
        foreach ($formats as $fmt) {
            $dt = DateTimeImmutable::createFromFormat($fmt, $raw, $tz);
            if ($dt instanceof DateTimeImmutable) return $dt->setTimezone($utc);
        }
        try {
            return (new DateTimeImmutable($raw, $tz))->setTimezone($utc);
        } catch (Throwable $e) {
            throw new InvalidArgumentException('SMS time is not valid');
        }
    }

    public static function bankKey(string $value): string {
        $s = strtoupper(preg_replace('/[^A-Z]/', '', $value) ?? '');
        if (str_contains($s, 'INDUS')) return 'INDUSIND';
        if (str_contains($s, 'ICICI')) return 'ICICI';
        if ($s === 'RBL' || str_contains($s, 'RBLBANK')) return 'RBL';
        return $s;
    }

    /** ICICI / IndusInd / RBL family for relay matching (JKMERCHANT etc. → ICICI). */
    public static function relayBankFamily(PDO $db, string $bankCode): string {
        return self::accountBankKey($db, $bankCode);
    }

    /** @return list<array<string,mixed>> */
    private static function matchingBanks(PDO $db, string $bankCode): array {
        $want = self::bankKey($bankCode);
        if ($want === '') return [];
        $rows = $db->query('SELECT * FROM bank_profiles WHERE enabled=1')->fetchAll();
        $wantCode = strtoupper(trim($bankCode));

        // Prefer the exact registered receiving profile. This matters when the same bank
        // has more than one receiving account/profile.
        foreach ($rows as $row) {
            if (strtoupper(trim((string)$row['code'])) === $wantCode) return [$row];
        }

        // The phone reports the real bank, for example ICICI, while the saved
        // profile code is JKMERCHANT or KKKENT. Match every enabled profile whose
        // code, bank name, or UPI ID is that bank. Same-VPA profiles stay eligible.
        $out = [];
        foreach ($rows as $row) {
            if (self::accountBankKey($db, (string)$row['code']) === $want) $out[] = $row;
        }
        return $out;
    }

    /** Bank profiles that collect into the same UPI ID as this SMS account. */
    private static function sameAccountBankIds(PDO $db, string $bankCode): array {
        $banks = self::matchingBanks($db, $bankCode);
        $ids = [];
        $vpas = [];
        foreach ($banks as $bank) {
            $ids[] = (int)$bank['id'];
            $vpa = strtolower(trim((string)($bank['vpa'] ?? '')));
            if ($vpa !== '') $vpas[$vpa] = true;
        }
        if ($vpas) {
            foreach ($db->query('SELECT id, vpa FROM bank_profiles WHERE enabled=1') as $row) {
                $vpa = strtolower(trim((string)($row['vpa'] ?? '')));
                if ($vpa !== '' && isset($vpas[$vpa])) $ids[] = (int)$row['id'];
            }
        }
        return array_values(array_unique($ids));
    }

    /**
     * Collect/UPI profile IDs reachable from the same Android relay phone as this bank code
     * (JKMERCHANT QR + KKKENT rotation on one ICICI account, etc.).
     *
     * @return list<int>
     */
    private static function relayCollectBankIds(PDO $db, string $bankCode): array {
        $ids = self::sameAccountBankIds($db, $bankCode);
        $codes = [strtoupper(trim($bankCode))];
        foreach ($db->query('SELECT id, bank_code FROM bank_sms_devices WHERE active=1') as $dev) {
            $devCodes = [strtoupper(trim((string)$dev['bank_code']))];
            $st = $db->prepare('SELECT bank_code FROM bank_sms_device_accounts WHERE device_row_id=?');
            $st->execute([(int)$dev['id']]);
            foreach ($st->fetchAll() as $row) {
                $devCodes[] = strtoupper(trim((string)$row['bank_code']));
            }
            $devCodes = array_values(array_unique(array_filter($devCodes)));
            $onDevice = false;
            foreach ($devCodes as $dc) {
                if ($dc === strtoupper(trim($bankCode)) || self::relayAccountMatchesBank($db, $dc, $bankCode)) {
                    $onDevice = true;
                    break;
                }
            }
            if ($onDevice) {
                $codes = array_merge($codes, $devCodes);
            }
        }
        $codes = array_values(array_unique(array_filter($codes)));
        foreach ($codes as $code) {
            foreach (self::sameAccountBankIds($db, $code) as $id) {
                $ids[] = $id;
            }
        }
        // Phone reports ICICI; QR payments use merchant profile codes (JKMERCHANT, KKKENT).
        // Every enabled profile on an active relay phone must be eligible for matching.
        $canonical = self::bankKey($bankCode);
        if (in_array($canonical, ['ICICI', 'INDUSIND', 'RBL'], true)) {
            foreach ($db->query('SELECT id, bank_code FROM bank_sms_devices WHERE active=1') as $dev) {
                $codes[] = strtoupper(trim((string)$dev['bank_code']));
                $st = $db->prepare('SELECT bank_code FROM bank_sms_device_accounts WHERE device_row_id=?');
                $st->execute([(int)$dev['id']]);
                foreach ($st->fetchAll() as $row) {
                    $codes[] = strtoupper(trim((string)$row['bank_code']));
                }
            }
            $codes = array_values(array_unique(array_filter($codes)));
            foreach ($codes as $code) {
                foreach (self::sameAccountBankIds($db, $code) as $id) {
                    $ids[] = $id;
                }
            }
        }
        $vpas = [];
        foreach ($ids as $id) {
            $row = self::bankById($db, (int)$id);
            if (!$row) continue;
            $vpa = strtolower(trim((string)($row['vpa'] ?? '')));
            if ($vpa !== '') $vpas[$vpa] = true;
        }
        if ($vpas) {
            foreach ($db->query('SELECT id, vpa FROM bank_profiles WHERE enabled=1') as $row) {
                $vpa = strtolower(trim((string)($row['vpa'] ?? '')));
                if ($vpa !== '' && isset($vpas[$vpa])) $ids[] = (int)$row['id'];
            }
        }
        return array_values(array_unique($ids));
    }

    /**
     * @param list<int> $bankIds
     */
    private static function pendingAckCountForAmount(PDO $db, string $amount, array $bankIds): int {
        if (!$bankIds) return 0;
        $marks = implode(',', array_fill(0, count($bankIds), '?'));
        $st = $db->prepare("SELECT COUNT(*) FROM transactions
                            WHERE status='PENDING'
                              AND customer_paid_ack_at IS NOT NULL
                              AND ROUND(requested_amount, 2) = ROUND(?, 2)
                              AND bank_id IN ($marks)");
        $st->execute(array_merge([$amount], $bankIds));
        return (int)$st->fetchColumn();
    }

    /**
     * @param list<int> $bankIds
     * @return array<string,mixed>|null
     */
    private static function solePendingAckForAmount(PDO $db, string $amount, array $bankIds): ?array {
        if (self::pendingAckCountForAmount($db, $amount, $bankIds) !== 1) return null;
        $marks = implode(',', array_fill(0, count($bankIds), '?'));
        $st = $db->prepare("SELECT * FROM transactions
                            WHERE status='PENDING'
                              AND customer_paid_ack_at IS NOT NULL
                              AND ROUND(requested_amount, 2) = ROUND(?, 2)
                              AND bank_id IN ($marks)
                            LIMIT 2");
        $st->execute(array_merge([$amount], $bankIds));
        $rows = $st->fetchAll();
        return count($rows) === 1 ? $rows[0] : null;
    }

    private static function paymentCreatedLookbackSeconds(): int {
        $fallback = max(self::MATCH_WINDOW_SECONDS * 4, 86400);
        return max(3600, min(172800, Env::int('BANK_SMS_PAYMENT_LOOKBACK_SECONDS', $fallback)));
    }

    /** @param array<string,mixed> $txn */
    private static function paymentMatchesSmsQrTime(array $txn, DateTimeImmutable $smsUtc): bool {
        try {
            $createdUtc = new DateTimeImmutable((string)$txn['created_at'], new DateTimeZone('UTC'));
        } catch (Throwable $e) {
            return false;
        }
        $delay = $smsUtc->getTimestamp() - $createdUtc->getTimestamp();
        return $delay >= -self::CLOCK_SKEW_SECONDS && $delay <= self::qrToPaySeconds();
    }

    /** @param array<string,mixed> $txn */
    private static function transactionEligibleForSmsCredit(array $txn, DateTimeImmutable $smsUtc): bool {
        if (!self::paymentMatchesSmsQrTime($txn, $smsUtc)) return false;
        $ackRaw = trim((string)($txn['customer_paid_ack_at'] ?? ''));
        if ($ackRaw === '') return true;
        try {
            $ackUtc = new DateTimeImmutable($ackRaw, new DateTimeZone('UTC'));
        } catch (Throwable $e) {
            return true;
        }
        $ackFrom = $smsUtc->modify('-'.self::MATCH_WINDOW_SECONDS.' seconds');
        $ackTo = $smsUtc->modify('+'.self::ACK_AFTER_SMS_SECONDS.' seconds');
        return $ackUtc >= $ackFrom && $ackUtc <= $ackTo;
    }

    /**
     * @param list<int> $ids
     * @return list<array<string,mixed>>
     */
    private static function resolvePaymentsForSms(PDO $db, string $amount, array $ids, DateTimeImmutable $smsUtc): array {
        $pool = self::pendingPoolForSms($db, $amount, $ids, $smsUtc);
        if ($pool === []) return [];

        $best = self::pickBestPaymentForSms($pool, $smsUtc);
        if ($best !== null) return [$best];

        $onlyCustomer = self::soleAckCustomerIdForSms($db, $amount, $ids, $smsUtc, true);
        $ackHits = self::chooseUniqueHit(
            self::candidatePayments($db, $amount, $ids, $smsUtc, true, $onlyCustomer),
            $smsUtc->format('Y-m-d H:i:s')
        );
        if (count($ackHits) === 1) return $ackHits;
        if (count($ackHits) > 1) {
            $one = self::pickClosestAckPayment($ackHits, $smsUtc);
            return $one ? [$one] : $ackHits;
        }
        return [];
    }

    /**
     * @param list<int> $bankIds
     * @return list<array<string,mixed>>
     */
    private static function pendingPoolForSms(PDO $db, string $amount, array $bankIds, DateTimeImmutable $smsUtc): array {
        if (!$bankIds) return [];
        $marks = implode(',', array_fill(0, count($bankIds), '?'));
        $createdFrom = $smsUtc->modify('-'.self::paymentCreatedLookbackSeconds().' seconds')->format('Y-m-d H:i:s');
        $createdTo = $smsUtc->modify('+'.self::CLOCK_SKEW_SECONDS.' seconds')->format('Y-m-d H:i:s');
        $st = $db->prepare("SELECT id, reference, bank_id, customer_id, customer_paid_ack_at, created_at
                            FROM transactions
                            WHERE status='PENDING'
                              AND ROUND(requested_amount, 2) = ROUND(?, 2)
                              AND bank_id IN ($marks)
                              AND created_at>=? AND created_at<=?
                            ORDER BY created_at DESC, id DESC");
        $st->execute(array_merge([$amount], $bankIds, [$createdFrom, $createdTo]));
        $rows = [];
        foreach ($st->fetchAll() as $row) {
            if (self::paymentMatchesSmsQrTime($row, $smsUtc)) $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Prefer the pending QR generated closest before the bank credit (actual pay session).
     *
     * @param list<array<string,mixed>> $pool
     * @return array<string,mixed>|null
     */
    private static function pickBestPaymentForSms(array $pool, DateTimeImmutable $smsUtc): ?array {
        if ($pool === []) return null;
        $smsTs = $smsUtc->getTimestamp();
        $sep = self::qrMatchSeparationSeconds();
        $scored = [];
        foreach ($pool as $row) {
            $createdTs = strtotime(((string)($row['created_at'] ?? '')).' UTC');
            if ($createdTs === false) continue;
            $qrDelay = $smsTs - $createdTs;
            if ($qrDelay < -self::CLOCK_SKEW_SECONDS || $qrDelay > self::qrToPaySeconds()) continue;
            $scored[] = ['row' => $row, 'qr_delay' => $qrDelay];
        }
        if ($scored === []) return null;
        usort($scored, static fn(array $a, array $b): int => $a['qr_delay'] <=> $b['qr_delay']);

        $best = $scored[0];
        $second = $scored[1] ?? null;
        if ($second === null) return $best['row'];

        $gap = $second['qr_delay'] - $best['qr_delay'];
        if ($gap >= $sep) return $best['row'];

        $bestCid = (int)($best['row']['customer_id'] ?? 0);
        $secondCid = (int)($second['row']['customer_id'] ?? 0);
        $crossCustomerMin = max(30, min(120, Env::int('BANK_SMS_CROSS_CUSTOMER_QR_GAP_SECONDS', 45)));
        if ($bestCid > 0 && $secondCid > 0 && $bestCid !== $secondCid && $gap >= $crossCustomerMin) {
            return $best['row'];
        }

        $tier = array_values(array_filter($scored, static fn(array $item): bool => ($item['qr_delay'] - $best['qr_delay']) <= $sep));
        $ackInWindow = [];
        foreach ($tier as $item) {
            $ackRaw = trim((string)($item['row']['customer_paid_ack_at'] ?? ''));
            if ($ackRaw === '') continue;
            try {
                $ackUtc = new DateTimeImmutable($ackRaw, new DateTimeZone('UTC'));
            } catch (Throwable $e) {
                continue;
            }
            $ackFrom = $smsUtc->modify('-'.self::MATCH_WINDOW_SECONDS.' seconds');
            $ackTo = $smsUtc->modify('+'.self::ACK_AFTER_SMS_SECONDS.' seconds');
            if ($ackUtc >= $ackFrom && $ackUtc <= $ackTo) $ackInWindow[] = $item['row'];
        }
        if (count($ackInWindow) === 1) return $ackInWindow[0];
        if (count($tier) === 1) return $tier[0]['row'];

        return null;
    }

    /**
     * @param list<array<string,mixed>> $hits
     * @return array<string,mixed>|null
     */
    private static function pickClosestAckPayment(array $hits, DateTimeImmutable $smsUtc): ?array {
        if ($hits === []) return null;
        $smsTs = $smsUtc->getTimestamp();
        $best = null;
        $bestDelta = PHP_INT_MAX;
        $secondDelta = PHP_INT_MAX;
        foreach ($hits as $hit) {
            $ackTs = strtotime(((string)($hit['customer_paid_ack_at'] ?? '')).' UTC');
            if ($ackTs === false) continue;
            $delta = abs($smsTs - $ackTs);
            if ($delta < $bestDelta) {
                $secondDelta = $bestDelta;
                $bestDelta = $delta;
                $best = $hit;
            } elseif ($delta < $secondDelta) {
                $secondDelta = $delta;
            }
        }
        if ($best === null) return null;
        if ($secondDelta < PHP_INT_MAX && ($secondDelta - $bestDelta) < 120) return null;
        return $best;
    }

    /**
     * @param list<int> $bankIds
     * @return array<string,mixed>|null
     */
    private static function bestPendingAckForAmount(PDO $db, string $amount, array $bankIds, DateTimeImmutable $smsUtc): ?array {
        if (!$bankIds) return null;
        $marks = implode(',', array_fill(0, count($bankIds), '?'));
        $createdFrom = $smsUtc->modify('-'.self::paymentCreatedLookbackSeconds().' seconds')->format('Y-m-d H:i:s');
        $createdTo = $smsUtc->modify('+'.self::CLOCK_SKEW_SECONDS.' seconds')->format('Y-m-d H:i:s');
        $ackFrom = $smsUtc->modify('-'.self::MATCH_WINDOW_SECONDS.' seconds')->format('Y-m-d H:i:s');
        $ackTo = $smsUtc->modify('+'.self::ACK_AFTER_SMS_SECONDS.' seconds')->format('Y-m-d H:i:s');
        $st = $db->prepare("SELECT id, reference, bank_id, customer_id, customer_paid_ack_at, created_at
                            FROM transactions
                            WHERE status='PENDING'
                              AND customer_paid_ack_at IS NOT NULL
                              AND ROUND(requested_amount, 2) = ROUND(?, 2)
                              AND bank_id IN ($marks)
                              AND created_at>=? AND created_at<=?
                              AND customer_paid_ack_at>=? AND customer_paid_ack_at<=?
                            ORDER BY customer_paid_ack_at DESC, id DESC");
        $st->execute(array_merge([$amount], $bankIds, [$createdFrom, $createdTo, $ackFrom, $ackTo]));
        $rows = $st->fetchAll();
        if (!$rows) return null;
        if (count($rows) === 1) return $rows[0];
        return self::pickClosestAckPayment($rows, $smsUtc);
    }

    /**
     * Payments the customer already confirmed, for this exact collected amount.
     * The tap can come after the bank SMS. Both the QR and the tap stay valid
     * for 30 minutes around that credit.
     *
     * @param list<int> $ids
     * @return list<array<string,mixed>>
     */
    private static function candidatePayments(PDO $db, string $amount, array $ids, DateTimeImmutable $smsUtc, bool $wideAckWindow = false, ?int $onlyCustomerId = null): array {
        if (!$ids) return [];
        $ackFrom = $smsUtc->modify('-'.self::MATCH_WINDOW_SECONDS.' seconds')->format('Y-m-d H:i:s');
        $ackAfter = $wideAckWindow ? self::ACK_AFTER_SMS_SECONDS : self::MATCH_WINDOW_SECONDS;
        $ackTo = $smsUtc->modify('+'.$ackAfter.' seconds')->format('Y-m-d H:i:s');
        $createdFrom = $smsUtc->modify('-'.self::paymentCreatedLookbackSeconds().' seconds')->format('Y-m-d H:i:s');
        $createdTo = $smsUtc->modify('+'.self::CLOCK_SKEW_SECONDS.' seconds')->format('Y-m-d H:i:s');
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $customerSql = $onlyCustomerId !== null && $onlyCustomerId > 0 ? ' AND customer_id=?' : '';
        $sql = "SELECT id, reference, bank_id, customer_id, customer_paid_ack_at
                FROM transactions
                WHERE status='PENDING'
                  AND customer_paid_ack_at IS NOT NULL
                  AND ROUND(requested_amount, 2) = ROUND(?, 2)
                  AND bank_id IN ($marks)
                  AND created_at>=?
                  AND created_at<=?
                  AND customer_paid_ack_at>=?
                  AND customer_paid_ack_at<=?"
                .$customerSql.'
                ORDER BY customer_paid_ack_at ASC, id ASC';
        $args = array_merge([$amount], $ids, [$createdFrom, $createdTo, $ackFrom, $ackTo]);
        if ($onlyCustomerId !== null && $onlyCustomerId > 0) $args[] = $onlyCustomerId;
        $st = $db->prepare($sql);
        $st->execute($args);
        return $st->fetchAll();
    }

    /**
     * @param list<int> $bankIds
     */
    private static function soleAckCustomerIdForSms(PDO $db, string $amount, array $bankIds, DateTimeImmutable $smsUtc, bool $wideAckWindow): ?int {
        $rows = self::candidatePayments($db, $amount, $bankIds, $smsUtc, $wideAckWindow);
        $ids = [];
        foreach ($rows as $row) {
            $cid = (int)($row['customer_id'] ?? 0);
            if ($cid > 0) $ids[$cid] = true;
        }
        return count($ids) === 1 ? (int)array_key_first($ids) : null;
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function customerCodesFromTxnRows(PDO $db, array $rows): string {
        $codes = [];
        foreach ($rows as $row) {
            $cid = (int)($row['customer_id'] ?? 0);
            if ($cid <= 0) continue;
            if (!isset($codes[$cid])) {
                $st = $db->prepare('SELECT code FROM customers WHERE id=? LIMIT 1');
                $st->execute([$cid]);
                $code = strtoupper(trim((string)$st->fetchColumn()));
                if ($code !== '') $codes[$cid] = $code;
            }
        }
        return substr(implode(',', array_values($codes)), 0, 500);
    }

    /**
     * @param list<string> $add
     */
    private static function mergeCustomerCodes(string $existing, array $add): string {
        $set = [];
        foreach (explode(',', $existing) as $part) {
            $part = strtoupper(trim($part));
            if ($part !== '') $set[$part] = true;
        }
        foreach ($add as $part) {
            $part = strtoupper(trim($part));
            if ($part !== '') $set[$part] = true;
        }
        return substr(implode(',', array_keys($set)), 0, 500);
    }

    /**
     * Several leftover payments of the same customer, same amount, and same UPI
     * are one payer. Attach the credit to the payment they confirmed last.
     * When more than one customer remains, attach it only when one acknowledgement
     * is clearly closer to the bank SMS than the others.
     *
     * @param list<array<string,mixed>> $hits
     * @return list<array<string,mixed>>
     */
    private static function chooseUniqueHit(array $hits, ?string $smsTime = null): array {
        if (count($hits) <= 1) return $hits;
        $latest = [];
        foreach ($hits as $hit) {
            $cid = (int)($hit['customer_id'] ?? 0);
            $prev = $latest[$cid] ?? null;
            if ($prev === null) {
                $latest[$cid] = $hit;
                continue;
            }
            $cmp = strcmp((string)($hit['customer_paid_ack_at'] ?? ''), (string)($prev['customer_paid_ack_at'] ?? ''));
            if ($cmp > 0 || ($cmp === 0 && (int)$hit['id'] > (int)$prev['id'])) {
                $latest[$cid] = $hit;
            }
        }
        $candidates = array_values($latest);
        if (count($candidates) === 1 || $smsTime === null || $smsTime === '') return $candidates;
        $smsTs = strtotime($smsTime . ' UTC');
        if ($smsTs === false) return $candidates;
        $delta = static function (array $hit) use ($smsTs): int {
            $ackTs = strtotime(((string)($hit['customer_paid_ack_at'] ?? '')).' UTC');
            return $ackTs === false ? PHP_INT_MAX : abs($smsTs - $ackTs);
        };
        usort($candidates, static function (array $a, array $b) use ($delta): int {
            return $delta($a) <=> $delta($b);
        });
        if (count($candidates) >= 2 && $delta($candidates[1]) >= $delta($candidates[0]) + 90) {
            return [$candidates[0]];
        }
        return $candidates;
    }

    private static function clearSameCustomerReview(PDO $db, int $customerId, string $amount, int $keepId): void {
        if ($customerId <= 0 || $keepId <= 0) return;
        $db->prepare("UPDATE transactions
                      SET sms_match=NULL, updated_at=UTC_TIMESTAMP()
                      WHERE customer_id=?
                        AND status='PENDING'
                        AND sms_match='REVIEW_REQUIRED'
                        AND requested_amount=?
                        AND id<>?")->execute([$customerId, $amount, $keepId]);
    }

    private static function bankByCode(PDO $db, string $code): ?array {
        $st = $db->prepare('SELECT * FROM bank_profiles WHERE code=? AND enabled=1 LIMIT 1');
        $st->execute([strtoupper($code)]);
        $r = $st->fetch();
        return $r ?: null;
    }

    private static function bankById(PDO $db, int $id): ?array {
        $st = $db->prepare('SELECT * FROM bank_profiles WHERE id=? LIMIT 1');
        $st->execute([$id]);
        $r = $st->fetch();
        return $r ?: null;
    }

    /** @return list<array{bank_code:string,account_last4:string}> */
    private static function deviceAccounts(PDO $db, array $device): array {
        $accounts = [[
            'bank_code' => (string)$device['bank_code'],
            'account_last4' => (string)$device['account_last4'],
        ]];
        $st = $db->prepare('SELECT bank_code, account_last4 FROM bank_sms_device_accounts WHERE device_row_id=?');
        $st->execute([(int)$device['id']]);
        foreach ($st->fetchAll() as $row) {
            $accounts[] = [
                'bank_code' => (string)$row['bank_code'],
                'account_last4' => (string)$row['account_last4'],
            ];
        }
        return $accounts;
    }

    /** Profile codes such as JKMERCHANT are still ICICI when the saved bank name or UPI ID is ICICI. */
    private static function accountBankKey(PDO $db, string $bankCode): string {
        $direct = self::bankKey($bankCode);
        if (in_array($direct, ['ICICI', 'INDUSIND', 'RBL'], true)) return $direct;
        $st = $db->prepare('SELECT code, bank_name, vpa FROM bank_profiles WHERE code=? LIMIT 1');
        $st->execute([strtoupper(trim($bankCode))]);
        $row = $st->fetch();
        if (!$row) return $direct;
        return self::bankKey((string)$row['code'].' '.(string)$row['bank_name'].' '.(string)($row['vpa'] ?? ''));
    }

    /** Device profile (e.g. JKMERCHANT) vs canonical bank from SMS (e.g. ICICI). */
    private static function relayAccountMatchesBank(PDO $db, string $accountBankCode, string $incomingBankCode): bool {
        $want = self::bankKey($incomingBankCode);
        if ($want === '') return false;
        $accCode = strtoupper(trim($accountBankCode));
        if (self::accountBankKey($db, $accCode) === $want) return true;
        if (strtoupper(trim($incomingBankCode)) === $accCode) return true;
        foreach (self::matchingBanks($db, $incomingBankCode) as $profile) {
            if (strtoupper(trim((string)$profile['code'])) === $accCode) return true;
        }
        return false;
    }

    /**
     * @param list<array{bank_code:string,account_last4:string}> $hits
     * @return array{bank_code:string,account_last4:string}|null
     */
    private static function pickUniqueRelayAccount(array $hits, array $device): ?array {
        if (count($hits) === 1) return $hits[0];
        if ($hits === []) return null;
        $primaryCode = strtoupper(trim((string)$device['bank_code']));
        $primaryLast4 = (string)$device['account_last4'];
        foreach ($hits as $account) {
            if (strtoupper((string)$account['bank_code']) === $primaryCode
                && hash_equals((string)$account['account_last4'], $primaryLast4)) {
                return $account;
            }
        }
        $last4 = (string)$hits[0]['account_last4'];
        foreach ($hits as $account) {
            if (!hash_equals((string)$account['account_last4'], $last4)) return null;
        }
        return $hits[0];
    }

    /** @return array{bank_code:string,account_last4:string}|null */
    private static function matchingAccount(PDO $db, array $device, string $bankCode, string $last4): ?array {
        $want = self::bankKey($bankCode);
        if ($want === '') return null;
        $allAccounts = self::deviceAccounts($db, $device);
        $candidates = [];
        foreach ($allAccounts as $account) {
            if (self::relayAccountMatchesBank($db, (string)$account['bank_code'], $bankCode)) {
                $candidates[] = $account;
            }
        }
        if (preg_match('/^\d{4}$/', $last4)) {
            $hits = [];
            foreach ($candidates as $account) {
                if (hash_equals((string)$account['account_last4'], $last4)) $hits[] = $account;
            }
            $picked = self::pickUniqueRelayAccount($hits, $device);
            if ($picked) return $picked;
            // Phone reports ICICI/IndusInd/RBL; device may be registered under a merchant profile code.
            if (in_array($want, ['ICICI', 'INDUSIND', 'RBL'], true)) {
                $byLast4 = [];
                foreach ($allAccounts as $account) {
                    if (hash_equals((string)$account['account_last4'], $last4)) $byLast4[] = $account;
                }
                return self::pickUniqueRelayAccount($byLast4, $device);
            }
            return null;
        }
        // If the bank SMS omits account digits, infer only when this relay phone has
        // exactly one registered receiving account for that bank.
        return count($candidates) === 1 ? $candidates[0] : null;
    }

    private static function activeDevice(PDO $db, string $deviceId): ?array {
        $st = $db->prepare('SELECT * FROM bank_sms_devices WHERE device_id=? AND active=1 LIMIT 1');
        $st->execute([$deviceId]);
        $r = $st->fetch();
        return $r ?: null;
    }

    private static function eventByAndroidEvent(PDO $db, string $deviceId, string $eventId, bool $lock = false): ?array {
        if ($deviceId === '' || $eventId === '') return null;
        $sql = 'SELECT * FROM portal_bank_sms_events WHERE device_id=? AND android_event_id=? LIMIT 1'.($lock ? ' FOR UPDATE' : '');
        $st = $db->prepare($sql);
        $st->execute([$deviceId, $eventId]);
        $r = $st->fetch();
        if (!$r) return null;
        if (!empty($r['transaction_id'])) {
            $ref = $db->prepare('SELECT reference FROM transactions WHERE id=?');
            $ref->execute([(int)$r['transaction_id']]);
            $r['reference'] = (string)($ref->fetchColumn() ?: '');
        }
        return $r;
    }

    private static function eventByUtr(PDO $db, string $utr, bool $lock = false): ?array {
        if ($utr === '') return null;
        $sql = 'SELECT * FROM portal_bank_sms_events WHERE utr=? LIMIT 1'.($lock ? ' FOR UPDATE' : '');
        $st = $db->prepare($sql);
        $st->execute([$utr]);
        $r = $st->fetch();
        if (!$r) return null;
        if (!empty($r['transaction_id'])) {
            $ref = $db->prepare('SELECT reference FROM transactions WHERE id=?');
            $ref->execute([(int)$r['transaction_id']]);
            $r['reference'] = (string)($ref->fetchColumn() ?: '');
        }
        return $r;
    }

    private static function eventById(PDO $db, int $id): ?array {
        $st = $db->prepare('SELECT e.*, t.reference FROM portal_bank_sms_events e LEFT JOIN transactions t ON t.id=e.transaction_id WHERE e.id=?');
        $st->execute([$id]);
        $r = $st->fetch();
        return $r ?: null;
    }

    /**
     * A signed credit from the registered phone is accepted even when matching hits a
     * database lock. The row stays UNMATCHED and the phone keeps a healthy upload.
     *
     * @param array<string,mixed> $data
     * @param array<string,mixed> $device
     * @return array<string,mixed>|null
     */
    private static function keepAcceptedSms(PDO $db, array $data, array $device): ?array {
        if ($db->inTransaction()) {
            try { $db->rollBack(); } catch (Throwable $e) {}
        }
        $utr = '';
        try {
            self::ensureSchema($db);
            $utr = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string)($data['utr'] ?? '')) ?? '');
            if (!preg_match('/^[A-Z0-9]{8,35}$/', $utr)) return null;
            $existing = self::eventByUtr($db, $utr);
            if ($existing) return self::eventResult($existing, true);
            $amount = self::normalizeAmount($data['amount'] ?? '');
            $last4 = trim((string)($data['account_last4'] ?? ''));
            if (!preg_match('/^\d{4}$/', $last4)) return null;
            $bankCode = strtoupper(trim((string)($data['bank'] ?? ($device['bank_code'] ?? ''))));
            if ($bankCode === '') return null;
            $sender = substr(trim((string)($data['sender'] ?? '')), 0, 80);
            $smsUtc = self::parseSmsTime((string)($data['sms_time'] ?? ''));
            $eventKey = strtolower(trim((string)($data['event_id'] ?? '')));
            if ($eventKey !== '' && !preg_match('/^[a-f0-9]{64}$/', $eventKey)) $eventKey = '';
            $db->prepare('INSERT INTO portal_bank_sms_events(
                device_id,android_event_id,bank_code,amount,utr,account_last4,sender_id,
                sms_time,match_status,created_at
            ) VALUES(?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP())')->execute([
                (string)$device['device_id'],
                $eventKey !== '' ? $eventKey : null,
                $bankCode,
                $amount,
                $utr,
                $last4,
                $sender !== '' ? $sender : null,
                $smsUtc->format('Y-m-d H:i:s'),
                'UNMATCHED',
            ]);
            $saved = self::eventById($db, (int)$db->lastInsertId());
            return self::eventResult($saved ?: ['match_status' => 'UNMATCHED', 'utr' => $utr], false);
        } catch (Throwable $e) {
            if ($utr !== '') {
                try {
                    $dup = self::eventByUtr($db, $utr);
                    if ($dup) return self::eventResult($dup, true);
                } catch (Throwable $ignored) {}
            }
            return null;
        }
    }

    /** @param array<string,mixed> $event @return array<string,mixed> */
    private static function eventResult(array $event, bool $duplicate): array {
        $refs = array_values(array_filter(array_map('trim', explode(',', (string)($event['candidate_refs'] ?? '')))));
        $paymentId = trim((string)($event['reference'] ?? ''));
        if ($paymentId === '' && count($refs) === 1) $paymentId = $refs[0];
        return [
            'ok' => true,
            'match' => (string)($event['match_status'] ?? ''),
            'payment_id' => $paymentId !== '' ? $paymentId : null,
            'utr' => (string)($event['utr'] ?? ''),
            'candidates' => $refs,
            'duplicate' => $duplicate,
        ];
    }
}
