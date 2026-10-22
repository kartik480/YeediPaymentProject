<?php
declare(strict_types=1);

final class SuperAdminService {
    /** @return array{today_attempts:int,today_pending:float,today_success:float,ledger_balance:float,operating_mode:string} */
    public static function dashboardStats(PDO $db): array {
        TransactionService::ensureSchema($db);
        [$start, $end] = local_period_bounds('day');
        $todayAttempts = 0;
        $todayPending = 0.0;
        $todaySuccess = 0.0;
        if ($start && $end) {
            $st = $db->prepare("SELECT
                COUNT(*) attempts,
                COALESCE(SUM(CASE WHEN status='PENDING' THEN COALESCE(requested_amount,0) ELSE 0 END),0) pending_amt,
                COALESCE(SUM(CASE WHEN status='SUCCESS' THEN COALESCE(confirmed_amount,requested_amount,0) ELSE 0 END),0) success_amt
                FROM transactions WHERE created_at>=? AND created_at<?");
            $st->execute([$start, $end]);
            $row = $st->fetch() ?: [];
            $todayAttempts = (int)($row['attempts'] ?? 0);
            $todayPending = (float)($row['pending_amt'] ?? 0);
            $todaySuccess = (float)($row['success_amt'] ?? 0);
        }
        $ledgerStats = self::customerAccountStats($db);
        $ledger = (float)($ledgerStats['ledger_balance'] ?? 0);
        $bank = BankService::active($db);
        $bankName = trim((string)($bank['bank_name'] ?? ''));
        if ($bankName === '') $bankName = 'No active bank';
        $mode = $bankName.' / I HAVE PAID + Android Bank SMS Relay + exact amount/account/UTR match';
        return [
            'today_attempts' => $todayAttempts,
            'today_pending' => $todayPending,
            'today_success' => $todaySuccess,
            'ledger_balance' => $ledger,
            'operating_mode' => $mode,
        ];
    }

    /** @return list<array<string,mixed>> */
    public static function customerAccounts(PDO $db): array {
        TransactionService::ensureSchema($db);
        return $db->query("SELECT c.*,
            COALESCE(SUM(CASE WHEN t.status='SUCCESS' THEN COALESCE(t.confirmed_amount,t.requested_amount,0) ELSE 0 END),0) confirmed_amount_total,
            COALESCE(SUM(CASE WHEN t.status='PENDING' THEN COALESCE(t.requested_amount,0) ELSE 0 END),0) pending_amount_total,
            COALESCE(SUM(CASE WHEN t.status='SUCCESS' AND COALESCE(t.settlement_status,'UNSETTLED')='SETTLED' THEN COALESCE(t.confirmed_amount,t.requested_amount,0) ELSE 0 END),0) settled_amount_total,
            ROUND(
                COALESCE(SUM(CASE WHEN t.status='SUCCESS' THEN COALESCE(t.confirmed_amount,t.requested_amount,0) ELSE 0 END),0)
                - COALESCE(SUM(CASE WHEN t.status='SUCCESS' AND COALESCE(t.settlement_status,'UNSETTLED')='SETTLED' THEN COALESCE(t.confirmed_amount,t.requested_amount,0) ELSE 0 END),0)
            ,2) ledger_balance,
            COUNT(t.id) txn_count
            FROM customers c
            LEFT JOIN transactions t ON t.customer_id=c.id
            GROUP BY c.id
            ORDER BY c.id DESC
            LIMIT 500")->fetchAll();
    }

    /** @return array{confirmed:float,pending:float,refunded:float,settled:float,ledger_balance:float} */
    public static function customerAccountStats(PDO $db): array {
        TransactionService::ensureSchema($db);
        SettlementService::ensureSchema($db);
        $row = $db->query("SELECT
            COALESCE(SUM(CASE WHEN status='SUCCESS' THEN COALESCE(confirmed_amount,requested_amount,0) ELSE 0 END),0) confirmed,
            COALESCE(SUM(CASE WHEN status='PENDING' THEN COALESCE(requested_amount,0) ELSE 0 END),0) pending,
            COALESCE(SUM(CASE WHEN status='SUCCESS' AND COALESCE(settlement_status,'UNSETTLED')='SETTLED' THEN COALESCE(confirmed_amount,requested_amount,0) ELSE 0 END),0) settled
            FROM transactions")->fetch() ?: [];

        $confirmed = round((float)($row['confirmed'] ?? 0), 2);
        $pending = round((float)($row['pending'] ?? 0), 2);
        $refunded = 0.00; // FAILED/EXPIRED are not refunds.
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

    /** @return array{status:string,period:string,ref:string} */
    public static function transactionFiltersFromGet(): array {
        return [
            'status' => strtoupper(trim((string)($_GET['status'] ?? ''))),
            'period' => strtolower(trim((string)($_GET['period'] ?? 'all'))),
            'ref' => trim((string)($_GET['ref'] ?? '')),
        ];
    }

    /** @param array{status?:string,period?:string,ref?:string} $f @return list<array<string,mixed>> */
    public static function transactions(PDO $db, array $f): array {
        TransactionService::ensureSchema($db);
        $status = strtoupper(trim((string)($f['status'] ?? '')));
        $period = strtolower(trim((string)($f['period'] ?? 'all')));
        $ref = trim((string)($f['ref'] ?? ''));
        $search = ['status' => $status, 'q' => $ref, 'bank_id' => ''];
        if ($period !== '' && $period !== 'all') {
            $tz = new DateTimeZone(Env::get('APP_TIMEZONE', 'Asia/Kolkata'));
            $now = new DateTimeImmutable('now', $tz);
            if ($period === 'day') {
                $search['start'] = $search['end'] = $now->format('Y-m-d');
            } elseif ($period === 'week') {
                $search['start'] = $now->modify('monday this week')->format('Y-m-d');
                $search['end'] = $now->format('Y-m-d');
            } elseif ($period === 'month') {
                $search['start'] = $now->modify('first day of this month')->format('Y-m-d');
                $search['end'] = $now->format('Y-m-d');
            }
        }
        return TransactionService::search($db, $search, 500);
    }

    public static function ensureCustomerSchema(PDO $db): void {
        TransactionService::ensureSchema($db);
        TenantService::ensureSchema($db);
        $cust = [];
        foreach ($db->query('SHOW COLUMNS FROM customers') as $r) {
            $cust[strtolower((string)$r['Field'])] = true;
        }
        if (!isset($cust['min_txn_limit'])) {
            $db->exec('ALTER TABLE customers ADD COLUMN min_txn_limit DECIMAL(14,2) NULL');
        }
        if (!isset($cust['service_callback_url'])) {
            $db->exec('ALTER TABLE customers ADD COLUMN service_callback_url VARCHAR(255) NULL');
        }
    }

    /** @return list<array<string,mixed>> */
    public static function customers(PDO $db): array {
        self::ensureCustomerSchema($db);
        return $db->query('SELECT code,name,email,active,created_at FROM customers ORDER BY id DESC LIMIT 500')->fetchAll();
    }

    /** @param array<string,mixed> $in @return array{code:string} */
    public static function createCustomer(PDO $db, array $in): array {
        self::ensureCustomerSchema($db);
        $name = trim((string)($in['name'] ?? ''));
        if ($name === '') throw new InvalidArgumentException('Customer name is required');
        $email = strtolower(trim((string)($in['email'] ?? '')));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Enter a valid email address');
        }
        $pass = (string)($in['password'] ?? '');
        if (strlen($pass) < 10) throw new InvalidArgumentException('Login password must be at least 10 characters');
        $code = normalize_code((string)($in['code'] ?? '')) ?: TransactionService::newCustomerCode($db);
        if (TransactionService::customerByCode($db, $code)) {
            throw new InvalidArgumentException('Customer ID / Ref already exists');
        }
        $minTxn = money_or_null($in['min_txn'] ?? '101') ?? '101.00';
        $maxTxn = money_or_null($in['max_txn'] ?? '200000');
        if ($maxTxn !== null && (float)$maxTxn < (float)$minTxn) {
            throw new InvalidArgumentException('Max Txn must be greater than or equal to Min Txn');
        }
        $callback = trim((string)($in['callback_url'] ?? ''));
        if ($callback !== '' && !preg_match('#^https?://#i', $callback)) {
            throw new InvalidArgumentException('Service Callback URL must start with http:// or https://');
        }
        if (strlen($callback) > 255) $callback = substr($callback, 0, 255);
        $phone = '9'.str_pad((string)random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
        $st = $db->prepare('INSERT INTO customers(code,name,phone,email,active,min_txn_limit,per_txn_limit,daily_limit,weekly_limit,monthly_limit,password_hash,service_callback_url,max_pending_attempts,created_at)
            VALUES(?,?,?,?,1,?,?,?,?,?,?,?,?,UTC_TIMESTAMP())');
        $st->execute([
            $code,
            $name,
            $phone,
            $email !== '' ? $email : null,
            $minTxn,
            $maxTxn,
            money_or_null($in['daily_limit'] ?? '0'),
            money_or_null($in['weekly_limit'] ?? '0'),
            money_or_null($in['monthly_limit'] ?? '0'),
            password_hash($pass, PASSWORD_DEFAULT),
            $callback !== '' ? $callback : null,
            5,
        ]);
        TransactionService::assignAccountNumber($db, (int)$db->lastInsertId());
        return ['code' => $code];
    }

    /** @return list<array<string,mixed>> */
    public static function banks(PDO $db): array {
        BankService::ensureSchema($db);
        return BankService::all($db);
    }

    public static function bankVerificationMode(array $bank): string {
        $label = strtoupper(trim((string)($bank['label'] ?? '')));
        if ($label === 'API') return 'API';
        $hasApi = trim((string)($bank['provider_base_url'] ?? '')) !== ''
            || trim((string)($bank['status_path'] ?? '')) !== ''
            || trim((string)($bank['create_order_path'] ?? '')) !== '';
        return $hasApi ? 'API' : 'MANUAL';
    }

    /** @param array<string,mixed> $in @return array{id:int,code:string} */
    public static function addBankRoute(PDO $db, array $in, array $files = []): array {
        BankService::ensureSchema($db);
        $name = trim((string)($in['bank_name'] ?? ''));
        if ($name === '') throw new InvalidArgumentException('Bank Name is required');
        $vpa = trim((string)($in['vpa'] ?? ''));
        $payee = trim((string)($in['payee_name'] ?? ''));
        $upiUri = trim((string)($in['upi_uri'] ?? ''));
        if ($upiUri === '' && ($vpa === '' || $payee === '')) {
            throw new InvalidArgumentException('UPI VPA and Payee Name are required (or upload a static bank QR)');
        }
        $mode = strtoupper(trim((string)($in['verification_mode'] ?? 'MANUAL')));
        if (!in_array($mode, ['MANUAL', 'API'], true)) $mode = 'MANUAL';
        $input = [
            'code' => trim((string)($in['code'] ?? '')),
            'bank_name' => $name,
            'vpa' => $vpa,
            'payee_name' => $payee,
            'upi_uri' => $upiUri,
            'rotation_order' => max(1, (int)($in['priority'] ?? 100)),
            'rotation_minutes' => 60,
            'enabled' => 1,
            'webhook_secret' => trim((string)($in['webhook_secret'] ?? '')),
            'label' => $mode,
        ];
        $id = BankService::add($db, $input);
        $bank = BankService::get($db, $id);
        if (!$bank) throw new RuntimeException('Bank route could not be saved');
        $qrPath = BankService::saveQrImage($files['qr_image'] ?? [], (string)$bank['code']);
        if ($qrPath !== null) {
            $db->prepare('UPDATE bank_profiles SET qr_image_path=?, signed_qr=1 WHERE id=?')->execute([$qrPath, $id]);
        }
        return ['id' => $id, 'code' => (string)$bank['code']];
    }

    public static function streamBankQr(PDO $db, int $id): never {
        BankService::streamQrImage($db, $id);
    }

    /** @param array<string,mixed> $file */
    private static function saveBankQrImage(array $file, string $code): ?string {
        return BankService::saveQrImage($file, $code);
    }

    public static function ensureAdminUserSchema(PDO $db): void {
        $cols = [];
        foreach ($db->query('SHOW COLUMNS FROM admin_users') as $r) {
            $cols[strtolower((string)$r['Field'])] = true;
        }
        if (!isset($cols['name'])) {
            $db->exec('ALTER TABLE admin_users ADD COLUMN name VARCHAR(180) NULL');
        }
        if (!isset($cols['email'])) {
            $db->exec('ALTER TABLE admin_users ADD COLUMN email VARCHAR(180) NULL');
        }
        if (!isset($cols['role'])) {
            $db->exec("ALTER TABLE admin_users ADD COLUMN role VARCHAR(32) NOT NULL DEFAULT 'CHECKER'");
        }
        $idx = $db->query("SHOW INDEX FROM admin_users WHERE Key_name='uq_admin_user_email'")->fetch();
        if (!$idx) {
            try { $db->exec('ALTER TABLE admin_users ADD UNIQUE KEY uq_admin_user_email(email)'); } catch (Throwable $e) {}
        }
        $db->exec("UPDATE admin_users SET name='Administrator', email='admin@yeedipay.local', role='SUPERADMIN' WHERE username='admin' AND (email IS NULL OR email='')");
        $db->exec("UPDATE admin_users SET role='SUPERADMIN' WHERE username='admin' AND (role IS NULL OR role='' OR role='CHECKER')");
        $db->exec("UPDATE admin_users SET name=username WHERE name IS NULL OR name=''");
        $db->exec("UPDATE admin_users SET email=CONCAT(username,'@yeedipay.local') WHERE (email IS NULL OR email='') AND username NOT LIKE '%@%'");
    }

    /** @return list<string> */
    public static function adminUserRoles(): array {
        return ['CHECKER', 'MAKER', 'VIEWER', 'SUPERADMIN'];
    }

    /** @return list<array<string,mixed>> */
    public static function adminUsers(PDO $db): array {
        self::ensureAdminUserSchema($db);
        return $db->query('SELECT id,username,name,email,role,active,created_at FROM admin_users ORDER BY id ASC')->fetchAll();
    }

    /** @param array<string,mixed> $in @return array{id:int,email:string} */
    public static function createAdminUser(PDO $db, array $in): array {
        self::ensureAdminUserSchema($db);
        $name = trim((string)($in['name'] ?? ''));
        if ($name === '') throw new InvalidArgumentException('Name is required');
        $email = strtolower(trim((string)($in['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid email is required');
        }
        $pass = (string)($in['password'] ?? '');
        if (strlen($pass) < 10) throw new InvalidArgumentException('Password must be at least 10 characters');
        $role = strtoupper(trim((string)($in['role'] ?? 'CHECKER')));
        if (!in_array($role, self::adminUserRoles(), true)) {
            throw new InvalidArgumentException('Invalid role selected');
        }
        $st = $db->prepare('SELECT id FROM admin_users WHERE username=? OR email=? LIMIT 1');
        $st->execute([$email, $email]);
        if ($st->fetch()) throw new InvalidArgumentException('An admin user with this email already exists');
        $st = $db->prepare('INSERT INTO admin_users(username,name,email,role,password_hash,active,created_at,updated_at) VALUES(?,?,?,?,?,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
        $st->execute([$email, $name, $email, $role, password_hash($pass, PASSWORD_DEFAULT)]);
        return ['id' => (int)$db->lastInsertId(), 'email' => $email];
    }

    public static function adminUserDisplayEmail(array $user): string {
        $email = trim((string)($user['email'] ?? ''));
        if ($email !== '') return $email;
        $username = trim((string)($user['username'] ?? ''));
        if ($username !== '' && str_contains($username, '@')) return $username;
        return $username !== '' ? $username.'@yeedipay.local' : '—';
    }

    public static function adminUserDisplayName(array $user): string {
        $name = trim((string)($user['name'] ?? ''));
        if ($name !== '') return $name;
        $username = trim((string)($user['username'] ?? ''));
        return $username !== '' ? ucfirst($username) : '—';
    }

    public static function adminUserDisplayRole(array $user): string {
        $role = strtoupper(trim((string)($user['role'] ?? '')));
        return $role !== '' ? $role : 'CHECKER';
    }
}
