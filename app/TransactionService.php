<?php
declare(strict_types=1);
final class TransactionService {
    private static bool $schemaReady = false;

    public static function ensureSchema(PDO $db): void {
        if (self::$schemaReady) return;
        $cust = [];
        foreach ($db->query('SHOW COLUMNS FROM customers') as $r) $cust[strtolower((string)$r['Field'])] = true;
        if (!isset($cust['email'])) $db->exec('ALTER TABLE customers ADD COLUMN email VARCHAR(180) NULL');
        if (!isset($cust['service_activated'])) $db->exec('ALTER TABLE customers ADD COLUMN service_activated TINYINT(1) NOT NULL DEFAULT 0');
        if (!isset($cust['service_activated_at'])) $db->exec('ALTER TABLE customers ADD COLUMN service_activated_at DATETIME NULL');
        if (!isset($cust['account_number'])) $db->exec('ALTER TABLE customers ADD COLUMN account_number VARCHAR(24) NULL');
        if (!isset($cust['login_id'])) $db->exec('ALTER TABLE customers ADD COLUMN login_id VARCHAR(64) NULL');
        if (!isset($cust['password_hash'])) $db->exec('ALTER TABLE customers ADD COLUMN password_hash VARCHAR(255) NULL');
        $idx = $db->query("SHOW INDEX FROM customers WHERE Key_name='uq_customer_account'")->fetch();
        if (!$idx) {
            try { $db->exec('ALTER TABLE customers ADD UNIQUE KEY uq_customer_account(account_number)'); } catch (Throwable $e) {}
        }
        $loginIdx = $db->query("SHOW INDEX FROM customers WHERE Key_name='uq_customer_login_id'")->fetch();
        if (!$loginIdx) {
            try { $db->exec('ALTER TABLE customers ADD UNIQUE KEY uq_customer_login_id(login_id)'); } catch (Throwable $e) {}
        }
        $cols = [];
        foreach ($db->query('SHOW COLUMNS FROM transactions') as $r) $cols[strtolower((string)$r['Field'])] = true;
        if (!isset($cols['service'])) $db->exec("ALTER TABLE transactions ADD COLUMN service VARCHAR(64) NOT NULL DEFAULT 'UPI'");
        if (!isset($cols['settlement_status'])) $db->exec("ALTER TABLE transactions ADD COLUMN settlement_status VARCHAR(32) NOT NULL DEFAULT 'UNSETTLED'");
        if (!isset($cols['invoice_ref'])) $db->exec('ALTER TABLE transactions ADD COLUMN invoice_ref VARCHAR(160) NULL');
        if (!isset($cols['customer_paid_ack_at'])) $db->exec('ALTER TABLE transactions ADD COLUMN customer_paid_ack_at DATETIME NULL');
        if (!isset($cols['utr_screenshot_path'])) $db->exec('ALTER TABLE transactions ADD COLUMN utr_screenshot_path VARCHAR(255) NULL');
        if (!isset($cols['utr_screenshot_orig'])) $db->exec('ALTER TABLE transactions ADD COLUMN utr_screenshot_orig VARCHAR(180) NULL');
        if (!isset($cols['customer_reported_email'])) $db->exec('ALTER TABLE transactions ADD COLUMN customer_reported_email VARCHAR(180) NULL');
        if (!isset($cols['buyer_name'])) $db->exec('ALTER TABLE transactions ADD COLUMN buyer_name VARCHAR(180) NULL AFTER customer_reported_email');
        if (!isset($cols['verified_utr'])) $db->exec('ALTER TABLE transactions ADD COLUMN verified_utr VARCHAR(160) NULL AFTER buyer_name');
        $db->exec("UPDATE transactions SET service='UPI' WHERE service IS NULL OR service=''");
        $db->exec("UPDATE transactions SET settlement_status='N/A' WHERE status IN ('FAILED','EXPIRED') AND settlement_status='UNSETTLED'");
        try {
            $db->exec("UPDATE transactions t JOIN customers c ON c.id=t.customer_id SET t.buyer_name=c.name WHERE (t.buyer_name IS NULL OR t.buyer_name='') AND c.name IS NOT NULL AND c.name<>''");
            $db->exec("UPDATE transactions SET verified_utr=COALESCE(NULLIF(TRIM(rrn),''), NULLIF(TRIM(upi_txn_id),''), NULLIF(TRIM(customer_reported_utr),''))
                WHERE status='SUCCESS' AND (verified_utr IS NULL OR verified_utr='')");
        } catch (Throwable $e) {
        }
        AdcLink::ensureSchema($db);
        BankSmsService::ensureSchema($db);
        self::$schemaReady = true;
    }

    public static function customerByCode(PDO $db,string $code): ?array { $st=$db->prepare('SELECT * FROM customers WHERE code=? LIMIT 1');$st->execute([strtoupper($code)]);$r=$st->fetch();return $r?:null; }
    public static function txnByRef(PDO $db,string $ref): ?array { $st=$db->prepare('SELECT t.*,c.code customer_code,c.name customer_name,c.phone customer_phone,b.bank_name,b.payee_name,b.vpa,b.mcc,b.qr_payload,b.qr_image_path,b.signed_qr FROM transactions t JOIN customers c ON c.id=t.customer_id JOIN bank_profiles b ON b.id=t.bank_id WHERE t.reference=?');$st->execute([$ref]);$r=$st->fetch();return $r?:null; }
    public static function newCustomerCode(PDO $db): string { $n=(int)$db->query('SELECT COUNT(*) FROM customers')->fetchColumn()+1;while(true){$code='CUST'.str_pad((string)$n,4,'0',STR_PAD_LEFT);$st=$db->prepare('SELECT 1 FROM customers WHERE code=?');$st->execute([$code]);if(!$st->fetchColumn())return $code;$n++;} }
    public const PRESET_AMOUNTS = [1, 100, 1000, 2000, 5000, 10000, 20000, 50000, 100000];
    public const PUBLIC_PAY_AMOUNTS = [100, 500, 1000, 2000, 5000, 10000, 25000, 50000];
    public const CHECK_STATUS_SECONDS = 20;
    public const PAID_VERIFY_SECONDS = 30;

    public static function parsePresetAmount(mixed $raw): float {
        $s = trim((string)$raw);
        if ($s === '' || !preg_match('/^\d+(?:\.\d{1,2})?$/', $s)) {
            throw new InvalidArgumentException('Select an amount');
        }
        $n = (int)round((float)$s);
        if (!in_array($n, self::PRESET_AMOUNTS, true)) {
            throw new InvalidArgumentException('Amount must be one of the listed options');
        }
        return (float)$n;
    }

    public static function parseEnteredAmount(mixed $raw): float {
        $s = str_replace(',', '', trim((string)$raw));
        if ($s === '' || !preg_match('/^\d+(?:\.\d{1,2})?$/', $s)) {
            throw new InvalidArgumentException('Enter a valid amount');
        }
        $n = round((float)$s, 2);
        if ($n < 1 || $n > 200000) {
            throw new InvalidArgumentException('Amount must be between ₹1 and ₹2,00,000');
        }
        return $n;
    }

    public static function recentForCustomer(PDO $db, int $customerId, int $limit = 12): array {
        $st = $db->prepare('SELECT t.*,b.bank_name FROM transactions t JOIN bank_profiles b ON b.id=t.bank_id WHERE t.customer_id=? ORDER BY t.id DESC LIMIT '.max(1, min(50, $limit)));
        $st->execute([$customerId]);
        return $st->fetchAll();
    }

    public static function latestPending(PDO $db, int $customerId): ?array {
        $st = $db->prepare("SELECT t.*,c.code customer_code,c.name customer_name,b.bank_name,b.payee_name,b.vpa,b.mcc,b.qr_payload,b.qr_image_path,b.signed_qr FROM transactions t JOIN customers c ON c.id=t.customer_id JOIN bank_profiles b ON b.id=t.bank_id WHERE t.customer_id=? AND t.status='PENDING' ORDER BY t.id DESC LIMIT 1");
        $st->execute([$customerId]);
        $r = $st->fetch();
        return $r ?: null;
    }

    /**
     * The customer picks a round amount. The QR collects 2 to 50 paise less
     * when that still leaves at least ₹1, because GPay hides the bank list
     * for anything under ₹1. For ₹1 tests / many simultaneous payers on one
     * VPA, bump by unused paise above ₹1 (1.01, 1.02, …) so bank SMS amount
     * matching stays one-to-one.
     */
    public static function collectAmount(PDO $db, array $bank, float $selected): float {
        $selectedPaise = (int)round($selected * 100);
        $low = max(100, $selectedPaise - 50);
        $high = $selectedPaise - 2;
        if ($high < $low) {
            $used = self::pendingAmountKeys($db, $bank);
            if ($selectedPaise >= 100) {
                $maxBump = max(1, min(99, Env::int('COLLECT_AMOUNT_ONE_RUPEE_MAX_PAISE', 50)));
                for ($paise = $selectedPaise + 1; $paise <= $selectedPaise + $maxBump; $paise++) {
                    $key = number_format($paise / 100, 2, '.', '');
                    if (!isset($used[$key])) {
                        return (float)$key;
                    }
                }
            }
            return round($selected, 2);
        }
        $used = self::pendingAmountKeys($db, $bank);
        $span = $high - $low + 1;
        for ($i = 0; $i < min(40, $span); $i++) {
            $key = number_format(random_int($low, $high) / 100, 2, '.', '');
            if (!isset($used[$key])) return (float)$key;
        }
        for ($paise = $high; $paise >= $low; $paise--) {
            $key = number_format($paise / 100, 2, '.', '');
            if (!isset($used[$key])) return (float)$key;
        }
        return (float)number_format($high / 100, 2, '.', '');
    }

    /** @return array<string,true> */
    private static function pendingAmountKeys(PDO $db, array $bank): array {
        $used = [];
        $vpa = strtolower(trim((string)($bank['vpa'] ?? '')));
        if ($vpa !== '') {
            $st = $db->prepare("SELECT t.requested_amount
                                FROM transactions t
                                JOIN bank_profiles b ON b.id=t.bank_id
                                WHERE t.status='PENDING' AND t.requested_amount IS NOT NULL AND LOWER(b.vpa)=?");
            $st->execute([$vpa]);
        } else {
            $st = $db->prepare("SELECT requested_amount FROM transactions WHERE status='PENDING' AND bank_id=? AND requested_amount IS NOT NULL");
            $st->execute([(int)($bank['id'] ?? 0)]);
        }
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $amt) {
            $used[number_format((float)$amt, 2, '.', '')] = true;
        }
        return $used;
    }

    public static function isExactPreset(float $amount, array $presets, mixed $mode): bool {
        if ((string)$mode !== 'preset') return false;
        foreach ($presets as $preset) {
            if (abs($amount - (float)$preset) < 0.001) return true;
        }
        return false;
    }

    public static function createAttempt(PDO $db,array $c,?float $amount, ?string $invoiceRef = null, string $service = 'UPI', string $settlementStatus = 'UNSETTLED', bool $exactAmount = false): array {
        self::ensureSchema($db);
        $bank=BankService::active($db);if(!$bank)throw new RuntimeException('No active bank is configured.');[$ok,$msg]=LimitService::check($db,$c,$amount);if(!$ok)throw new RuntimeException($msg);
        $svc = strtoupper(trim($service)) ?: 'UPI';
        if ($amount !== null && $svc !== 'WALLET_RECHARGE' && !$exactAmount) {
            $amount = self::collectAmount($db, $bank, (float)$amount);
        }
        if ($amount !== null) {
            $amount = (float)number_format((float)$amount, 2, '.', '');
        }
        $ref=random_reference($c['code'],$bank['code']);$intent=PaymentConfirmService::intentForAttempt($bank,$amount,$ref);
        if ($amount !== null && str_starts_with(strtolower(trim($intent)), 'upi:')) {
            $parsedIntent = BankService::parseUpiUri($intent);
            $intentAmount = trim((string)($parsedIntent['params']['am'] ?? ''));
            if ($intentAmount !== '' && is_numeric($intentAmount) && abs((float)$intentAmount - (float)$amount) > 0.009) {
                throw new RuntimeException('Bank QR amount does not match the requested amount. Configure a compatible dynamic/unsigned QR or use the QR fixed amount.');
            }
        }
        $invoice = trim((string)$invoiceRef);
        if (strlen($invoice) > 160) $invoice = substr($invoice, 0, 160);
        $amt = $amount===null?null:number_format($amount,2,'.','');
        $settle = strtoupper(trim($settlementStatus)) ?: 'UNSETTLED';
        $buyerName = trim((string)($c['name'] ?? ''));
        if (strlen($buyerName) > 180) {
            $buyerName = substr($buyerName, 0, 180);
        }
        $st=$db->prepare("INSERT INTO transactions(customer_id,bank_id,reference,status,requested_amount,intent_uri,limit_breach,limit_note,service,settlement_status,invoice_ref,buyer_name,created_at,updated_at) VALUES(?,?,?,'PENDING',?,?,0,'',?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
        $st->execute([$c['id'],$bank['id'],$ref,$amt,$intent,$svc,$settle,$invoice!==''?$invoice:null,$buyerName !== '' ? $buyerName : null]);
        $id=(int)$db->lastInsertId();
        SmsService::enqueueTxn($db,$id,'PENDING');
        $linked = AdcLink::attach($db, $ref, $bank, $amount);
        return ['id'=>$id,'reference'=>$ref,'bank'=>$bank,'intent'=>$intent,'adc'=>$linked];
    }

    /** @return array<string,mixed>|null */
    public static function acknowledgePaid(PDO $db, string $reference): ?array {
        self::ensureSchema($db);
        $reference = strtoupper(trim($reference));
        $t = self::txnByRef($db, $reference);
        if (!$t) return null;

        if (strtoupper((string)$t['status']) === 'PENDING' && empty($t['customer_paid_ack_at'])) {
            $db->prepare("UPDATE transactions
                          SET customer_paid_ack_at=UTC_TIMESTAMP(), updated_at=UTC_TIMESTAMP()
                          WHERE reference=? AND status='PENDING' AND customer_paid_ack_at IS NULL")
               ->execute([$reference]);
            $t = self::txnByRef($db, $reference) ?: $t;
            try {
                BankSmsService::tagUnmatchedSmsForCustomerAck($db, $t);
            } catch (Throwable $e) {
                error_log('Bank SMS customer ack tag failed: '.$e->getMessage());
            }
        }

        if (strtoupper((string)($t['status'] ?? '')) === 'PENDING' && !empty($t['customer_paid_ack_at'])) {
            try {
                $t = BankSmsService::matchAcknowledgedTransaction($db, $t);
            } catch (Throwable $e) {
                error_log('Bank SMS amount match after acknowledgement failed: '.$e->getMessage());
                $t = self::txnByRef($db, $reference) ?: $t;
            }
        }
        return $t;
    }

    /** Pending payments stay open until they are paid, cancelled, or fail. */
    public static function expireDuePayments(PDO $db): void {
    }

    /** @param array<string,mixed> $t @return array<string,mixed> */
    public static function syncPaymentWindow(PDO $db, array $t): array {
        self::expireDuePayments($db);
        $ref = strtoupper(trim((string)($t['reference'] ?? '')));
        if ($ref === '') return $t;
        return self::txnByRef($db, $ref) ?: $t;
    }

    /** @return array<string,mixed>|null */
    public static function cancelByCustomer(PDO $db, string $reference): ?array {
        $reference = strtoupper(trim($reference));
        $t = self::txnByRef($db, $reference);
        if (!$t) return null;
        $t = self::syncPaymentWindow($db, $t);
        if (strtoupper((string)$t['status']) !== 'PENDING') return $t;
        $db->prepare("UPDATE transactions
                      SET status='FAILED',
                          confirmation_source='CUSTOMER_CANCEL',
                          settlement_status='N/A',
                          limit_breach=0,
                          limit_note='',
                          updated_at=UTC_TIMESTAMP()
                      WHERE id=? AND status='PENDING'")
           ->execute([(int)$t['id']]);
        try { WalletService::onTransactionClosed($db, (int)$t['id'], 'FAILED'); } catch (Throwable $e) {}
        return self::txnByRef($db, $reference) ?: $t;
    }

    /**
     * @param array<string,mixed> $t
     * @return array<string,mixed>
     */
    public static function refreshCustomerPayment(PDO $db, array $t): array {
        $ref = (string)($t['reference'] ?? '');
        try { AdcLink::syncReference($db, $ref); } catch (Throwable $e) {}
        $t = self::txnByRef($db, $ref) ?: $t;
        $t = self::syncPaymentWindow($db, $t);
        $recoverable = strtoupper((string)($t['status'] ?? '')) === 'FAILED'
            && strtoupper((string)($t['confirmation_source'] ?? '')) === 'NOT_CONFIRMED'
            && !empty($t['customer_paid_ack_at']);
        if ((strtoupper((string)($t['status'] ?? '')) === 'PENDING' || $recoverable)
            && !empty($t['customer_paid_ack_at'])) {
            try {
                $t = BankSmsService::matchAcknowledgedTransaction($db, $t);
            } catch (Throwable $e) {
                error_log('Bank SMS match during status refresh failed: '.$e->getMessage());
                $t = self::txnByRef($db, $ref) ?: $t;
            }
        }
        $t = PaymentConfirmService::refresh($db, $t);
        return self::syncPaymentWindow($db, $t);
    }

    /** @param array<string,mixed> $t @return array{code:string,label:string} */
    public static function customerOutcome(array $t): array {
        $status = strtoupper((string)($t['status'] ?? ''));
        $source = strtoupper((string)($t['confirmation_source'] ?? ''));
        if ($status === 'SUCCESS') return ['code' => 'paid', 'label' => 'PAID'];
        if ($status === 'EXPIRED') return ['code' => 'expired', 'label' => 'Session Expired'];
        if ($status === 'FAILED' && str_starts_with($source, 'CUSTOMER_CANCEL')) return ['code' => 'cancelled', 'label' => 'Payment Cancelled'];
        if ($status === 'FAILED') return ['code' => 'failed', 'label' => 'Payment Failed'];
        return ['code' => 'pending', 'label' => ''];
    }

    /** @param array<string,mixed> $t @return array<string,mixed> */
    public static function statusPayload(array $t): array {
        $v = self::verificationState($t);
        $outcome = self::customerOutcome($t);
        $buyerLabel = self::displayBuyerName($t);
        $verifiedUtrLabel = self::displayVerifiedUtr($t);
        return [
            'ok' => true,
            'reference' => $t['reference'],
            'status' => $t['status'],
            'view' => BankSmsService::customerView($t),
            'outcome' => $outcome['code'],
            'outcome_label' => $outcome['label'],
            'receipt' => $outcome['code'] === 'paid',
            'verification_state' => $v['state'],
            'customer_acknowledged' => $v['acknowledged'],
            'verification_remaining_seconds' => $v['remaining'],
            'verification_buffer_seconds' => self::PAID_VERIFY_SECONDS,
            'customer_message' => $outcome['label'] !== '' ? $outcome['label'] : $v['message'],
            'sms_match' => (string)($t['sms_match'] ?? ''),
            'amount' => (string)($t['confirmed_amount'] ?? $t['requested_amount'] ?? ''),
            'utr' => $verifiedUtrLabel === 'Not Available' ? '' : $verifiedUtrLabel,
            'verified_utr' => $verifiedUtrLabel,
            'buyer_name' => $buyerLabel,
            'expires_in' => BankSmsService::qrSecondsLeft($t),
            'limit_breach' => (bool)($t['limit_breach'] ?? false),
        ];
    }

    /** @return array{state:string,acknowledged:bool,remaining:int,message:string} */
    public static function verificationState(array $t): array {
        $status = strtoupper((string)($t['status'] ?? ''));
        $outcome = self::customerOutcome($t);
        if ($status === 'SUCCESS') return ['state'=>'CONFIRMED','acknowledged'=>true,'remaining'=>0,'message'=>'Payment confirmed successfully.'];
        if ($outcome['code'] === 'expired') return ['state'=>'EXPIRED','acknowledged'=>!empty($t['customer_paid_ack_at']),'remaining'=>0,'message'=>'Session Expired'];
        if ($outcome['code'] === 'cancelled') return ['state'=>'CANCELLED','acknowledged'=>!empty($t['customer_paid_ack_at']),'remaining'=>0,'message'=>'Payment Cancelled'];
        if ($outcome['code'] === 'failed') return ['state'=>'FAILED','acknowledged'=>!empty($t['customer_paid_ack_at']),'remaining'=>0,'message'=>'Payment Failed'];
        if (strtoupper((string)($t['sms_match'] ?? '')) === 'REVIEW_REQUIRED') return ['state'=>'REVIEW_REQUIRED','acknowledged'=>!empty($t['customer_paid_ack_at']),'remaining'=>0,'message'=>'Payment needs manual verification. Do not pay again.'];
        $ack = trim((string)($t['customer_paid_ack_at'] ?? ''));
        if ($ack === '') return ['state'=>'AWAITING_PAYMENT','acknowledged'=>false,'remaining'=>0,'message'=>'Scan the QR code and make payment and after payment success please press the I HAVE PAID button.'];
        $ts = strtotime($ack.' UTC');
        if ($ts === false) $ts = time();
        $remaining = max(0, self::PAID_VERIFY_SECONDS - max(0, time() - $ts));
        if ($remaining > 0) return ['state'=>'VERIFYING','acknowledged'=>true,'remaining'=>$remaining,'message'=>'Checking bank confirmation. Please do not refresh or pay again.'];
        return ['state'=>'WAITING_BANK_SMS','acknowledged'=>true,'remaining'=>0,'message'=>'Bank SMS is delayed. We are continuing verification. Please do not pay again.'];
    }

    public static function checkWaitLeft(array $t, int $waitSeconds = self::CHECK_STATUS_SECONDS): int {
        if (strtoupper((string)($t['status'] ?? '')) !== 'PENDING') {
            return 0;
        }
        $created = strtotime((string)($t['created_at'] ?? '').' UTC');
        if ($created === false) {
            return $waitSeconds;
        }
        return max(0, $waitSeconds - (time() - $created));
    }

    public static function isValidUtr(string $utr): bool {
        return (bool)preg_match('/^[0-9]{12}$/', $utr) || (bool)preg_match('/^[A-Z0-9]{12,16}$/', $utr);
    }

    public static function assignAccountNumber(PDO $db, int $customerId): string {
        self::ensureSchema($db);
        $st = $db->prepare('SELECT account_number FROM customers WHERE id=?');
        $st->execute([$customerId]);
        $existing = trim((string)$st->fetchColumn());
        if ($existing !== '') {
            return $existing;
        }
        $chk = $db->prepare('SELECT 1 FROM customers WHERE account_number=?');
        do {
            $acc = 'YD'.str_pad((string)$customerId, 4, '0', STR_PAD_LEFT).str_pad((string)random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
            $chk->execute([$acc]);
        } while ($chk->fetchColumn());
        $db->prepare("UPDATE customers SET account_number=? WHERE id=? AND (account_number IS NULL OR account_number='')")->execute([$acc, $customerId]);
        $st->execute([$customerId]);
        $saved = trim((string)$st->fetchColumn());
        return $saved !== '' ? $saved : $acc;
    }

    public static function screenshotAbsPath(array $t): ?string {
        $rel = trim((string)($t['utr_screenshot_path'] ?? ''));
        if ($rel === '') return null;
        $root = realpath(YEEDIPAY_ROOT) ?: YEEDIPAY_ROOT;
        $abs = realpath($root.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel));
        $dir = realpath($root.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'utr_screenshots');
        if (!$abs || !$dir || !str_starts_with($abs, $dir.DIRECTORY_SEPARATOR) || !is_file($abs)) {
            return null;
        }
        return $abs;
    }

    public static function streamScreenshot(array $t): never {
        $abs = self::screenshotAbsPath($t);
        if (!$abs) {
            http_response_code(404);
            exit('Screenshot not found');
        }
        $mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file($abs);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            $mime = 'application/octet-stream';
        }
        header('Content-Type: '.$mime);
        header('Content-Length: '.(string)filesize($abs));
        header('Cache-Control: private, max-age=3600');
        header('X-Content-Type-Options: nosniff');
        readfile($abs);
        exit;
    }

    /** @return array{0:string,1:string} */
    private static function storeScreenshot(array $file, int $txnId): array {
        $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE || empty($file['tmp_name'])) {
            throw new InvalidArgumentException('Upload a payment screenshot from your UPI app');
        }
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            throw new InvalidArgumentException('Screenshot is too large (max 5 MB)');
        }
        if ($err !== UPLOAD_ERR_OK || !is_uploaded_file((string)$file['tmp_name'])) {
            throw new InvalidArgumentException('Screenshot upload failed. Try again.');
        }
        if ((int)($file['size'] ?? 0) > 5 * 1024 * 1024) {
            throw new InvalidArgumentException('Screenshot is too large (max 5 MB)');
        }
        $mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file((string)$file['tmp_name']);
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? '';
        if ($ext === '') {
            throw new InvalidArgumentException('Screenshot must be a JPG, PNG, or WEBP image');
        }
        $dir = YEEDIPAY_ROOT.'/storage/utr_screenshots';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new InvalidArgumentException('Could not save screenshot. Try again.');
        }
        $name = $txnId.'_'.bin2hex(random_bytes(8)).'.'.$ext;
        $dest = $dir.'/'.$name;
        if (!move_uploaded_file((string)$file['tmp_name'], $dest)) {
            throw new InvalidArgumentException('Could not save screenshot. Try again.');
        }
        $orig = basename((string)($file['name'] ?? 'screenshot.'.$ext));
        $orig = substr(preg_replace('/[^A-Za-z0-9._-]/', '_', $orig) ?? 'screenshot', 0, 180);
        return ['storage/utr_screenshots/'.$name, $orig];
    }

