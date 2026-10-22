<?php
declare(strict_types=1);

final class TenantService {
    public const PLANS = [
        'starter' => 'Starter ₹2,999.00',
        'business' => 'Business ₹7,499.00',
        'pro' => 'Pro ₹14,999.00',
    ];

    public static function ensureSchema(PDO $db): void {
        TransactionService::ensureSchema($db);
        $cust = [];
        foreach ($db->query('SHOW COLUMNS FROM customers') as $r) {
            $cust[strtolower((string)$r['Field'])] = true;
        }
        if (!isset($cust['legal_name'])) $db->exec('ALTER TABLE customers ADD COLUMN legal_name VARCHAR(180) NULL');
        if (!isset($cust['pan'])) $db->exec('ALTER TABLE customers ADD COLUMN pan VARCHAR(20) NULL');
        if (!isset($cust['gst'])) $db->exec('ALTER TABLE customers ADD COLUMN gst VARCHAR(32) NULL');
        if (!isset($cust['plan'])) $db->exec("ALTER TABLE customers ADD COLUMN plan VARCHAR(32) NULL DEFAULT 'starter'");
        if (!isset($cust['subscription_ends'])) $db->exec('ALTER TABLE customers ADD COLUMN subscription_ends DATETIME NULL');
        if (!isset($cust['admin_name'])) $db->exec('ALTER TABLE customers ADD COLUMN admin_name VARCHAR(180) NULL');
        if (!isset($cust['password_hash'])) $db->exec('ALTER TABLE customers ADD COLUMN password_hash VARCHAR(255) NULL');
    }

    public static function planLabel(?string $plan): string {
        $key = strtolower(trim((string)$plan));
        if ($key === 'growth') $key = 'business';
        if ($key === 'scale') $key = 'pro';
        return self::PLANS[$key] ?? ($key !== '' ? $plan : '—');
    }

    public static function parseSubscriptionEnds(string $raw): ?string {
        $raw = trim($raw);
        if ($raw === '') return null;
        $raw = str_replace('T', ' ', $raw);
        try {
            $dt = new DateTimeImmutable($raw);
            return $dt->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            throw new InvalidArgumentException('Invalid subscription end date');
        }
    }

    public static function create(PDO $db, array $in): string {
        self::ensureSchema($db);
        $brand = trim((string)($in['brand_name'] ?? ''));
        if ($brand === '') throw new RuntimeException('Brand name is required');
        $email = strtolower(trim((string)($in['admin_email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('A valid admin email is required');
        $pass = (string)($in['admin_password'] ?? '');
        if (strlen($pass) < 8) throw new RuntimeException('Admin password must be at least 8 characters');
        $plan = strtolower(trim((string)($in['plan'] ?? 'starter')));
        if (!isset(self::PLANS[$plan])) $plan = 'starter';
        $code = TransactionService::newCustomerCode($db);
        $phone = preg_replace('/\D+/', '', (string)($in['phone'] ?? '')) ?: '0000000000';
        $sql = 'INSERT INTO customers(code,name,legal_name,phone,email,active,pan,gst,plan,per_txn_limit,daily_limit,subscription_ends,admin_name,password_hash,max_pending_attempts,created_at)
                VALUES(?,?,?,?,?,1,?,?,?,?,?,?,?,?,5,UTC_TIMESTAMP())';
        $db->prepare($sql)->execute([
            $code,
            $brand,
            trim((string)($in['legal_name'] ?? '')) ?: null,
            $phone,
            $email,
            strtoupper(trim((string)($in['pan'] ?? ''))) ?: null,
            strtoupper(trim((string)($in['gst'] ?? ''))) ?: null,
            $plan,
            money_or_null($in['per_txn_limit'] ?? '200000') ?? '200000.00',
            money_or_null($in['daily_limit'] ?? '1000000') ?? '1000000.00',
            self::parseSubscriptionEnds((string)($in['subscription_ends'] ?? '')),
            trim((string)($in['admin_name'] ?? '')) ?: null,
            password_hash($pass, PASSWORD_DEFAULT),
        ]);
        return $code;
    }
}
