<?php
declare(strict_types=1);

final class BankVerificationService {
    public static function ensureSchema(PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS source_bank_verifications (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            customer_id BIGINT UNSIGNED NOT NULL,
            bank_name VARCHAR(120) NOT NULL,
            account_number VARCHAR(32) NOT NULL,
            ifsc VARCHAR(16) NOT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'PENDING',
            verified_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_src_bank_cust(customer_id),
            INDEX idx_src_bank_status(status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public static function all(PDO $db): array {
        self::ensureSchema($db);
        return $db->query("SELECT s.*, c.code customer_code, c.name customer_name
            FROM source_bank_verifications s
            JOIN customers c ON c.id=s.customer_id
            ORDER BY s.id DESC LIMIT 500")->fetchAll();
    }

    public static function add(PDO $db, array $in): int {
        self::ensureSchema($db);
        $code = normalize_code((string)($in['company'] ?? $in['tenant'] ?? ''));
        $c = TransactionService::customerByCode($db, $code);
        if (!$c) throw new RuntimeException('Select a company');
        $bank = trim((string)($in['bank_name'] ?? ''));
        if ($bank === '') throw new RuntimeException('Bank is required');
        $account = preg_replace('/\s+/', '', (string)($in['account_number'] ?? ''));
        if ($account === '' || !preg_match('/^[0-9]{6,32}$/', $account)) throw new RuntimeException('Enter a valid account number');
        $ifsc = strtoupper(preg_replace('/\s+/', '', (string)($in['ifsc'] ?? '')) ?? '');
        if (!preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $ifsc)) throw new RuntimeException('Enter a valid IFSC');
        $st = $db->prepare("INSERT INTO source_bank_verifications(customer_id,bank_name,account_number,ifsc,status,created_at) VALUES(?,?,?,?, 'PENDING', UTC_TIMESTAMP())");
        $st->execute([(int)$c['id'], $bank, $account, $ifsc]);
        return (int)$db->lastInsertId();
    }

    public static function decide(PDO $db, int $id, string $decision): void {
        self::ensureSchema($db);
        $decision = strtoupper(trim($decision));
        if (!in_array($decision, ['APPROVED', 'REJECTED'], true)) throw new RuntimeException('Invalid decision');
        $st = $db->prepare('SELECT * FROM source_bank_verifications WHERE id=?');
        $st->execute([$id]);
        if (!$st->fetch()) throw new RuntimeException('Bank record not found');
        if ($decision === 'APPROVED') {
            $db->prepare("UPDATE source_bank_verifications SET status=?, verified_at=UTC_TIMESTAMP() WHERE id=?")->execute([$decision, $id]);
            return;
        }
        $db->prepare("UPDATE source_bank_verifications SET status=?, verified_at=NULL WHERE id=?")->execute([$decision, $id]);
    }
}