    public static function reportUtr(PDO $db, string $ref, string $utr, array $upload = [], string $email = ''): array {
        self::ensureSchema($db);
        $utr = normalize_utr($utr);
        if (!self::isValidUtr($utr)) {
            return [false, 'Enter a valid UTR (12-digit UPI transaction ID from Google Pay / PhonePe)'];
        }
        $email = strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [false, 'Enter a valid Gmail ID'];
        }
        $t = self::txnByRef($db, $ref);
        if (!$t) {
            return [false, 'Transaction not found'];
        }
        if ($t['status'] !== 'PENDING') {
            return [false, 'This payment is already '.$t['status']];
        }
        if (!empty($t['customer_reported_utr']) && !hash_equals((string)$t['customer_reported_utr'], $utr)) {
            return [false, 'A UTR is already saved. Contact Admin for correction.'];
        }
        $q = $db->prepare('SELECT reference FROM transactions WHERE (customer_reported_utr=? OR rrn=? OR upi_txn_id=?) AND id<>? LIMIT 1');
        $q->execute([$utr, $utr, $utr, $t['id']]);
        if ($q->fetchColumn()) {
            return [false, 'This UTR is already linked to another payment'];
        }
        $shotPath = trim((string)($t['utr_screenshot_path'] ?? ''));
        $shotOrig = trim((string)($t['utr_screenshot_orig'] ?? ''));
        // Screenshot optional for now — SUCCESS is based on valid unique UTR only
        if ($shotPath === '' && !empty($upload['tmp_name']) && (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            try {
                [$shotPath, $shotOrig] = self::storeScreenshot($upload, (int)$t['id']);
            } catch (InvalidArgumentException $e) {
                // Ignore optional screenshot failures
                $shotPath = null;
                $shotOrig = null;
            }
        } else {
            $shotPath = $shotPath !== '' ? $shotPath : null;
            $shotOrig = $shotOrig !== '' ? $shotOrig : null;
        }
        $db->prepare('UPDATE transactions SET customer_reported_utr=?,customer_reported_email=?,customer_reported_at=UTC_TIMESTAMP(),utr_screenshot_path=COALESCE(?,utr_screenshot_path),utr_screenshot_orig=COALESCE(?,utr_screenshot_orig),updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$utr, $email, $shotPath, $shotOrig, $t['id']]);
        $db->prepare("UPDATE customers SET email=? WHERE id=? AND (email IS NULL OR email='')")->execute([$email, (int)$t['customer_id']]);
        $bank = BankService::get($db, (int)$t['bank_id']);
        if (!$bank) {
            return [false, 'Bank not found'];
        }
        [$ok, $msg] = ReconciliationService::apply($db, $bank, [
            'reference' => $t['reference'],
            'status' => 'SUCCESS',
            'amount' => $t['requested_amount'] ?? '',
            'rrn' => $utr,
            'upi_txn_id' => $utr,
        ], 'CUSTOMER_UTR');
        if (!$ok) {
            return [false, $msg];
        }
        $acc = self::assignAccountNumber($db, (int)$t['customer_id']);
        try {
            SmsService::sendPaymentSuccess($db, (int)$t['id']);
        } catch (Throwable $e) {
        }
        return [true, 'Payment successful. Account number '.$acc.' has been assigned. A success SMS was sent to your registered mobile.'];
    }
    public static function query(PDO $db,?int $customerId,string $period,string $status='',?int $bankId=null,string $start='',string $end='',int $limit=1000): array {
        $where=[];$args=[];if($customerId){$where[]='t.customer_id=?';$args[]=$customerId;}if($status!==''){$where[]='t.status=?';$args[]=strtoupper($status);}if($bankId){$where[]='t.bank_id=?';$args[]=$bankId;}[$s,$e]=local_period_bounds($period,$start,$end);if($s){$where[]='t.created_at>=?';$args[]=$s;}if($e){$where[]='t.created_at<?';$args[]=$e;}$sql='SELECT t.*,c.code customer_code,c.name customer_name,c.phone customer_phone,b.bank_name,b.code bank_code FROM transactions t JOIN customers c ON c.id=t.customer_id JOIN bank_profiles b ON b.id=t.bank_id'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY t.id DESC LIMIT '.max(1,min(5000,$limit));$st=$db->prepare($sql);$st->execute($args);return $st->fetchAll();
    }

    public static function search(PDO $db, array $f, int $limit = 2000): array {
        self::ensureSchema($db);
        $where = [];
        $args = [];
        $status = strtoupper(trim((string)($f['status'] ?? '')));
        if ($status !== '') { $where[] = 't.status=?'; $args[] = $status; }
        $bankId = (int)($f['bank_id'] ?? 0);
        if ($bankId > 0) { $where[] = 't.bank_id=?'; $args[] = $bankId; }
        $q = trim((string)($f['q'] ?? ''));
        if ($q !== '') {
            $like = '%'.$q.'%';
            $where[] = '(c.code LIKE ? OR c.name LIKE ? OR c.phone LIKE ? OR IFNULL(c.email,\'\') LIKE ? OR t.reference LIKE ? OR IFNULL(t.rrn,\'\') LIKE ? OR IFNULL(t.customer_reported_utr,\'\') LIKE ?)';
            array_push($args, $like, $like, $like, $like, $like, $like, $like);
        }
        $start = trim((string)($f['start'] ?? ''));
        $end = trim((string)($f['end'] ?? ''));
        if ($start !== '' && $end === '') $end = $start;
        if ($start === '' && $end !== '') $start = $end;
        if ($start !== '' && $end !== '') {
            [$s, $e] = local_period_bounds('custom', $start, $end);
            if ($s) { $where[] = 't.created_at>=?'; $args[] = $s; }
            if ($e) { $where[] = 't.created_at<?'; $args[] = $e; }
        }
        $sql = 'SELECT t.*,c.code customer_code,c.name customer_name,c.phone customer_phone,c.email customer_email,b.bank_name,b.code bank_code FROM transactions t JOIN customers c ON c.id=t.customer_id JOIN bank_profiles b ON b.id=t.bank_id'.($where ? ' WHERE '.implode(' AND ', $where) : '').' ORDER BY t.id DESC LIMIT '.max(1, min(5000, $limit));
        $st = $db->prepare($sql);
        $st->execute($args);
        $rows = $st->fetchAll();
        // Display-only: fold confirmed Razorpay captures (a separate rail, no bank
        // profile) into the list so they appear next to UPI rows. Skipped when a
        // specific bank is filtered, or when the status filter excludes SUCCESS.
        $bankId = (int)($f['bank_id'] ?? 0);
        $status = strtoupper(trim((string)($f['status'] ?? '')));
        if ($bankId <= 0 && ($status === '' || $status === 'SUCCESS')) {
            $rzp = RazorpayService::paidAsTxnRows($db, [
                'q' => $f['q'] ?? '',
                'start' => $f['start'] ?? '',
                'end' => $f['end'] ?? '',
            ], $limit);
            if ($rzp) {
                $rows = array_merge($rows, $rzp);
                usort($rows, static fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
                if (count($rows) > $limit) $rows = array_slice($rows, 0, $limit);
            }
        }
        return $rows;
    }

    public static function displayAmount(array $t): string {
        $amt = $t['confirmed_amount'] ?? null;
        if ($amt === null || $amt === '') $amt = $t['requested_amount'] ?? null;
        return $amt === null || $amt === '' ? '' : amount_fmt($amt);
    }

    /** Customer-entered UTR, or bank SMS / reconciliation UPI ref when present. */
    public static function displayUtr(array $t): string {
        $verified = trim((string)($t['verified_utr'] ?? ''));
        if ($verified !== '') {
            return $verified;
        }
        foreach (['customer_reported_utr', 'upi_txn_id', 'rrn'] as $key) {
            $v = trim((string)($t[$key] ?? ''));
            if ($v !== '') {
                return $v;
            }
        }
        return '';
    }

    public static function displayBuyerName(array $t): string {
        $name = trim((string)($t['buyer_name'] ?? ''));
        if ($name === '') {
            $name = trim((string)($t['customer_name'] ?? ''));
        }
        return $name !== '' ? $name : 'Not Available';
    }

    public static function displayVerifiedUtr(array $t): string {
        if (strtoupper((string)($t['status'] ?? '')) !== 'SUCCESS') {
            return 'Not Available';
        }
        $utr = trim((string)($t['verified_utr'] ?? ''));
        if ($utr === '') {
            $utr = self::displayUtr($t);
        }
        return $utr !== '' ? $utr : 'Not Available';
    }

    /** Persist buyer name and verified UTR on the transaction row. */
    public static function syncIdentityFields(PDO $db, int $txnId): void {
        self::ensureSchema($db);
        $st = $db->prepare('SELECT t.*, c.name customer_name FROM transactions t JOIN customers c ON c.id=t.customer_id WHERE t.id=? LIMIT 1');
        $st->execute([$txnId]);
        $t = $st->fetch();
        if (!$t) {
            return;
        }
        $buyer = trim((string)($t['buyer_name'] ?? ''));
        if ($buyer === '') {
            $mo = $db->prepare('SELECT buyer_name FROM merchant_orders WHERE transaction_id=? LIMIT 1');
            $mo->execute([$txnId]);
            $buyer = trim((string)($mo->fetchColumn() ?: ''));
        }
        if ($buyer === '') {
            $buyer = trim((string)($t['customer_name'] ?? ''));
        }
        $verifiedUtr = null;
        if (strtoupper((string)($t['status'] ?? '')) === 'SUCCESS') {
            $verifiedUtr = trim((string)($t['verified_utr'] ?? ''));
            if ($verifiedUtr === '') {
                foreach (['rrn', 'upi_txn_id', 'customer_reported_utr'] as $key) {
                    $v = trim((string)($t[$key] ?? ''));
                    if ($v !== '') {
                        $verifiedUtr = $v;
                        break;
                    }
                }
            }
            $verifiedUtr = $verifiedUtr !== '' ? $verifiedUtr : null;
        }
        $db->prepare('UPDATE transactions SET buyer_name=COALESCE(NULLIF(?,\'\'), buyer_name), verified_utr=COALESCE(?, verified_utr), updated_at=UTC_TIMESTAMP() WHERE id=?')
            ->execute([$buyer !== '' ? $buyer : null, $verifiedUtr, $txnId]);
    }

    /** @return array{success:float,pending:float,rejected:float} */
    public static function dashboardTotals(array $txns): array {
        $success = 0.0;
        $pending = 0.0;
        $rejected = 0.0;
        foreach ($txns as $t) {
            $st = strtoupper((string)($t['status'] ?? ''));
            if ($st === 'SUCCESS') {
                $success += (float)($t['confirmed_amount'] ?? $t['requested_amount'] ?? 0);
            } elseif ($st === 'PENDING') {
                $pending += (float)($t['requested_amount'] ?? 0);
            } elseif ($st === 'FAILED') {
                $rejected += (float)($t['requested_amount'] ?? 0);
            }
        }
        return ['success' => $success, 'pending' => $pending, 'rejected' => $rejected];
    }

    /** @return array{confirmed:float,pending:float,refunded:float,settled:float,ledger_balance:float} */
    public static function collectionTotals(PDO $db, int $customerId): array {
        self::ensureSchema($db);

        // IMPORTANT ACCOUNTING RULES:
        // 1) PENDING is only a pending collection; it does not change ledger balance.
        // 2) FAILED / EXPIRED are failed attempts, NOT refunds.
        // 3) Only SUCCESS contributes to confirmed collections.
        // 4) A SUCCESS transaction marked SETTLED is debited from customer ledger balance.
        // 5) This build has no dedicated bank-confirmed refund ledger table, therefore refunded=0.
        //    When a real refund ledger is introduced, read only bank-confirmed refund entries there.
        $st = $db->prepare("SELECT
            COALESCE(SUM(CASE WHEN status='SUCCESS' THEN COALESCE(confirmed_amount,requested_amount,0) ELSE 0 END),0) confirmed,
            COALESCE(SUM(CASE WHEN status='PENDING' THEN COALESCE(requested_amount,0) ELSE 0 END),0) pending,
            COALESCE(SUM(CASE WHEN status='SUCCESS' AND COALESCE(settlement_status,'UNSETTLED')='SETTLED' THEN COALESCE(confirmed_amount,requested_amount,0) ELSE 0 END),0) settled
            FROM transactions
            WHERE customer_id=?");
        $st->execute([$customerId]);
        $row = $st->fetch() ?: [];

        // Round monetary totals at the reporting boundary.
        $confirmed = round((float)($row['confirmed'] ?? 0), 2);
        $pending = round((float)($row['pending'] ?? 0), 2);
        $refunded = 0.00;
        $settled = round((float)($row['settled'] ?? 0), 2);
        $ledger = round($confirmed - $settled - $refunded, 2);

        return [
            'confirmed' => $confirmed,
            'pending' => $pending,
            'refunded' => $refunded,
            'settled' => $settled,
            'ledger_balance' => $ledger,
        ];
    }

    public static function scanUri(array $t): string {
        $amt = $t['requested_amount'] ?? null;
        $amount = ($amt !== null && $amt !== '' && (float)$amt > 0) ? (float)number_format((float)$amt, 2, '.', '') : null;
        $intent = trim((string)($t['intent_uri'] ?? ''));
        if ($intent !== '' && str_starts_with(strtolower($intent), 'upi')) {
            try {
                $parsed = BankService::parseUpiUri($intent);
                $intentAm = trim((string)($parsed['params']['am'] ?? ''));
                if ($amount === null || $intentAm === '' || (is_numeric($intentAm) && abs((float)$intentAm - $amount) < 0.009)) {
                    return $intent;
                }
            } catch (Throwable $e) {
            }
        }
        $vpa = trim((string)($t['vpa'] ?? ''));
        if ($vpa !== '' && $amount !== null) {
            return BankService::customerAmountIntent([
                'vpa' => $vpa,
                'payee_name' => (string)($t['payee_name'] ?? ''),
                'bank_name' => (string)($t['bank_name'] ?? ''),
                'mcc' => (string)($t['mcc'] ?? ''),
                'qr_payload' => (string)($t['qr_payload'] ?? ''),
            ], $amount, (string)($t['reference'] ?? ''));
        }
        return $intent;
    }

    public static function usesUploadedBankQr(array $t): bool {
        return false;
    }

    public static function bankQrUrl(array $t): string {
        return url('/banks/'.(int)($t['bank_id'] ?? 0).'/qr');
    }
}
