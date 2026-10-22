<?php
declare(strict_types=1);

/**
 * Links portal transactions to the ADC bank-SMS confirmation core.
 *
 * The Android relay posts authenticated ICICI credit events to /api/bank-sms.
 * BankSmsService validates and matches those events, while this class maintains
 * the ADC order records used to synchronize the server response back to the
 * portal transaction.
 */
final class AdcLink {
    private static bool $ready = false;

    public static function ensureSchema(PDO $db): void {
        if (self::$ready) return;

        $portalEvents = $db->query("SHOW TABLES LIKE 'bank_sms_events'")->fetchColumn();
        if ($portalEvents) {
            $cols = [];
            foreach ($db->query('SHOW COLUMNS FROM bank_sms_events') as $row) {
                $cols[strtolower((string)$row['Field'])] = true;
            }
            if (isset($cols['utr']) && !isset($cols['event_id'])) {
                $existing = $db->query("SHOW TABLES LIKE 'portal_bank_sms_events'")->fetchColumn();
                if (!$existing) $db->exec('RENAME TABLE bank_sms_events TO portal_bank_sms_events');
            }
        }

        self::createCoreTables($db);

        $txnCols = [];
        foreach ($db->query('SHOW COLUMNS FROM transactions') as $row) {
            $txnCols[strtolower((string)$row['Field'])] = true;
        }
        if (!isset($txnCols['adc_payment_id'])) {
            $db->exec('ALTER TABLE transactions ADD COLUMN adc_payment_id VARCHAR(64) NULL');
        }
        if (!isset($txnCols['adc_public_token'])) {
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
        $merchantId = self::merchantId($db);
        $existing = $db->prepare('SELECT payment_id FROM qr_payment_orders WHERE merchant_client_id=? AND merchant_order_id=? LIMIT 1');
        $existing->execute([$merchantId, $reference]);
        if ($existing->fetchColumn()) return null;

        $token = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
        $hash = hash('sha256', $token);
        $amountText = number_format($amount, 2, '.', '');
        $db->prepare(
            "INSERT INTO qr_payment_orders
             (payment_id, merchant_client_id, merchant_order_id, public_token_hash,
              receiving_account_id, bank_name, amount, status, created_at, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'PENDING', UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL 10 MINUTE))"
        )->execute([$reference, $merchantId, $reference, $hash, $accountId, $relayBank, $amountText]);
        $db->prepare('UPDATE transactions SET adc_payment_id=?, adc_public_token=? WHERE reference=?')
            ->execute([$reference, $token, $reference]);
        return ['payment_id' => $reference, 'token' => $token];
    }

    public static function syncReference(PDO $db, string $reference): void {
        self::ensureSchema($db);
        if ($reference === '') return;
        $query = $db->prepare(
            'SELECT status, amount, submitted_utr, merchant_order_id
             FROM qr_payment_orders WHERE payment_id=? OR merchant_order_id=? LIMIT 1'
        );
        $query->execute([$reference, $reference]);
        $row = $query->fetch();
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

        $ambiguous = $db->prepare(
            "SELECT 1 FROM bank_sms_events e
             JOIN qr_payment_orders o ON o.receiving_account_id=e.receiving_account_id
              AND o.bank_name=e.bank_name AND o.amount=e.amount
             WHERE o.merchant_order_id=? AND e.match_state='AMBIGUOUS' LIMIT 1"
        );
        $ambiguous->execute([$ref]);
        if ($ambiguous->fetchColumn()) {
            $db->prepare(
                "UPDATE transactions SET sms_match='REVIEW_REQUIRED', updated_at=UTC_TIMESTAMP()
                 WHERE reference=? AND status='PENDING' AND (sms_match IS NULL OR sms_match='')"
            )->execute([$ref]);
        }
    }

