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
        $idx = $db->query("SHOW INDEX FROM customers WHERE Key_name='uq_customer_account'")->fetch();
        if (!$idx) {
            try { $db->exec('ALTER TABLE customers ADD UNIQUE KEY uq_customer_account(account_number)'); } catch (Throwable $e) {}
        }
        $cols = [];
        foreach ($db->query('SHOW COLUMNS FROM transactions') as $r) $cols[strtolower((string)$r['Field'])] = true;
        if (!isset($cols['service'])) $db->exec("ALTER TABLE transactions ADD COLUMN service VARCHAR(64) NOT NULL DEFAULT 'UPI'");
        if (!isset($cols['settlement_status'])) $db->exec("ALTER TABLE transactions ADD COLUMN settlement_status VARCHAR(32) NOT NULL DEFAULT 'UNSETTLED'");
        if (!isset($cols['invoice_ref'])) $db->exec('ALTER TABLE transactions ADD COLUMN invoice_ref VARCHAR(160) NULL');
        if (!isset($cols['customer_paid_ack_at'])) $db->exec('ALTER TABLE transactions ADD COLUMN customer_paid_ack_at DATETIME NULL');
        $db->exec("UPDATE transactions SET service='UPI' WHERE service IS NULL OR service=''");
        $db->exec("UPDATE transactions SET settlement_status='N/A' WHERE status IN ('FAILED','EXPIRED') AND settlement_status='UNSETTLED'");
        BankSmsService::ensureSchema($db);
        self::$schemaReady = true;
    }

    public static function customerByCode(PDO $db,string $code): ?array { $st=$db->prepare('SELECT * FROM customers WHERE code=? LIMIT 1');$st->execute([strtoupper($code)]);$r=$st->fetch();return $r?:null; }
    public static function txnByRef(PDO $db,string $ref): ?array { $st=$db->prepare('SELECT t.*,c.code customer_code,c.name customer_name,c.phone customer_phone,b.bank_name,b.payee_name,b.vpa,b.qr_payload,b.qr_image_path,b.signed_qr FROM transactions t JOIN customers c ON c.id=t.customer_id JOIN bank_profiles b ON b.id=t.bank_id WHERE t.reference=?');$st->execute([$ref]);$r=$st->fetch();return $r?:null; }
    public static function newCustomerCode(PDO $db): string { $n=(int)$db->query('SELECT COUNT(*) FROM customers')->fetchColumn()+1;while(true){$code='CUST'.str_pad((string)$n,4,'0',STR_PAD_LEFT);$st=$db->prepare('SELECT 1 FROM customers WHERE code=?');$st->execute([$code]);if(!$st->fetchColumn())return $code;$n++;} }
    public const PRESET_AMOUNTS = [1, 101, 1000, 2000, 5000, 10000, 20000, 50000, 100000];
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
        $st = $db->prepare("SELECT t.*,c.code customer_code,c.name customer_name,b.bank_name,b.payee_name,b.vpa,b.qr_payload,b.qr_image_path,b.signed_qr FROM transactions t JOIN customers c ON c.id=t.customer_id JOIN bank_profiles b ON b.id=t.bank_id WHERE t.customer_id=? AND t.status='PENDING' ORDER BY t.id DESC LIMIT 1");
        $st->execute([$customerId]);
        $r = $st->fetch();
        return $r ?: null;
    }

    public static function createAttempt(PDO $db,array $c,?float $amount, ?string $invoiceRef = null, string $service = 'UPI', string $settlementStatus = 'UNSETTLED'): array {
        self::ensureSchema($db);
        $bank=BankService::active($db);if(!$bank)throw new RuntimeException('No active bank is configured.');[$ok,$msg]=LimitService::check($db,$c,$amount);if(!$ok)throw new RuntimeException($msg);$ref=random_reference($c['code'],$bank['code']);$intent=PaymentConfirmService::intentForAttempt($bank,$amount,$ref);
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
        $svc = strtoupper(trim($service)) ?: 'UPI';
        $settle = strtoupper(trim($settlementStatus)) ?: 'UNSETTLED';
        $st=$db->prepare("INSERT INTO transactions(customer_id,bank_id,reference,status,requested_amount,intent_uri,limit_breach,limit_note,service,settlement_status,invoice_ref,created_at,updated_at) VALUES(?,?,?,'PENDING',?,?,0,'',?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
        $st->execute([$c['id'],$bank['id'],$ref,$amt,$intent,$svc,$settle,$invoice!==''?$invoice:null]);
        $id=(int)$db->lastInsertId();
        SmsService::enqueueTxn($db,$id,'PENDING');
        return ['id'=>$id,'reference'=>$ref,'bank'=>$bank,'intent'=>$intent];
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
        }

        // The button itself triggers the server-side amount check. If the bank SMS
        // reached the server first, match it now; if not, the later SMS ingress will
        // match only this acknowledged payment.
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

    /** @return array{state:string,acknowledged:bool,remaining:int,message:string} */
    public static function verificationState(array $t): array {
        $status = strtoupper((string)($t['status'] ?? ''));
        if ($status === 'SUCCESS') return ['state'=>'CONFIRMED','acknowledged'=>true,'remaining'=>0,'message'=>'Payment confirmed successfully.'];
        if (in_array($status, ['FAILED','EXPIRED'], true)) return ['state'=>'EXPIRED','acknowledged'=>!empty($t['customer_paid_ack_at']),'remaining'=>0,'message'=>'Payment window is closed.'];
        if (strtoupper((string)($t['sms_match'] ?? '')) === 'REVIEW_REQUIRED') return ['state'=>'REVIEW_REQUIRED','acknowledged'=>!empty($t['customer_paid_ack_at']),'remaining'=>0,'message'=>'Payment needs manual verification. Do not pay again.'];
        $ack = trim((string)($t['customer_paid_ack_at'] ?? ''));
        if ($ack === '') return ['state'=>'AWAITING_PAYMENT','acknowledged'=>false,'remaining'=>0,'message'=>'Complete payment and tap I HAVE PAID.'];
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
            $where[] = '(c.code LIKE ? OR c.name LIKE ? OR c.phone LIKE ? OR IFNULL(c.email,\'\') LIKE ? OR t.reference LIKE ? OR IFNULL(t.rrn,\'\') LIKE ?)';
            array_push($args, $like, $like, $like, $like, $like, $like);
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
        return $st->fetchAll();
    }

    public static function displayAmount(array $t): string {
        $amt = $t['confirmed_amount'] ?? null;
        if ($amt === null || $amt === '') $amt = $t['requested_amount'] ?? null;
        return $amt === null || $amt === '' ? '' : amount_fmt($amt);
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
        $st = $db->prepare("SELECT
            COALESCE(SUM(CASE WHEN status='SUCCESS' THEN COALESCE(confirmed_amount,requested_amount,0) ELSE 0 END),0) confirmed,
            COALESCE(SUM(CASE WHEN status='PENDING' THEN COALESCE(requested_amount,0) ELSE 0 END),0) pending,
            COALESCE(SUM(CASE WHEN status='FAILED' THEN COALESCE(requested_amount,confirmed_amount,0) ELSE 0 END),0) refunded,
            COALESCE(SUM(CASE WHEN status='SUCCESS' AND COALESCE(settlement_status,'UNSETTLED')='SETTLED' THEN COALESCE(confirmed_amount,requested_amount,0) ELSE 0 END),0) settled
            FROM transactions WHERE customer_id=?");
        $st->execute([$customerId]);
        $row = $st->fetch() ?: [];
        $confirmed = (float)($row['confirmed'] ?? 0);
        $pending = (float)($row['pending'] ?? 0);
        $refunded = (float)($row['refunded'] ?? 0);
        $settled = (float)($row['settled'] ?? 0);
        $ledger = max(0, round($confirmed - $settled - $refunded, 2));
        return [
            'confirmed' => $confirmed,
            'pending' => $pending,
            'refunded' => $refunded,
            'settled' => $settled,
            'ledger_balance' => $ledger,
        ];
    }

    public static function scanUri(array $t): string {
        // The exact intent used when the transaction was created is authoritative.
        // This preserves bank/provider dynamic order URLs and signed payloads.
        $intent = trim((string)($t['intent_uri'] ?? ''));
        if ($intent !== '') return $intent;
        // Compatibility fallback for legacy rows that pre-date intent_uri storage.
        $amt = $t['requested_amount'] ?? null;
        $amount = ($amt !== null && $amt !== '' && (float)$amt > 0) ? (float)$amt : null;
        return BankService::customerAmountIntent([
            'vpa' => (string)($t['vpa'] ?? ''),
            'payee_name' => (string)($t['payee_name'] ?? ''),
            'bank_name' => (string)($t['bank_name'] ?? ''),
            'mcc' => (string)($t['mcc'] ?? ''),
            'qr_payload' => (string)($t['qr_payload'] ?? ''),
        ], $amount, (string)($t['reference'] ?? ''));
    }

    public static function usesUploadedBankQr(array $t): bool {
        return false;
    }

    public static function bankQrUrl(array $t): string {
        return url('/banks/'.(int)($t['bank_id'] ?? 0).'/qr');
    }
}
