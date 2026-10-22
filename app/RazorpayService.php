<?php
declare(strict_types=1);

/**
 * Razorpay integration — Orders API + Checkout.js + HMAC-verified webhook.
 *
 * Design notes (read before changing):
 *  - This is an ISOLATED ledger (razorpay_orders / razorpay_events). It never
 *    writes to `transactions`, bank-SMS tables, or settlement logic.
 *  - A payment is marked PAID only by trusted server-side evidence:
 *      (a) a webhook whose HMAC-SHA256 signature verifies against the webhook
 *          secret, OR
 *      (b) an authenticated Orders/Payments API fetch to Razorpay.
 *    A browser redirect / JS callback never, on its own, flips status to PAID.
 *  - applyCapture() is idempotent and validates order association, amount, and
 *    currency before confirming, so duplicate webhooks cannot double-credit.
 *  - Secrets (key_secret, webhook_secret) are encrypted at rest (Crypto) and
 *    are never exposed to the frontend or to customers.
 */
final class RazorpayService {
    private const API_BASE = 'https://api.razorpay.com/v1';

    private static bool $schemaReady = false;

    public static function ensureSchema(PDO $db): void {
        if (self::$schemaReady) return;
        $db->exec("CREATE TABLE IF NOT EXISTS razorpay_settings (
            id TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
            enabled TINYINT(1) NOT NULL DEFAULT 0,
            live_mode TINYINT(1) NOT NULL DEFAULT 0,
            key_id VARCHAR(64) NOT NULL DEFAULT '',
            key_secret_encrypted TEXT NULL,
            webhook_secret_encrypted TEXT NULL,
            currency CHAR(3) NOT NULL DEFAULT 'INR',
            button_label VARCHAR(80) NOT NULL DEFAULT 'YEEDIPAY Payment',
            min_amount DECIMAL(14,2) NOT NULL DEFAULT 1.00,
            max_amount DECIMAL(14,2) NOT NULL DEFAULT 100000.00,
            webhook_configured TINYINT(1) NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("INSERT IGNORE INTO razorpay_settings (id) VALUES (1)");
        $db->exec("CREATE TABLE IF NOT EXISTS razorpay_orders (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            local_reference VARCHAR(64) NOT NULL,
            customer_id BIGINT UNSIGNED NULL,
            customer_code VARCHAR(32) NULL,
            rzp_order_id VARCHAR(64) NOT NULL,
            rzp_payment_id VARCHAR(64) NULL,
            amount_paise BIGINT UNSIGNED NOT NULL,
            captured_amount_paise BIGINT UNSIGNED NULL,
            currency CHAR(3) NOT NULL DEFAULT 'INR',
            status ENUM('CREATED','PENDING','PAID','FAILED','CANCELLED','REVIEW') NOT NULL DEFAULT 'CREATED',
            verified TINYINT(1) NOT NULL DEFAULT 0,
            verification_source VARCHAR(32) NULL,
            verification_note VARCHAR(500) NULL,
            method VARCHAR(40) NULL,
            email VARCHAR(190) NULL,
            contact VARCHAR(40) NULL,
            notes_json TEXT NULL,
            live_mode TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            paid_at DATETIME NULL,
            UNIQUE KEY uq_rzp_local_reference (local_reference),
            UNIQUE KEY uq_rzp_order_id (rzp_order_id),
            UNIQUE KEY uq_rzp_payment_id (rzp_payment_id),
            INDEX idx_rzp_status (status),
            INDEX idx_rzp_customer (customer_id, status),
            INDEX idx_rzp_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE IF NOT EXISTS razorpay_events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id VARCHAR(80) NOT NULL,
            event_type VARCHAR(64) NOT NULL,
            rzp_order_id VARCHAR(64) NULL,
            rzp_payment_id VARCHAR(64) NULL,
            signature_valid TINYINT(1) NOT NULL DEFAULT 0,
            amount_paise BIGINT UNSIGNED NULL,
            currency CHAR(3) NULL,
            processed TINYINT(1) NOT NULL DEFAULT 0,
            process_note VARCHAR(500) NULL,
            payload_json LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_rzp_event_id (event_id),
            INDEX idx_rzp_event_order (rzp_order_id),
            INDEX idx_rzp_event_payment (rzp_payment_id),
            INDEX idx_rzp_event_type (event_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        // Payer name for the public (no-login) pay page.
        $cols = [];
        foreach ($db->query('SHOW COLUMNS FROM razorpay_orders') as $r) { $cols[strtolower((string)$r['Field'])] = true; }
        if (!isset($cols['buyer_name'])) {
            $db->exec('ALTER TABLE razorpay_orders ADD COLUMN buyer_name VARCHAR(180) NULL AFTER customer_code');
        }
        self::$schemaReady = true;
    }

    /* ---------------------------------------------------------------- settings */

    /** @return array<string,mixed> */
    public static function settings(PDO $db): array {
        self::ensureSchema($db);
        $r = $db->query('SELECT * FROM razorpay_settings WHERE id=1')->fetch();
        if (!$r) {
            $db->exec("INSERT IGNORE INTO razorpay_settings (id) VALUES (1)");
            $r = $db->query('SELECT * FROM razorpay_settings WHERE id=1')->fetch();
        }
        return $r ?: [];
    }

    public static function keySecret(PDO $db): string {
        $s = self::settings($db);
        return Crypto::decrypt((string)($s['key_secret_encrypted'] ?? ''));
    }

    public static function webhookSecret(PDO $db): string {
        $s = self::settings($db);
        return Crypto::decrypt((string)($s['webhook_secret_encrypted'] ?? ''));
    }

    /**
     * Frontend-safe public config. NEVER returns a secret.
     * @return array<string,mixed>
     */
    public static function publicConfig(PDO $db): array {
        $s = self::settings($db);
        return [
            'enabled'   => (bool)((int)($s['enabled'] ?? 0)) && self::isConfigured($db),
            'key_id'    => (string)($s['key_id'] ?? ''),
            'currency'  => (string)($s['currency'] ?? 'INR'),
            'label'     => (string)($s['button_label'] ?? 'YEEDIPAY Payment'),
            'live_mode' => (bool)((int)($s['live_mode'] ?? 0)),
            'min'       => (float)($s['min_amount'] ?? 1),
            'max'       => (float)($s['max_amount'] ?? 100000),
        ];
    }

    public static function isConfigured(PDO $db): bool {
        $s = self::settings($db);
        return trim((string)($s['key_id'] ?? '')) !== '' && self::keySecret($db) !== '';
    }

    public static function isLiveReady(PDO $db): bool {
        $s = self::settings($db);
        return self::isConfigured($db)
            && self::webhookSecret($db) !== ''
            && (int)($s['webhook_configured'] ?? 0) === 1;
    }

    /**
     * Save admin settings. Validates key_id format and mode consistency.
     * Secrets passed empty are left unchanged (so the form never needs to echo them).
     * @param array<string,mixed> $in
     * @return array{0:bool,1:string}
     */
    public static function saveSettings(PDO $db, array $in): array {
        self::ensureSchema($db);
        $cur = self::settings($db);

        $live    = !empty($in['live_mode']);
        $enabled = !empty($in['enabled']);
        $keyId   = trim((string)($in['key_id'] ?? ''));
        $currency = strtoupper(trim((string)($in['currency'] ?? 'INR'))) ?: 'INR';
        $label   = trim((string)($in['button_label'] ?? 'YEEDIPAY Payment'));
        if ($label === '') $label = 'YEEDIPAY Payment';
        $min     = (float)($in['min_amount'] ?? 1);
        $max     = (float)($in['max_amount'] ?? 100000);
        $webhookConfigured = !empty($in['webhook_configured']);

        if (!preg_match('/^rzp_(test|live)_[A-Za-z0-9]+$/', $keyId)) {
            return [false, 'Key ID must look like rzp_test_xxxx or rzp_live_xxxx.'];
        }
        $keyIsLive = str_starts_with($keyId, 'rzp_live_');
        if ($live !== $keyIsLive) {
            return [false, $live
                ? 'Live mode requires a rzp_live_ Key ID.'
                : 'Test mode requires a rzp_test_ Key ID.'];
        }
        if ($currency !== 'INR' && strlen($currency) !== 3) {
            return [false, 'Currency must be a 3-letter ISO code.'];
        }
        if ($min < 1) $min = 1;
        if ($max < $min) $max = $min;

        // Secrets: update only when a new non-empty value is provided.
        $keySecretEnc = (string)($cur['key_secret_encrypted'] ?? '');
        $newSecret = trim((string)($in['key_secret'] ?? ''));
        if ($newSecret !== '') {
            $keySecretEnc = Crypto::encrypt($newSecret);
        }
        $webhookSecretEnc = (string)($cur['webhook_secret_encrypted'] ?? '');
        $newWh = trim((string)($in['webhook_secret'] ?? ''));
        if ($newWh !== '') {
            $webhookSecretEnc = Crypto::encrypt($newWh);
        }

        if ($enabled && ($keyId === '' || $keySecretEnc === '')) {
            return [false, 'Cannot enable Razorpay without a Key ID and Key Secret.'];
        }
        if ($enabled && $live && ($webhookSecretEnc === '' || !$webhookConfigured)) {
            return [false, 'Live mode requires a webhook secret and the "webhook configured" confirmation (so payments can be verified server-side).'];
        }

        $db->prepare('UPDATE razorpay_settings SET enabled=?, live_mode=?, key_id=?, key_secret_encrypted=?, webhook_secret_encrypted=?, currency=?, button_label=?, min_amount=?, max_amount=?, webhook_configured=?, updated_at=UTC_TIMESTAMP() WHERE id=1')
           ->execute([
               $enabled ? 1 : 0,
               $live ? 1 : 0,
               $keyId,
               $keySecretEnc !== '' ? $keySecretEnc : null,
               $webhookSecretEnc !== '' ? $webhookSecretEnc : null,
               $currency,
               $label,
               number_format($min, 2, '.', ''),
               number_format($max, 2, '.', ''),
               $webhookConfigured ? 1 : 0,
           ]);
        return [true, 'Razorpay settings saved.'];
    }

    /* ------------------------------------------------------------- order create */

    public static function parseAmount(mixed $raw, float $min, float $max): float {
        $s = trim((string)$raw);
        if ($s === '' || !preg_match('/^\d+(?:\.\d{1,2})?$/', $s)) {
            throw new InvalidArgumentException('Enter a valid amount.');
        }
        $amt = (float)$s;
        if ($amt < $min) throw new InvalidArgumentException('Minimum amount is '.rtrim(rtrim(number_format($min,2,'.',''),'0'),'.').'.');
        if ($amt > $max) throw new InvalidArgumentException('Maximum amount is '.rtrim(rtrim(number_format($max,2,'.',''),'0'),'.').'.');
        return (float)number_format($amt, 2, '.', '');
    }

    /**
     * Create a Razorpay Order and a local CREATED row.
     * @param array<string,mixed>|null $customer
     * @return array<string,mixed> local razorpay_orders row + checkout fields
     */
    public static function createOrder(PDO $db, ?array $customer, float $amountRupees, array $payer = []): array {
        self::ensureSchema($db);
        if (!self::isConfigured($db)) {
            throw new RuntimeException('Razorpay is not configured.');
        }
        $s = self::settings($db);
        if ((int)($s['enabled'] ?? 0) !== 1) {
            throw new RuntimeException('Razorpay payments are disabled.');
        }
        $currency = (string)($s['currency'] ?? 'INR');
        $live = (int)($s['live_mode'] ?? 0) === 1;
        $amountPaise = (int)round($amountRupees * 100);
        if ($amountPaise < 100) {
            throw new RuntimeException('Amount too small for Razorpay (minimum ₹1).');
        }

        $code = $customer ? strtoupper((string)($customer['code'] ?? '')) : 'GUEST';
        $localRef = 'RZP-'.($code !== '' ? $code.'-' : '').strtoupper(bin2hex(random_bytes(6)));
        $payerName = trim((string)($payer['name'] ?? ''));
        if ($payerName === '' && $customer) $payerName = trim((string)($customer['name'] ?? ''));
        $payerContact = trim((string)($payer['contact'] ?? ($customer['phone'] ?? '')));
        $payerEmail = trim((string)($payer['email'] ?? ($customer['email'] ?? '')));
        if (strlen($payerName) > 180) $payerName = substr($payerName, 0, 180);
        $notes = [
            'yeedipay_reference' => $localRef,
            'customer_code'      => $code,
            'payer_name'         => $payerName,
            'payer_contact'      => $payerContact,
        ];

        $res = self::api($db, 'POST', '/orders', [
            'amount'          => $amountPaise,
            'currency'        => $currency,
            'receipt'         => $localRef,
            'payment_capture' => 1,
            'notes'           => $notes,
        ]);
        $orderId = (string)($res['id'] ?? '');
        if ($orderId === '' || (string)($res['entity'] ?? '') !== 'order') {
            throw new RuntimeException('Razorpay did not return a valid order.');
        }
        // Defensive: provider must echo our amount/currency.
        if ((int)($res['amount'] ?? -1) !== $amountPaise || strtoupper((string)($res['currency'] ?? '')) !== strtoupper($currency)) {
            throw new RuntimeException('Razorpay order amount/currency mismatch.');
        }

        $db->prepare('INSERT INTO razorpay_orders(local_reference,customer_id,customer_code,buyer_name,rzp_order_id,amount_paise,currency,status,email,contact,notes_json,live_mode,created_at,updated_at) VALUES(?,?,?,?,?,?,?,\'CREATED\',?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())')
           ->execute([
               $localRef,
               $customer ? (int)$customer['id'] : null,
               $code !== '' ? $code : null,
               $payerName !== '' ? $payerName : null,
               $orderId,
               $amountPaise,
               $currency,
               $payerEmail !== '' ? $payerEmail : null,
               $payerContact !== '' ? $payerContact : null,
               json_encode($notes, JSON_UNESCAPED_SLASHES),
               $live ? 1 : 0,
           ]);
        return self::orderByLocalRef($db, $localRef) ?: [];
    }

    /* ----------------------------------------------------------- lookups / lists */

    public static function orderByLocalRef(PDO $db, string $ref): ?array {
        $st = $db->prepare('SELECT * FROM razorpay_orders WHERE local_reference=? LIMIT 1');
        $st->execute([$ref]);
        return $st->fetch() ?: null;
    }
    public static function orderByRzpId(PDO $db, string $orderId): ?array {
        $st = $db->prepare('SELECT * FROM razorpay_orders WHERE rzp_order_id=? LIMIT 1');
        $st->execute([$orderId]);
        return $st->fetch() ?: null;
    }

    /** @return array<int,array<string,mixed>> */
    public static function recentForCustomer(PDO $db, int $customerId, int $limit = 20): array {
        $st = $db->prepare('SELECT * FROM razorpay_orders WHERE customer_id=? ORDER BY id DESC LIMIT '.max(1,(int)$limit));
        $st->execute([$customerId]);
        return $st->fetchAll() ?: [];
    }

    /* --------------------------------------------- signature verification (pure) */

    /** Razorpay checkout handler signature: HMAC_SHA256(order_id|payment_id, key_secret). */
    public static function verifyCheckoutSignature(string $orderId, string $paymentId, string $signature, string $keySecret): bool {
        if ($orderId === '' || $paymentId === '' || $signature === '' || $keySecret === '') return false;
        $expected = hash_hmac('sha256', $orderId.'|'.$paymentId, $keySecret);
        return hash_equals($expected, $signature);
    }

    /** Webhook signature: HMAC_SHA256(raw_body, webhook_secret). */
    public static function verifyWebhookSignature(string $rawBody, string $signature, string $webhookSecret): bool {
        if ($rawBody === '' || $signature === '' || $webhookSecret === '') return false;
        $expected = hash_hmac('sha256', $rawBody, $webhookSecret);
        return hash_equals($expected, $signature);
    }

    /* --------------------------------------------------------- confirm (trusted) */

    /**
     * Idempotently confirm a payment against a local order from trusted evidence.
     * Validates order association, amount, and currency before marking PAID.
     *
     * @param array<string,mixed> $payment Razorpay payment entity (id, order_id, amount, currency, status, method, email, contact)
     * @param string $source e.g. 'WEBHOOK', 'API_FETCH'
     * @return array{0:string,1:string} [resultCode, note]  resultCode in PAID|FAILED|REVIEW|IGNORED
     */
    public static function applyCapture(PDO $db, array $payment, string $source): array {
        self::ensureSchema($db);
        $orderId   = (string)($payment['order_id'] ?? '');
        $paymentId = (string)($payment['id'] ?? '');
        $amount    = (int)($payment['amount'] ?? 0);
        $currency  = strtoupper((string)($payment['currency'] ?? ''));
        $status    = strtolower((string)($payment['status'] ?? ''));
        if ($orderId === '') return ['IGNORED', 'No order_id on payment'];

        $order = self::orderByRzpId($db, $orderId);
        if (!$order) return ['IGNORED', 'No local order for '.$orderId];

        $local = strtoupper((string)$order['status']);
        if ($local === 'PAID') {
            // Idempotent: already settled. Only flag if a different payment claims it.
            if ($paymentId !== '' && (string)$order['rzp_payment_id'] !== '' && (string)$order['rzp_payment_id'] !== $paymentId) {
                return ['REVIEW', 'Order already paid by a different payment_id'];
            }
            return ['PAID', 'Already paid (idempotent)'];
        }

        if ($status === 'failed') {
            $db->prepare("UPDATE razorpay_orders SET status='FAILED', rzp_payment_id=COALESCE(rzp_payment_id,?), method=COALESCE(NULLIF(?,''),method), email=COALESCE(NULLIF(?,''),email), contact=COALESCE(NULLIF(?,''),contact), verification_source=?, verification_note=?, updated_at=UTC_TIMESTAMP() WHERE rzp_order_id=? AND status NOT IN('PAID')")
               ->execute([$paymentId ?: null, (string)($payment['method'] ?? '') ?: null, (string)($payment['email'] ?? '') ?: null, (string)($payment['contact'] ?? '') ?: null, $source, 'Payment failed at Razorpay', $orderId]);
            return ['FAILED', 'Payment failed'];
        }

        if ($status !== 'captured' && $status !== 'authorized') {
            return ['IGNORED', 'Payment status not final: '.$status];
        }

        // Validate amount & currency against what we created.
        $note = '';
        $review = false;
        if ($amount !== (int)$order['amount_paise']) { $review = true; $note = 'Amount mismatch (order '.$order['amount_paise'].' vs paid '.$amount.')'; }
        if ($currency !== strtoupper((string)$order['currency'])) { $review = true; $note = trim($note.' Currency mismatch ('.$order['currency'].' vs '.$currency.')'); }

        if ($review) {
            $db->prepare("UPDATE razorpay_orders SET status='REVIEW', rzp_payment_id=COALESCE(rzp_payment_id,?), captured_amount_paise=?, method=COALESCE(NULLIF(?,''),method), email=COALESCE(NULLIF(?,''),email), contact=COALESCE(NULLIF(?,''),contact), verification_source=?, verification_note=?, updated_at=UTC_TIMESTAMP() WHERE rzp_order_id=? AND status<>'PAID'")
               ->execute([$paymentId ?: null, $amount, (string)($payment['method'] ?? '') ?: null, (string)($payment['email'] ?? '') ?: null, (string)($payment['contact'] ?? '') ?: null, $source, substr($note,0,500), $orderId]);
            return ['REVIEW', $note];
        }

        if ($status === 'authorized') {
            // Authorized but not yet captured — keep PENDING, wait for capture webhook/fetch.
            $db->prepare("UPDATE razorpay_orders SET status='PENDING', rzp_payment_id=COALESCE(rzp_payment_id,?), verification_source=?, verification_note=?, updated_at=UTC_TIMESTAMP() WHERE rzp_order_id=? AND status='CREATED'")
               ->execute([$paymentId ?: null, $source, 'Authorized, awaiting capture', $orderId]);
            return ['IGNORED', 'Authorized, awaiting capture'];
        }

        // captured + validated → PAID (guarded so concurrent callers can't double-apply)
        $st = $db->prepare("UPDATE razorpay_orders SET status='PAID', verified=1, rzp_payment_id=?, captured_amount_paise=?, method=COALESCE(NULLIF(?,''),method), email=COALESCE(NULLIF(?,''),email), contact=COALESCE(NULLIF(?,''),contact), verification_source=?, verification_note='Verified capture', paid_at=UTC_TIMESTAMP(), updated_at=UTC_TIMESTAMP() WHERE rzp_order_id=? AND status<>'PAID'");
        $st->execute([$paymentId ?: null, $amount, (string)($payment['method'] ?? '') ?: null, (string)($payment['email'] ?? '') ?: null, (string)($payment['contact'] ?? '') ?: null, $source, $orderId]);
        if ($st->rowCount() === 0) {
            return ['PAID', 'Already paid (race, idempotent)'];
        }
        // First genuine capture for this order → treat as a confirmed payment and
        // activate the customer's merchant service (idempotent; only fires on the
        // real CREATED/PENDING→PAID transition, so a replayed webhook won't re-run).
        $custId = (int)($order['customer_id'] ?? 0);
        if ($custId > 0) {
            try { PaymentConfirmService::activateService($db, $custId); } catch (Throwable $e) {}
            // Assign the customer's account number on first confirmed payment, the
            // same as the bank/UPI SUCCESS path. Idempotent: keeps any existing one.
            try { TransactionService::assignAccountNumber($db, $custId); } catch (Throwable $e) {}
        }
        return ['PAID', 'Verified capture'];
    }

    /**
     * Authenticated server-side re-check of an order's status (used by the result
     * page poll and when the browser was closed). Trusted: it queries Razorpay with
     * our key. Returns the refreshed local row.
     * @param array<string,mixed> $order local row
     * @return array<string,mixed>
     */
    public static function refreshFromApi(PDO $db, array $order): array {
        $orderId = (string)($order['rzp_order_id'] ?? '');
        if ($orderId === '' || strtoupper((string)$order['status']) === 'PAID') {
            return $order;
        }
        try {
            $payments = self::api($db, 'GET', '/orders/'.rawurlencode($orderId).'/payments', null);
        } catch (Throwable $e) {
            return $order;
        }
        $items = is_array($payments['items'] ?? null) ? $payments['items'] : [];
        foreach ($items as $p) {
            if (!is_array($p)) continue;
            $p['order_id'] = $p['order_id'] ?? $orderId;
            [$code] = self::applyCapture($db, $p, 'API_FETCH');
            if ($code === 'PAID') break;
        }
        return self::orderByRzpId($db, $orderId) ?: $order;
    }

    /* ------------------------------------------------------------------ webhook */

    /**
     * Verify + record + process an inbound webhook. Idempotent by event id.
     * @return array{0:int,1:string} [httpStatus, message]
     */
    public static function handleWebhook(PDO $db, string $rawBody, string $signatureHeader): array {
        self::ensureSchema($db);
        $secret = self::webhookSecret($db);
        if ($secret === '') {
            return [503, 'Webhook secret not configured'];
        }
        $valid = self::verifyWebhookSignature($rawBody, $signatureHeader, $secret);
        $data = json_decode($rawBody, true);
        if (!is_array($data)) {
            return [400, 'Invalid JSON'];
        }
        $eventType = (string)($data['event'] ?? '');
        // Razorpay does not always send a stable event id header; derive a stable key.
        $eventId = (string)($data['id'] ?? ($signatureHeader !== '' ? 'sig_'.substr(hash('sha256', $signatureHeader.$eventType), 0, 48) : 'evt_'.substr(hash('sha256', $rawBody), 0, 48)));

        $payEntity = $data['payload']['payment']['entity'] ?? null;
        $orderEntity = $data['payload']['order']['entity'] ?? null;
        $rzpOrderId = (string)($payEntity['order_id'] ?? ($orderEntity['id'] ?? ''));
        $rzpPaymentId = (string)($payEntity['id'] ?? '');
        $amount = isset($payEntity['amount']) ? (int)$payEntity['amount'] : (isset($orderEntity['amount']) ? (int)$orderEntity['amount'] : null);
        $currency = strtoupper((string)($payEntity['currency'] ?? ($orderEntity['currency'] ?? ''))) ?: null;

        // Record the event (dedupe on event_id). If duplicate, acknowledge 200 and stop.
        try {
            $db->prepare('INSERT INTO razorpay_events(event_id,event_type,rzp_order_id,rzp_payment_id,signature_valid,amount_paise,currency,processed,payload_json,created_at) VALUES(?,?,?,?,?,?,?,0,?,UTC_TIMESTAMP())')
               ->execute([$eventId, $eventType, $rzpOrderId ?: null, $rzpPaymentId ?: null, $valid ? 1 : 0, $amount, $currency, $rawBody]);
        } catch (PDOException $e) {
            if ((int)$e->errorInfo[1] === 1062) {
                return [200, 'Duplicate event ignored'];
            }
            throw $e;
        }

        if (!$valid) {
            self::markEvent($db, $eventId, false, 'Invalid signature');
            return [401, 'Invalid signature'];
        }

        $note = 'No action';
        // payment.captured / payment.failed / order.paid all carry payload.payment.entity.
        if (is_array($payEntity) && in_array($eventType, ['payment.captured', 'payment.authorized', 'payment.failed', 'order.paid'], true)) {
            [, $note] = self::applyCapture($db, $payEntity, 'WEBHOOK');
        }
        self::markEvent($db, $eventId, true, $note);
        return [200, 'OK: '.$note];
    }

    private static function markEvent(PDO $db, string $eventId, bool $processed, string $note): void {
        $db->prepare('UPDATE razorpay_events SET processed=?, process_note=? WHERE event_id=?')
           ->execute([$processed ? 1 : 0, substr($note, 0, 500), $eventId]);
    }

    /* ---------------------------------------------------------- reconciliation */

    /** @return array<string,int> */
    public static function stats(PDO $db): array {
        self::ensureSchema($db);
        $out = ['total'=>0,'paid'=>0,'pending'=>0,'created'=>0,'failed'=>0,'cancelled'=>0,'review'=>0,'events'=>0,'events_unprocessed'=>0,'events_badsig'=>0];
        foreach ($db->query("SELECT status, COUNT(*) c FROM razorpay_orders GROUP BY status") as $r) {
            $out['total'] += (int)$r['c'];
            $k = strtolower((string)$r['status']);
            if (isset($out[$k])) $out[$k] = (int)$r['c'];
        }
        $out['events'] = (int)$db->query('SELECT COUNT(*) FROM razorpay_events')->fetchColumn();
        $out['events_unprocessed'] = (int)$db->query('SELECT COUNT(*) FROM razorpay_events WHERE processed=0 AND signature_valid=1')->fetchColumn();
        $out['events_badsig'] = (int)$db->query('SELECT COUNT(*) FROM razorpay_events WHERE signature_valid=0')->fetchColumn();
        return $out;
    }

    /**
     * Search/filter orders for admin.
     * @param array<string,mixed> $f
     * @return array<int,array<string,mixed>>
     */
    public static function search(PDO $db, array $f, int $limit = 100): array {
        self::ensureSchema($db);
        $where = [];
        $args = [];
        $q = trim((string)($f['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(local_reference LIKE ? OR rzp_order_id LIKE ? OR rzp_payment_id LIKE ? OR customer_code LIKE ? OR email LIKE ? OR contact LIKE ?)';
            for ($i=0;$i<6;$i++) $args[] = '%'.$q.'%';
        }
        $status = strtoupper(trim((string)($f['status'] ?? '')));
        if ($status !== '' && in_array($status, ['CREATED','PENDING','PAID','FAILED','CANCELLED','REVIEW'], true)) {
            $where[] = 'status=?';
            $args[] = $status;
        }
        $sql = 'SELECT * FROM razorpay_orders';
        if ($where) $sql .= ' WHERE '.implode(' AND ', $where);
        $sql .= ' ORDER BY id DESC LIMIT '.max(1, min(500, $limit));
        $st = $db->prepare($sql);
        $st->execute($args);
        return $st->fetchAll() ?: [];
    }

    /** Recent events for the reconciliation log. @return array<int,array<string,mixed>> */
    public static function recentEvents(PDO $db, int $limit = 50): array {
        self::ensureSchema($db);
        $st = $db->prepare('SELECT id,event_id,event_type,rzp_order_id,rzp_payment_id,signature_valid,amount_paise,currency,processed,process_note,created_at FROM razorpay_events ORDER BY id DESC LIMIT '.max(1,min(200,$limit)));
        $st->execute();
        return $st->fetchAll() ?: [];
    }

    public static function rupees(int $paise): string {
        return number_format($paise / 100, 2, '.', '');
    }

    /**
     * Confirmed (PAID) Razorpay captures normalized into the common transaction
     * row shape consumed by the admin transaction list and the customer
     * statement. DISPLAY ONLY — nothing is written to the `transactions` table;
     * this just lets a verified Razorpay capture show up alongside UPI rows.
     *
     * Only PAID orders are returned, mapped to status SUCCESS. Pending/failed
     * Razorpay activity stays on the dedicated Razorpay Txns page. Rows carry
     * `is_razorpay => true` so views can skip UPI-only actions (invoice, confirm).
     *
     * Filters (all optional): customer_id, q, start, end (Y-m-d).
     *
     * @param array<string,mixed> $f
     * @return list<array<string,mixed>>
     */
    public static function paidAsTxnRows(PDO $db, array $f, int $limit = 2000): array {
        self::ensureSchema($db);
        $where = ["o.status='PAID'"];
        $args = [];
        $cid = (int)($f['customer_id'] ?? 0);
        if ($cid > 0) { $where[] = 'o.customer_id=?'; $args[] = $cid; }
        $q = trim((string)($f['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(o.customer_code LIKE ? OR c.name LIKE ? OR c.phone LIKE ? OR IFNULL(c.email,\'\') LIKE ? OR IFNULL(o.rzp_payment_id,\'\') LIKE ? OR o.local_reference LIKE ? OR IFNULL(o.buyer_name,\'\') LIKE ?)';
            $like = '%'.$q.'%';
            array_push($args, $like, $like, $like, $like, $like, $like, $like);
        }
        $start = trim((string)($f['start'] ?? ''));
        $end = trim((string)($f['end'] ?? ''));
        if ($start !== '' && $end === '') $end = $start;
        if ($start === '' && $end !== '') $start = $end;
        if ($start !== '' && $end !== '') {
            [$s, $e] = local_period_bounds('custom', $start, $end);
            if ($s) { $where[] = 'COALESCE(o.paid_at,o.created_at)>=?'; $args[] = $s; }
            if ($e) { $where[] = 'COALESCE(o.paid_at,o.created_at)<?'; $args[] = $e; }
        }
        $sql = 'SELECT o.*, c.code AS c_code, c.name AS c_name, c.phone AS c_phone, c.email AS c_email
                FROM razorpay_orders o JOIN customers c ON c.id=o.customer_id
                WHERE '.implode(' AND ', $where).'
                ORDER BY COALESCE(o.paid_at,o.created_at) DESC, o.id DESC LIMIT '.max(1, min(5000, $limit));
        $st = $db->prepare($sql);
        $st->execute($args);
        $out = [];
        foreach ($st->fetchAll() as $o) {
            $paise = (int)(($o['captured_amount_paise'] ?? 0) ?: ($o['amount_paise'] ?? 0));
            $amt = self::rupees($paise);
            $ref = trim((string)($o['rzp_payment_id'] ?? '')) ?: (string)($o['local_reference'] ?? '');
            $out[] = [
                'id' => 'rzp-'.(int)$o['id'],
                'is_razorpay' => true,
                'created_at' => (string)(($o['paid_at'] ?? '') ?: ($o['created_at'] ?? '')),
                'reference' => $ref,
                'customer_id' => (int)$o['customer_id'],
                'customer_code' => (string)(($o['customer_code'] ?? '') ?: ($o['c_code'] ?? '')),
                'customer_name' => (string)($o['c_name'] ?? ''),
                'customer_phone' => (string)($o['c_phone'] ?? ''),
                'customer_email' => (string)(($o['email'] ?? '') ?: ($o['c_email'] ?? '')),
                'buyer_name' => (string)($o['buyer_name'] ?? ''),
                'bank_name' => 'Razorpay',
                'bank_code' => 'RAZORPAY',
                'requested_amount' => $amt,
                'confirmed_amount' => $amt,
                'status' => 'SUCCESS',
                'verified_utr' => $ref,
                'rrn' => '',
                'customer_reported_utr' => '',
                'upi_txn_id' => '',
                'utr_screenshot_path' => null,
                'service' => 'RAZORPAY',
                'settlement_status' => 'N/A',
            ];
        }
        return $out;
    }

    /* ----------------------------------------------------------------- HTTP/API */

    /**
     * Authenticated Razorpay REST call (HTTP Basic: key_id:key_secret).
     * @param array<string,mixed>|null $body
     * @return array<string,mixed>
     */
    private static function api(PDO $db, string $method, string $path, ?array $body): array {
        $s = self::settings($db);
        $keyId = trim((string)($s['key_id'] ?? ''));
        $keySecret = self::keySecret($db);
        if ($keyId === '' || $keySecret === '') {
            throw new RuntimeException('Razorpay credentials missing.');
        }
        $url = self::API_BASE.$path;
        $payload = $body !== null ? json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL is required for Razorpay API calls.');
        }
        $ch = curl_init($url);
        $headers = ['Accept: application/json'];
        if ($payload !== null) $headers[] = 'Content-Type: application/json';
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_USERPWD        => $keyId.':'.$keySecret,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            throw new RuntimeException('Razorpay API connection failed: '.$err);
        }
        $json = json_decode((string)$raw, true);
        if (!is_array($json)) {
            throw new RuntimeException('Razorpay API returned invalid JSON (HTTP '.$code.').');
        }
        if ($code >= 400) {
            $msg = (string)($json['error']['description'] ?? ('HTTP '.$code));
            throw new RuntimeException('Razorpay API error: '.$msg);
        }
        return $json;
    }
}