    private static function createCoreTables(PDO $db): void {
        $statements = [
            "CREATE TABLE IF NOT EXISTS sms_relay_devices (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                device_id VARCHAR(64) NOT NULL,
                device_label VARCHAR(100) NOT NULL,
                api_secret VARCHAR(128) NOT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                last_seen_at DATETIME NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_sms_relay_device_id (device_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS merchant_clients (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                client_id VARCHAR(64) NOT NULL,
                client_name VARCHAR(120) NOT NULL,
                api_secret VARCHAR(128) NOT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_merchant_client_id (client_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS bank_receiving_accounts (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                account_key VARCHAR(64) NOT NULL,
                bank_name VARCHAR(20) NOT NULL,
                account_label VARCHAR(100) NOT NULL,
                account_last4 CHAR(4) NULL,
                upi_id VARCHAR(120) NULL,
                payee_name VARCHAR(120) NULL,
                qr_image_url VARCHAR(500) NULL,
                sort_order INT NOT NULL DEFAULT 100,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_receiving_account_key (account_key),
                UNIQUE KEY uq_bank_account_mask (bank_name, account_last4),
                KEY ix_receiving_bank_active (bank_name, active, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS qr_payment_orders (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                payment_id VARCHAR(64) NOT NULL,
                merchant_client_id BIGINT UNSIGNED NULL,
                merchant_order_id VARCHAR(100) NULL,
                public_token_hash CHAR(64) NOT NULL,
                receiving_account_id BIGINT UNSIGNED NOT NULL,
                bank_name VARCHAR(20) NOT NULL,
                amount DECIMAL(14,2) NOT NULL,
                status ENUM('PENDING','SUCCESS','FAILED','EXPIRED') NOT NULL DEFAULT 'PENDING',
                submitted_utr VARCHAR(35) NULL,
                customer_ack_at DATETIME NULL,
                last_status_poll_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL,
                sms_confirmed_at DATETIME NULL,
                paid_at DATETIME NULL,
                UNIQUE KEY uq_qr_payment_id (payment_id),
                UNIQUE KEY uq_qr_bank_submitted_utr (bank_name, submitted_utr),
                UNIQUE KEY uq_merchant_order (merchant_client_id, merchant_order_id),
                KEY ix_qr_payment_match (status, bank_name, receiving_account_id, amount, created_at, expires_at),
                KEY ix_qr_collision (receiving_account_id, amount, status, expires_at),
                CONSTRAINT fk_qr_receiving_account FOREIGN KEY (receiving_account_id) REFERENCES bank_receiving_accounts(id),
                CONSTRAINT fk_qr_merchant_client FOREIGN KEY (merchant_client_id) REFERENCES merchant_clients(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS bank_sms_events (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                event_id CHAR(64) NOT NULL,
                device_id VARCHAR(64) NOT NULL,
                receiving_account_id BIGINT UNSIGNED NULL,
                bank_name VARCHAR(20) NOT NULL,
                amount DECIMAL(14,2) NOT NULL,
                reference_no VARCHAR(35) NOT NULL,
                account_last4 CHAR(4) NULL,
                sender_id VARCHAR(80) NOT NULL,
                received_at DATETIME NOT NULL,
                match_state ENUM('UNMATCHED','MATCHED','AMBIGUOUS') NOT NULL DEFAULT 'UNMATCHED',
                matched_payment_id BIGINT UNSIGNED NULL,
                matched_at DATETIME NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_bank_sms_event_id (event_id),
                UNIQUE KEY uq_bank_sms_reference (bank_name, reference_no),
                KEY ix_bank_sms_match (bank_name, receiving_account_id, amount, match_state),
                KEY ix_bank_sms_received (received_at),
                CONSTRAINT fk_sms_receiving_account FOREIGN KEY (receiving_account_id) REFERENCES bank_receiving_accounts(id),
                CONSTRAINT fk_sms_payment_order FOREIGN KEY (matched_payment_id) REFERENCES qr_payment_orders(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];
        foreach ($statements as $sql) $db->exec($sql);
    }

    /** @param array<string,mixed> $bank */
    private static function relayBank(array $bank): ?string {
        $blob = strtoupper(
            (string)($bank['bank_name'] ?? '').' '.
            (string)($bank['vpa'] ?? '').' '.
            (string)($bank['code'] ?? '')
        );
        if (str_contains($blob, 'INDUS')) return 'INDUSIND';
        if (str_contains($blob, 'RBL')) return 'RBL';
        if (str_contains($blob, 'ICICI')) return 'ICICI';
        return null;
    }

    /** @param array<string,mixed> $bank */
    private static function receivingAccountId(PDO $db, array $bank, string $relayBank): int {
        $key = strtoupper(trim((string)($bank['code'] ?? '')));
        if ($key === '') throw new RuntimeException('ADC bank code is missing');
        $query = $db->prepare('SELECT id FROM bank_receiving_accounts WHERE account_key=? LIMIT 1');
        $query->execute([$key]);
        $id = $query->fetchColumn();
        $vpa = trim((string)($bank['vpa'] ?? '')) ?: null;
        $name = trim((string)($bank['payee_name'] ?? $bank['bank_name'] ?? '')) ?: $key;
        $active = (int)($bank['enabled'] ?? 1) === 1 ? 1 : 0;
        if ($id) {
            $db->prepare(
                'UPDATE bank_receiving_accounts
                 SET bank_name=?, account_label=?, upi_id=?, payee_name=?, active=? WHERE id=?'
            )->execute([$relayBank, $name, $vpa, $name, $active, (int)$id]);
            return (int)$id;
        }
        $db->prepare(
            'INSERT INTO bank_receiving_accounts
             (account_key, bank_name, account_label, upi_id, payee_name, sort_order, active)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$key, $relayBank, $name, $vpa, $name, (int)($bank['rotation_order'] ?? 100), $active]);
        return (int)$db->lastInsertId();
    }

    private static function merchantId(PDO $db): int {
        $id = $db->query("SELECT id FROM merchant_clients WHERE client_id='YEEDIPAY-PORTAL' LIMIT 1")->fetchColumn();
        if ($id) return (int)$id;
        $db->prepare('INSERT INTO merchant_clients (client_id, client_name, api_secret, active) VALUES (?,?,?,1)')
            ->execute(['YEEDIPAY-PORTAL', 'YeediPay Portal', bin2hex(random_bytes(32))]);
        return (int)$db->lastInsertId();
    }

    private static function seed(PDO $db): void {
        foreach ($db->query('SELECT code, bank_name, vpa, payee_name, enabled, rotation_order FROM bank_profiles') as $bank) {
            $relayBank = self::relayBank($bank);
            if ($relayBank !== null) self::receivingAccountId($db, $bank, $relayBank);
        }
        self::merchantId($db);
        if ((int)$db->query('SELECT COUNT(*) FROM sms_relay_devices')->fetchColumn() > 0) return;

        $deviceId = 'YEEDI-'.strtoupper(bin2hex(random_bytes(4)));
        $secret = bin2hex(random_bytes(32));
        $db->prepare('INSERT INTO sms_relay_devices (device_id, device_label, api_secret, active) VALUES (?,?,?,1)')
            ->execute([$deviceId, 'Office parser', $secret]);
        $dir = dirname(__DIR__).'/storage';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents(
            $dir.'/adc_relay_device.txt',
            "DEVICE_ID={$deviceId}\nAPI_SECRET={$secret}\nENDPOINT=/api/bank-sms\n"
        );
    }
}
