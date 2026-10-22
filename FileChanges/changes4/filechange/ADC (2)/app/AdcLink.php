<?php
declare(strict_types=1);

/**
 * Connects a portal payment to the ADC server core in the same YPay database.
 * Android SMS Relay posts to ADC/02_SERVER_CORE/api/relay/webhook_bank_sms.php.
 * The customer dashboard reads ADC/02_SERVER_CORE/api/payments/status.php.
 */
final class AdcLink {
    private static bool $ready = false;

    public static function ensureSchema(PDO $db): void {
        if (self::$ready) return;
        $portalEvents = $db->query("SHOW TABLES LIKE 'bank_sms_events'")->fetchColumn();
        if ($portalEvents) {
            $cols = [];
            foreach ($db->query('SHOW COLUMNS FROM bank_sms_events') as $r) {
                $cols[strtolower((string)$r['Field'])] = true;
            }
            if (isset($cols['utr']) && !isset($cols['event_id'])) {
                $already = $db->query("SHOW TABLES LIKE 'portal_bank_sms_events'")->fetchColumn();
                if (!$already) {
                    $db->exec('RENAME TABLE bank_sms_events TO portal_bank_sms_events');
                }
            }
        }
        $sql = file_get_contents(dirname(__DIR__).'/ADC/02_SERVER_CORE/sql/install.sql');
        if ($sql === false) {
            throw new RuntimeException('ADC install.sql is missing');
        }
        $sql = preg_replace('/--.*$/m', '', $sql) ?? $sql;
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            $db->exec($stmt);
        }
        $txn = [];
        foreach ($db->query('SHOW COLUMNS FROM transactions') as $r) {
            $txn[strtolower((string)$r['Field'])] = true;
        }
        if (!isset($txn['adc_payment_id'])) {
            $db->exec('ALTER TABLE transactions ADD COLUMN adc_payment_id VARCHAR(64) NULL');
        }
        if (!isset($txn['adc_public_token'])) {
            $db->exec('ALTER TABLE transactions ADD COLUMN adc_public_token VARCHAR(128) NULL');
        }
        self::seed($db);
        self::$ready = true;
    }

    /** @param array<string,mixed> $bank */
    public static function attach(PDO $db, string $reference, array $bank, ?float $amount): ?array {
        self::ensureSchema($db);
        if ($amount === null || $amount <= 0) return null;
        $relayBank = self::relayBank($bank);
        if ($relayBank === null) return null;
        $accountId = self::receivingAccountId($db, $bank, $relayBank);
        if ($accountId === null) return null;
        $merchantId = self::merchantId($db);
        $existing = $db->prepare('SELECT payment_id FROM qr_payment_orders WHERE merchant_client_id=? AND merchant_order_id=? LIMIT 1');
        $existing->execute([$merchantId, $reference]);
        if ($existing->fetchColumn()) return null;

        $token = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
        $hash = hash('sha256', $token);
        $amountText = number_format($amount, 2, '.', '');
        $ins = $db->prepare(
            "INSERT INTO qr_payment_orders
             (payment_id, merchant_client_id, merchant_order_id, public_token_hash,
              receiving_account_id, bank_name, amount, status, created_at, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'PENDING', UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL 10 MINUTE))"
        );
        $ins->execute([$reference, $merchantId, $reference, $hash, $accountId, $relayBank, $amountText]);
        $db->prepare('UPDATE transactions SET adc_payment_id=?, adc_public_token=? WHERE reference=?')
            ->execute([$reference, $token, $reference]);
        return ['payment_id' => $reference, 'token' => $token];
    }

    public static function syncReference(PDO $db, string $reference): void {
        self::ensureSchema($db);
        $q = $db->prepare('SELECT status, amount, submitted_utr, merchant_order_id FROM qr_payment_orders WHERE payment_id=? OR merchant_order_id=? LIMIT 1');
        $q->execute([$reference, $reference]);
        $row = $q->fetch();
        if (!$row) return;
        $ref = trim((string)($row['merchant_order_id'] ?: $reference));
        if (strtoupper((string)$row['status']) === 'SUCCESS') {
            $utr = trim((string)($row['submitted_utr'] ?? ''));
            $db->prepare(
                "UPDATE transactions
                 SET status='SUCCESS', confirmed_amount=?, rrn=IFNULL(rrn, NULLIF(?, '')),
                     upi_txn_id=IFNULL(upi_txn_id, NULLIF(?, '')), sms_match='SUCCESS',
                     confirmation_source='ADC_BANK_SMS', updated_at=UTC_TIMESTAMP()
                 WHERE reference=? AND status IN ('PENDING','EXPIRED')"
            )->execute([$row['amount'], $utr, $utr, $ref]);
            return;
        }
        $amb = $db->prepare(
            "SELECT 1 FROM bank_sms_events e
             JOIN qr_payment_orders o ON o.receiving_account_id=e.receiving_account_id
              AND o.bank_name=e.bank_name AND o.amount=e.amount
             WHERE o.merchant_order_id=? AND e.match_state='AMBIGUOUS' LIMIT 1"
        );
        $amb->execute([$ref]);
        if ($amb->fetchColumn()) {
            $db->prepare("UPDATE transactions SET sms_match='REVIEW_REQUIRED', updated_at=UTC_TIMESTAMP() WHERE reference=? AND status='PENDING' AND (sms_match IS NULL OR sms_match='')")
                ->execute([$ref]);
        }
    }

    /** @param array<string,mixed> $bank */
    private static function relayBank(array $bank): ?string {
        $blob = strtoupper((string)($bank['bank_name'] ?? '').' '.(string)($bank['vpa'] ?? '').' '.(string)($bank['code'] ?? ''));
        if (str_contains($blob, 'INDUS')) return 'INDUSIND';
        if (str_contains($blob, 'RBL')) return 'RBL';
        if (str_contains($blob, 'ICICI')) return 'ICICI';
        return null;
    }

    /** @param array<string,mixed> $bank */
    private static function receivingAccountId(PDO $db, array $bank, string $relayBank): ?int {
        $key = strtoupper(trim((string)($bank['code'] ?? '')));
        if ($key === '') return null;
        $st = $db->prepare('SELECT id FROM bank_receiving_accounts WHERE account_key=? LIMIT 1');
        $st->execute([$key]);
        $id = $st->fetchColumn();
        $vpa = trim((string)($bank['vpa'] ?? '')) ?: null;
        $name = trim((string)($bank['payee_name'] ?? $bank['bank_name'] ?? '')) ?: null;
        $active = (int)($bank['enabled'] ?? 1) === 1 ? 1 : 0;
        if ($id) {
            $db->prepare('UPDATE bank_receiving_accounts SET bank_name=?, account_label=?, upi_id=?, payee_name=?, active=? WHERE id=?')
                ->execute([$relayBank, $name ?: $key, $vpa, $name, $active, (int)$id]);
            return (int)$id;
        }
        $db->prepare(
            'INSERT INTO bank_receiving_accounts (account_key, bank_name, account_label, upi_id, payee_name, sort_order, active)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$key, $relayBank, $name ?: $key, $vpa, $name, (int)($bank['rotation_order'] ?? 100), $active]);
        return (int)$db->lastInsertId();
    }

    private static function merchantId(PDO $db): int {
        $id = $db->query("SELECT id FROM merchant_clients WHERE client_id='YEEDIPAY-PORTAL' LIMIT 1")->fetchColumn();
        if ($id) return (int)$id;
        $secret = bin2hex(random_bytes(32));
        $db->prepare('INSERT INTO merchant_clients (client_id, client_name, api_secret, active) VALUES (?,?,?,1)')
            ->execute(['YEEDIPAY-PORTAL', 'YeediPay Portal', $secret]);
        return (int)$db->lastInsertId();
    }

    private static function seed(PDO $db): void {
        $banks = $db->query('SELECT id, code, bank_name, vpa, payee_name, enabled, rotation_order FROM bank_profiles')->fetchAll();
        foreach ($banks as $bank) {
            if (self::relayBank($bank) === null) continue;
            self::receivingAccountId($db, $bank, self::relayBank($bank));
        }
        self::merchantId($db);
        $devices = (int)$db->query('SELECT COUNT(*) FROM sms_relay_devices')->fetchColumn();
        if ($devices > 0) return;
        $deviceId = 'YEEDI-'.strtoupper(bin2hex(random_bytes(4)));
        $secret = bin2hex(random_bytes(32));
        $db->prepare('INSERT INTO sms_relay_devices (device_id, device_label, api_secret, active) VALUES (?,?,?,1)')
            ->execute([$deviceId, 'Office parser', $secret]);
        $dir = dirname(__DIR__).'/storage';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents($dir.'/adc_relay_device.txt', "DEVICE_ID=$deviceId\nAPI_SECRET=$secret\nENDPOINT=/ADC/02_SERVER_CORE/api/relay/webhook_bank_sms.php\n");
    }
}
