<?php
declare(strict_types=1);

final class PricingService {
    public const FIELDS = [
        'monthly_fee' => ['label' => 'Monthly Fee', 'step' => '0.01'],
        'included_payouts' => ['label' => 'Included Payouts', 'step' => '1'],
        'extra_payout_fee' => ['label' => 'Extra Payout Fee', 'step' => '0.01'],
        'included_banks' => ['label' => 'Included Banks', 'step' => '1'],
        'extra_bank_month' => ['label' => 'Extra Bank / Month', 'step' => '0.01'],
        'custom_format_setup' => ['label' => 'Custom Format Setup', 'step' => '0.01'],
        'h2h_month' => ['label' => 'H2H / Month', 'step' => '0.01'],
        'api_month' => ['label' => 'API / Month', 'step' => '0.01'],
        'white_label_month' => ['label' => 'White Label / Month', 'step' => '0.01'],
    ];

    public const CHARGES = [
        'BASE_SUBSCRIPTION' => 'BASE_SUBSCRIPTION',
        'EXTRA_PAYOUT' => 'EXTRA_PAYOUT',
        'EXTRA_BANK' => 'EXTRA_BANK',
        'CUSTOM_FORMAT_SETUP' => 'CUSTOM_FORMAT_SETUP',
        'H2H_CONNECTOR' => 'H2H_CONNECTOR',
        'API_CONNECTOR' => 'API_CONNECTOR',
        'WHITE_LABEL' => 'WHITE_LABEL',
    ];

    public const DEFAULTS = [
        'starter' => [
            'monthly_fee' => '2999.00',
            'included_payouts' => '1000',
            'extra_payout_fee' => '3.00',
            'included_banks' => '1',
            'extra_bank_month' => '500.00',
            'custom_format_setup' => '2500.00',
            'h2h_month' => '5000.00',
            'api_month' => '5000.00',
            'white_label_month' => '10000.00',
        ],
        'business' => [
            'monthly_fee' => '7499.00',
            'included_payouts' => '5000',
            'extra_payout_fee' => '2.00',
            'included_banks' => '2',
            'extra_bank_month' => '500.00',
            'custom_format_setup' => '2500.00',
            'h2h_month' => '5000.00',
            'api_month' => '5000.00',
            'white_label_month' => '10000.00',
        ],
        'pro' => [
            'monthly_fee' => '14999.00',
            'included_payouts' => '15000',
            'extra_payout_fee' => '1.50',
            'included_banks' => '3',
            'extra_bank_month' => '500.00',
            'custom_format_setup' => '2500.00',
            'h2h_month' => '5000.00',
            'api_month' => '5000.00',
            'white_label_month' => '10000.00',
        ],
    ];

    public static function ensureSchema(PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS pricing_plans (
            plan_key VARCHAR(32) NOT NULL PRIMARY KEY,
            monthly_fee DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            included_payouts INT NOT NULL DEFAULT 0,
            extra_payout_fee DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            included_banks INT NOT NULL DEFAULT 0,
            extra_bank_month DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            custom_format_setup DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            h2h_month DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            api_month DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            white_label_month DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE IF NOT EXISTS pricing_overrides (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            customer_id BIGINT UNSIGNED NOT NULL,
            charge_key VARCHAR(64) NOT NULL,
            unit_price DECIMAL(14,2) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_price_override(customer_id, charge_key),
            INDEX idx_price_override_cust(customer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $st = $db->prepare('INSERT IGNORE INTO pricing_plans(plan_key,monthly_fee,included_payouts,extra_payout_fee,included_banks,extra_bank_month,custom_format_setup,h2h_month,api_month,white_label_month) VALUES(?,?,?,?,?,?,?,?,?,?)');
        foreach (self::DEFAULTS as $key => $row) {
            $st->execute([
                $key,
                $row['monthly_fee'],
                $row['included_payouts'],
                $row['extra_payout_fee'],
                $row['included_banks'],
                $row['extra_bank_month'],
                $row['custom_format_setup'],
                $row['h2h_month'],
                $row['api_month'],
                $row['white_label_month'],
            ]);
        }
    }

    public static function plans(PDO $db): array {
        self::ensureSchema($db);
        $rows = $db->query('SELECT * FROM pricing_plans')->fetchAll();
        $out = [];
        foreach ($rows as $r) $out[(string)$r['plan_key']] = $r;
        $ordered = [];
        foreach (array_keys(self::DEFAULTS) as $key) {
            $ordered[$key] = $out[$key] ?? (['plan_key' => $key] + self::DEFAULTS[$key]);
        }
        return $ordered;
    }

    public static function savePlan(PDO $db, string $planKey, array $in): void {
        self::ensureSchema($db);
        $planKey = strtolower(trim($planKey));
        if (!isset(self::DEFAULTS[$planKey])) throw new RuntimeException('Unknown plan');
        $d = self::DEFAULTS[$planKey];
        $vals = [];
        foreach (self::FIELDS as $field => $meta) {
            $vals[$field] = self::num($in[$field] ?? '', $d[$field], $meta['step'] === '1');
        }
        $sql = 'UPDATE pricing_plans SET monthly_fee=?,included_payouts=?,extra_payout_fee=?,included_banks=?,extra_bank_month=?,custom_format_setup=?,h2h_month=?,api_month=?,white_label_month=?,updated_at=UTC_TIMESTAMP() WHERE plan_key=?';
        $db->prepare($sql)->execute([
            $vals['monthly_fee'],
            $vals['included_payouts'],
            $vals['extra_payout_fee'],
            $vals['included_banks'],
            $vals['extra_bank_month'],
            $vals['custom_format_setup'],
            $vals['h2h_month'],
            $vals['api_month'],
            $vals['white_label_month'],
            $planKey,
        ]);
    }

    public static function overrides(PDO $db): array {
        self::ensureSchema($db);
        return $db->query('SELECT o.*,c.code customer_code,c.name customer_name FROM pricing_overrides o JOIN customers c ON c.id=o.customer_id ORDER BY o.id DESC LIMIT 500')->fetchAll();
    }

    public static function saveOverride(PDO $db, array $in): void {
        self::ensureSchema($db);
        $code = normalize_code((string)($in['tenant'] ?? ''));
        $c = TransactionService::customerByCode($db, $code);
        if (!$c) throw new RuntimeException('Select a tenant');
        $charge = strtoupper(trim((string)($in['charge'] ?? '')));
        if ($charge === 'H2H') $charge = 'H2H_CONNECTOR';
        if ($charge === 'API') $charge = 'API_CONNECTOR';
        if (!isset(self::CHARGES[$charge])) throw new RuntimeException('Select a charge');
        $price = self::num($in['unit_price'] ?? '', '', false);
        $st = $db->prepare('INSERT INTO pricing_overrides(customer_id,charge_key,unit_price,created_at,updated_at) VALUES(?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE unit_price=VALUES(unit_price),updated_at=UTC_TIMESTAMP()');
        $st->execute([(int)$c['id'], $charge, $price]);
    }

    private static function num(mixed $v, string $fallback, bool $int): string {
        $s = trim((string)$v);
        if ($s === '') {
            if ($fallback === '') throw new RuntimeException('Unit price is required');
            $s = $fallback;
        }
        if ($int) {
            if (!preg_match('/^\d+$/', $s)) throw new InvalidArgumentException('Invalid number');
            return (string)(int)$s;
        }
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $s)) throw new InvalidArgumentException('Invalid amount');
        return number_format((float)$s, 2, '.', '');
    }
}
