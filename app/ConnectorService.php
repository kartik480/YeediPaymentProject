<?php
declare(strict_types=1);

final class ConnectorService {
    public const MODES = ['UAT' => 'UAT', 'LIVE' => 'LIVE'];

    public static function ensureSchema(PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS payout_connectors (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            customer_id BIGINT UNSIGNED NOT NULL,
            bank_id BIGINT UNSIGNED NOT NULL,
            mode VARCHAR(16) NOT NULL DEFAULT 'UAT',
            status VARCHAR(24) NOT NULL DEFAULT 'UNVERIFIED',
            verified_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_payout_connector(customer_id, bank_id, mode),
            INDEX idx_payout_connector_status(status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public static function all(PDO $db): array {
        self::ensureSchema($db);
        return $db->query("SELECT pc.*, c.code customer_code, c.name customer_name, b.bank_name
            FROM payout_connectors pc
            JOIN customers c ON c.id=pc.customer_id
            JOIN bank_profiles b ON b.id=pc.bank_id
            ORDER BY pc.id DESC LIMIT 500")->fetchAll();
    }

    public static function add(PDO $db, array $in): int {
        self::ensureSchema($db);
        $code = normalize_code((string)($in['tenant'] ?? ''));
        $c = TransactionService::customerByCode($db, $code);
        if (!$c) throw new RuntimeException('Select a tenant');
        $bankId = (int)($in['bank_id'] ?? 0);
        $bank = BankService::get($db, $bankId);
        if (!$bank) throw new RuntimeException('Select a bank');
        $mode = strtoupper(trim((string)($in['mode'] ?? 'UAT')));
        if (!isset(self::MODES[$mode])) $mode = 'UAT';
        try {
            $st = $db->prepare('INSERT INTO payout_connectors(customer_id,bank_id,mode,status,created_at) VALUES(?,?,?,\'UNVERIFIED\',UTC_TIMESTAMP())');
            $st->execute([(int)$c['id'], $bankId, $mode]);
            return (int)$db->lastInsertId();
        } catch (PDOException $e) {
            throw new RuntimeException('That tenant, bank and mode are already listed.');
        }
    }

    public static function verify(PDO $db, int $id): void {
        self::ensureSchema($db);
        $st = $db->prepare('SELECT * FROM payout_connectors WHERE id=?');
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) throw new RuntimeException('Connector not found');
        if (strtoupper((string)$row['status']) === 'VERIFIED') {
            $db->prepare("UPDATE payout_connectors SET status='UNVERIFIED', verified_at=NULL WHERE id=?")->execute([$id]);
            return;
        }
        $db->prepare("UPDATE payout_connectors SET status='VERIFIED', verified_at=UTC_TIMESTAMP() WHERE id=?")->execute([$id]);
    }
}
