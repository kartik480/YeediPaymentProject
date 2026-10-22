<?php
declare(strict_types=1);
final class SettlementService {
    private static bool $schemaReady = false;

    public static function ensureSchema(PDO $db): void {
        if (self::$schemaReady) return;
        TransactionService::ensureSchema($db);
        $db->exec("CREATE TABLE IF NOT EXISTS settlements (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            reference VARCHAR(64) NOT NULL UNIQUE,
            range_start DATE NOT NULL,
            range_end DATE NOT NULL,
            txn_count INT NOT NULL DEFAULT 0,
            gross_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            fee_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            net_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            status VARCHAR(32) NOT NULL DEFAULT 'REQUESTED',
            bank_ref VARCHAR(160) NULL,
            created_by VARCHAR(80) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            settled_at DATETIME NULL,
            INDEX idx_stl_status(status),
            INDEX idx_stl_created(created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $cols = [];
        foreach ($db->query('SHOW COLUMNS FROM transactions') as $r) {
            $cols[strtolower((string)$r['Field'])] = true;
        }
        if (!isset($cols['settlement_id'])) {
            $db->exec('ALTER TABLE transactions ADD COLUMN settlement_id BIGINT UNSIGNED NULL, ADD INDEX idx_txn_settlement(settlement_id)');
        }
        self::$schemaReady = true;
    }

    public static function all(PDO $db): array {
        self::ensureSchema($db);
        return $db->query('SELECT * FROM settlements ORDER BY id DESC LIMIT 500')->fetchAll();
    }

    public static function get(PDO $db, int $id): ?array {
        self::ensureSchema($db);
        $st = $db->prepare('SELECT * FROM settlements WHERE id=?');
        $st->execute([$id]);
        $r = $st->fetch();
        return $r ?: null;
    }

    public static function items(PDO $db, int $settlementId): array {
        self::ensureSchema($db);
        $st = $db->prepare('SELECT t.*,c.code customer_code,c.name customer_name,b.bank_name FROM transactions t JOIN customers c ON c.id=t.customer_id JOIN bank_profiles b ON b.id=t.bank_id WHERE t.settlement_id=? ORDER BY t.id');
        $st->execute([$settlementId]);
        return $st->fetchAll();
    }

    public static function request(PDO $db, string $start, string $end, mixed $fee, mixed $tax, string $actor): int {
        self::ensureSchema($db);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            throw new InvalidArgumentException('Select a valid date range');
        }
        if ($start > $end) throw new InvalidArgumentException('Start date must be on or before end date');
        $feeAmt = self::moneyAllowZero($fee, 'Fee');
        $taxAmt = self::moneyAllowZero($tax, 'Tax');
        [$from, $to] = local_period_bounds('custom', $start, $end);
        $db->beginTransaction();
        try {
            $st = $db->prepare("SELECT id, COALESCE(confirmed_amount, requested_amount, 0) amt FROM transactions WHERE status='SUCCESS' AND settlement_status='UNSETTLED' AND settlement_id IS NULL AND created_at>=? AND created_at<? FOR UPDATE");
            $st->execute([$from, $to]);
            $rows = $st->fetchAll();
            if (!$rows) throw new RuntimeException('No unsettled SUCCESS payments in that date range');
            $gross = 0.0;
            $ids = [];
            foreach ($rows as $r) {
                $ids[] = (int)$r['id'];
                $gross += (float)$r['amt'];
            }
            $grossStr = number_format($gross, 2, '.', '');
            $net = $gross - (float)$feeAmt - (float)$taxAmt;
            if ($net < 0) throw new RuntimeException('Fee + tax cannot exceed the gross amount');
            $ref = self::newReference($db);
            $ins = $db->prepare('INSERT INTO settlements(reference,range_start,range_end,txn_count,gross_amount,fee_amount,tax_amount,net_amount,status,created_by,created_at) VALUES(?,?,?,?,?,?,?,?,"REQUESTED",?,UTC_TIMESTAMP())');
            $ins->execute([$ref, $start, $end, count($ids), $grossStr, $feeAmt, $taxAmt, number_format($net, 2, '.', ''), $actor]);
            $id = (int)$db->lastInsertId();
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $up = $db->prepare("UPDATE transactions SET settlement_id=?, settlement_status='REQUESTED', updated_at=UTC_TIMESTAMP() WHERE id IN ($ph)");
            $up->execute(array_merge([$id], $ids));
            $db->commit();
            return $id;
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public static function markSettled(PDO $db, int $id, string $bankRef): void {
        self::ensureSchema($db);
        $s = self::get($db, $id);
        if (!$s) throw new RuntimeException('Settlement not found');
        if ($s['status'] !== 'REQUESTED') throw new RuntimeException('Only a requested settlement can be marked settled');
        $bankRef = trim($bankRef);
        $db->beginTransaction();
        try {
            $db->prepare('UPDATE settlements SET status=?,bank_ref=?,settled_at=UTC_TIMESTAMP() WHERE id=?')->execute(['SETTLED', $bankRef !== '' ? $bankRef : null, $id]);
            $db->prepare("UPDATE transactions SET settlement_status='SETTLED', updated_at=UTC_TIMESTAMP() WHERE settlement_id=?")->execute([$id]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public static function cancel(PDO $db, int $id): void {
        self::ensureSchema($db);
        $s = self::get($db, $id);
        if (!$s) throw new RuntimeException('Settlement not found');
        if ($s['status'] !== 'REQUESTED') throw new RuntimeException('Only a requested settlement can be cancelled');
        $db->beginTransaction();
        try {
            $db->prepare("UPDATE transactions SET settlement_id=NULL, settlement_status='UNSETTLED', updated_at=UTC_TIMESTAMP() WHERE settlement_id=?")->execute([$id]);
            $db->prepare("UPDATE settlements SET status='CANCELLED' WHERE id=?")->execute([$id]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public static function rangeLabel(array $s): string {
        return self::dmy((string)$s['range_start']).' → '.self::dmy((string)$s['range_end']);
    }

    private static function dmy(string $ymd): string {
        $d = DateTimeImmutable::createFromFormat('Y-m-d', substr($ymd, 0, 10));
        return $d ? $d->format('d-m-Y') : $ymd;
    }

    private static function moneyAllowZero(mixed $v, string $label): string {
        $s = trim((string)$v);
        if ($s === '') return '0.00';
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $s)) throw new InvalidArgumentException('Invalid '.$label);
        return number_format((float)$s, 2, '.', '');
    }

    private static function newReference(PDO $db): string {
        for ($i = 0; $i < 8; $i++) {
            $ref = 'STL'.gmdate('Ymd').strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            $st = $db->prepare('SELECT 1 FROM settlements WHERE reference=?');
            $st->execute([$ref]);
            if (!$st->fetchColumn()) return $ref;
        }
        throw new RuntimeException('Could not allocate settlement reference');
    }
}
