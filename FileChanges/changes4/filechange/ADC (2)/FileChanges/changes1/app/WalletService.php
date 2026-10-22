<?php
declare(strict_types=1);

final class WalletService {
    public const RECHARGE_STATUSES = ['PENDING', 'SUCCESS', 'FAILED', 'EXPIRED'];
    public const WITHDRAW_STATUSES = ['REQUESTED', 'APPROVED', 'PROCESSING', 'PAID', 'REJECTED', 'CANCELLED'];

    private static bool $schemaReady = false;

    public static function ensureSchema(PDO $db): void {
        if (self::$schemaReady) {
            return;
        }
        TransactionService::ensureSchema($db);
        $db->exec("CREATE TABLE IF NOT EXISTS wallet_balances (
            customer_id BIGINT UNSIGNED PRIMARY KEY,
            available_balance DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            pending_balance DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_wallet_bal_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE IF NOT EXISTS wallet_recharges (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            customer_id BIGINT UNSIGNED NOT NULL,
            transaction_id BIGINT UNSIGNED NULL,
            reference VARCHAR(64) NOT NULL UNIQUE,
            amount DECIMAL(14,2) NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'PENDING',
            bank_id BIGINT UNSIGNED NULL,
            utr VARCHAR(160) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            credited_at DATETIME NULL,
            INDEX idx_wr_customer_status (customer_id, status),
            INDEX idx_wr_txn (transaction_id),
            INDEX idx_wr_created (created_at),
            CONSTRAINT fk_wr_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
            CONSTRAINT fk_wr_txn FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE IF NOT EXISTS withdrawal_requests (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            customer_id BIGINT UNSIGNED NOT NULL,
            reference VARCHAR(64) NOT NULL UNIQUE,
            amount DECIMAL(14,2) NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'REQUESTED',
            account_holder VARCHAR(180) NOT NULL DEFAULT '',
            bank_name VARCHAR(120) NOT NULL,
            account_number VARCHAR(32) NOT NULL,
            ifsc VARCHAR(16) NOT NULL,
            admin_note TEXT NULL,
            bank_ref VARCHAR(160) NULL,
            processed_by VARCHAR(80) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            processed_at DATETIME NULL,
            INDEX idx_wd_customer_status (customer_id, status),
            INDEX idx_wd_created (created_at),
            CONSTRAINT fk_wd_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        self::$schemaReady = true;
    }

    /** @return array{available_balance:float,pending_balance:float} */
    public static function balance(PDO $db, int $customerId): array {
        self::ensureSchema($db);
        $st = $db->prepare('SELECT available_balance, pending_balance FROM wallet_balances WHERE customer_id = ?');
        $st->execute([$customerId]);
        $row = $st->fetch();
        if (!$row) {
            $db->prepare('INSERT INTO wallet_balances(customer_id,available_balance,pending_balance,updated_at) VALUES(?,0,0,UTC_TIMESTAMP())')->execute([$customerId]);
            return ['available_balance' => 0.0, 'pending_balance' => 0.0];
        }
        return [
            'available_balance' => (float)$row['available_balance'],
            'pending_balance' => (float)$row['pending_balance'],
        ];
    }

    /** @param array<string,mixed> $c */
    public static function startRecharge(PDO $db, array $c, float $amount): array {
        self::ensureSchema($db);
        if ($amount < 1) {
            throw new InvalidArgumentException('Minimum recharge amount is ₹1');
        }
        $t = TransactionService::createAttempt($db, $c, $amount, 'WALLET-RECHARGE', 'WALLET_RECHARGE', 'N/A');
        $ref = self::newReference($db, 'WR');
        $st = $db->prepare('INSERT INTO wallet_recharges(customer_id,transaction_id,reference,amount,status,bank_id,created_at)
            VALUES(?,?,?,?,"PENDING",?,UTC_TIMESTAMP())');
        $st->execute([(int)$c['id'], (int)$t['id'], $ref, number_format($amount, 2, '.', ''), (int)$t['bank']['id']]);
        $recharge = self::getRecharge($db, (int)$db->lastInsertId());
        if (!$recharge) {
            throw new RuntimeException('Could not create wallet recharge');
        }
        return $recharge;
    }

    public static function onTransactionSuccess(PDO $db, int $transactionId): void {
        self::ensureSchema($db);
        $st = $db->prepare('SELECT * FROM wallet_recharges WHERE transaction_id = ? AND status = "PENDING" LIMIT 1');
        $st->execute([$transactionId]);
        $recharge = $st->fetch();
        if (!$recharge) {
            return;
        }
        $amount = (float)$recharge['amount'];
        $customerId = (int)$recharge['customer_id'];
        $db->beginTransaction();
        try {
            $db->prepare('UPDATE wallet_recharges SET status="SUCCESS", credited_at=UTC_TIMESTAMP() WHERE id=? AND status="PENDING"')->execute([(int)$recharge['id']]);
            self::ensureBalanceRow($db, $customerId);
            $db->prepare('UPDATE wallet_balances SET available_balance = available_balance + ?, updated_at=UTC_TIMESTAMP() WHERE customer_id=?')
                ->execute([number_format($amount, 2, '.', ''), $customerId]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function onTransactionClosed(PDO $db, int $transactionId, string $status): void {
        self::ensureSchema($db);
        $status = strtoupper(trim($status));
        if (!in_array($status, ['FAILED', 'EXPIRED'], true)) {
            return;
        }
        $db->prepare('UPDATE wallet_recharges SET status=? WHERE transaction_id=? AND status="PENDING"')->execute([$status, $transactionId]);
    }

    /** @param array<string,mixed> $c @param array<string,mixed> $in */
    public static function createWithdrawal(PDO $db, array $c, array $in): array {
        self::ensureSchema($db);
        $amount = money_or_null($in['amount'] ?? '');
        if ($amount === null || $amount < 1) {
            throw new InvalidArgumentException('Enter a valid withdrawal amount (min ₹1)');
        }
        $bankName = trim((string)($in['bank_name'] ?? ''));
        $accountNumber = preg_replace('/\s+/', '', trim((string)($in['account_number'] ?? ''))) ?? '';
        $ifsc = strtoupper(trim((string)($in['ifsc'] ?? '')));
        $holder = trim((string)($in['account_holder'] ?? ($c['name'] ?? '')));
        if ($bankName === '' || $accountNumber === '' || $ifsc === '') {
            throw new InvalidArgumentException('Bank name, account number and IFSC are required');
        }
        if (!preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $ifsc)) {
            throw new InvalidArgumentException('Enter a valid IFSC code');
        }
        $bal = self::balance($db, (int)$c['id']);
        if ($bal['available_balance'] < $amount) {
            throw new InvalidArgumentException('Insufficient wallet balance');
        }
        $ref = self::newReference($db, 'WD');
        $db->beginTransaction();
        try {
            self::ensureBalanceRow($db, (int)$c['id']);
            $upd = $db->prepare('UPDATE wallet_balances SET available_balance = available_balance - ?, pending_balance = pending_balance + ?, updated_at=UTC_TIMESTAMP() WHERE customer_id=? AND available_balance >= ?');
            $amt = number_format($amount, 2, '.', '');
            $upd->execute([$amt, $amt, (int)$c['id'], $amt]);
            if ($upd->rowCount() === 0) {
                throw new InvalidArgumentException('Insufficient wallet balance');
            }
            $st = $db->prepare('INSERT INTO withdrawal_requests(customer_id,reference,amount,status,account_holder,bank_name,account_number,ifsc,created_at)
                VALUES(?,?,?,"REQUESTED",?,?,?,?,UTC_TIMESTAMP())');
            $st->execute([(int)$c['id'], $ref, $amt, $holder, $bankName, $accountNumber, $ifsc]);
            $id = (int)$db->lastInsertId();
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
        $row = self::getWithdrawal($db, $id);
        if (!$row) {
            throw new RuntimeException('Could not create withdrawal request');
        }
        return $row;
    }

    public static function getRecharge(PDO $db, int $id): ?array {
        self::ensureSchema($db);
        $st = $db->prepare('SELECT r.*, c.code customer_code, c.name customer_name, t.reference txn_reference, t.status txn_status, t.intent_uri, b.bank_name, b.vpa
            FROM wallet_recharges r
            JOIN customers c ON c.id = r.customer_id
            LEFT JOIN transactions t ON t.id = r.transaction_id
            LEFT JOIN bank_profiles b ON b.id = r.bank_id
            WHERE r.id = ?');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function getRechargeByTxnRef(PDO $db, string $txnRef): ?array {
        self::ensureSchema($db);
        $st = $db->prepare('SELECT r.*, c.code customer_code, c.name customer_name, t.reference txn_reference, t.status txn_status, t.intent_uri, b.bank_name, b.vpa
            FROM wallet_recharges r
            JOIN customers c ON c.id = r.customer_id
            JOIN transactions t ON t.id = r.transaction_id
            LEFT JOIN bank_profiles b ON b.id = r.bank_id
            WHERE t.reference = ?');
        $st->execute([strtoupper(trim($txnRef))]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /** @return list<array<string,mixed>> */
    public static function rechargesForCustomer(PDO $db, int $customerId, int $limit = 100): array {
        self::ensureSchema($db);
        $st = $db->prepare('SELECT r.*, t.reference txn_reference FROM wallet_recharges r
            LEFT JOIN transactions t ON t.id = r.transaction_id
            WHERE r.customer_id = ?
            ORDER BY r.id DESC LIMIT '.max(1, min(200, $limit)));
        $st->execute([$customerId]);
        return $st->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public static function searchRecharges(PDO $db, array $f, int $limit = 500): array {
        self::ensureSchema($db);
        $where = [];
        $args = [];
        $status = strtoupper(trim((string)($f['status'] ?? '')));
        if ($status !== '' && in_array($status, self::RECHARGE_STATUSES, true)) {
            $where[] = 'r.status = ?';
            $args[] = $status;
        }
        $customerId = (int)($f['customer_id'] ?? 0);
        if ($customerId > 0) {
            $where[] = 'r.customer_id = ?';
            $args[] = $customerId;
        }
        $q = trim((string)($f['q'] ?? ''));
        if ($q !== '') {
            $like = '%'.$q.'%';
            $where[] = '(r.reference LIKE ? OR t.reference LIKE ? OR c.code LIKE ? OR c.name LIKE ? OR c.phone LIKE ?)';
            array_push($args, $like, $like, $like, $like, $like);
        }
        $sql = 'SELECT r.*, c.code customer_code, c.name customer_name, c.phone customer_phone,
            t.reference txn_reference, t.status txn_status, b.bank_name
            FROM wallet_recharges r
            JOIN customers c ON c.id = r.customer_id
            LEFT JOIN transactions t ON t.id = r.transaction_id
            LEFT JOIN bank_profiles b ON b.id = r.bank_id'
            .($where ? ' WHERE '.implode(' AND ', $where) : '')
            .' ORDER BY r.id DESC LIMIT '.max(1, min(500, $limit));
        $st = $db->prepare($sql);
        $st->execute($args);
        return $st->fetchAll();
    }

    /** @return array{pending:int,success:int,failed:int,total_amt:float} */
    public static function rechargeStats(PDO $db): array {
        self::ensureSchema($db);
        $row = $db->query("SELECT
            COALESCE(SUM(CASE WHEN status='PENDING' THEN 1 ELSE 0 END),0) pending,
            COALESCE(SUM(CASE WHEN status='SUCCESS' THEN 1 ELSE 0 END),0) success,
            COALESCE(SUM(CASE WHEN status IN ('FAILED','EXPIRED') THEN 1 ELSE 0 END),0) failed,
            COALESCE(SUM(CASE WHEN status='SUCCESS' THEN amount ELSE 0 END),0) total_amt
            FROM wallet_recharges")->fetch() ?: [];
        return [
            'pending' => (int)($row['pending'] ?? 0),
            'success' => (int)($row['success'] ?? 0),
            'failed' => (int)($row['failed'] ?? 0),
            'total_amt' => (float)($row['total_amt'] ?? 0),
        ];
    }

    public static function getWithdrawal(PDO $db, int $id): ?array {
        self::ensureSchema($db);
        $st = $db->prepare('SELECT w.*, c.code customer_code, c.name customer_name, c.phone customer_phone, c.email customer_email
            FROM withdrawal_requests w
            JOIN customers c ON c.id = w.customer_id
            WHERE w.id = ?');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /** @return list<array<string,mixed>> */
    public static function withdrawalsForCustomer(PDO $db, int $customerId, int $limit = 100): array {
        self::ensureSchema($db);
        $st = $db->prepare('SELECT * FROM withdrawal_requests WHERE customer_id = ? ORDER BY id DESC LIMIT '.max(1, min(200, $limit)));
        $st->execute([$customerId]);
        return $st->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public static function searchWithdrawals(PDO $db, array $f, int $limit = 500): array {
        self::ensureSchema($db);
        $where = [];
        $args = [];
        $status = strtoupper(trim((string)($f['status'] ?? '')));
        if ($status !== '' && in_array($status, self::WITHDRAW_STATUSES, true)) {
            $where[] = 'w.status = ?';
            $args[] = $status;
        }
        $customerId = (int)($f['customer_id'] ?? 0);
        if ($customerId > 0) {
            $where[] = 'w.customer_id = ?';
            $args[] = $customerId;
        }
        $q = trim((string)($f['q'] ?? ''));
        if ($q !== '') {
            $like = '%'.$q.'%';
            $where[] = '(w.reference LIKE ? OR c.code LIKE ? OR c.name LIKE ? OR c.phone LIKE ? OR w.account_number LIKE ?)';
            array_push($args, $like, $like, $like, $like, $like);
        }
        $sql = 'SELECT w.*, c.code customer_code, c.name customer_name, c.phone customer_phone
            FROM withdrawal_requests w
            JOIN customers c ON c.id = w.customer_id'
            .($where ? ' WHERE '.implode(' AND ', $where) : '')
            .' ORDER BY w.id DESC LIMIT '.max(1, min(500, $limit));
        $st = $db->prepare($sql);
        $st->execute($args);
        return $st->fetchAll();
    }

    /** @return array{requested:int,approved:int,paid:int,total:float} */
    public static function withdrawalStats(PDO $db): array {
        self::ensureSchema($db);
        $row = $db->query("SELECT
            COALESCE(SUM(CASE WHEN status='REQUESTED' THEN 1 ELSE 0 END),0) requested,
            COALESCE(SUM(CASE WHEN status IN ('APPROVED','PROCESSING') THEN 1 ELSE 0 END),0) approved,
            COALESCE(SUM(CASE WHEN status='PAID' THEN 1 ELSE 0 END),0) paid,
            COALESCE(SUM(CASE WHEN status IN ('REQUESTED','APPROVED','PROCESSING') THEN amount ELSE 0 END),0) pending_amt
            FROM withdrawal_requests")->fetch() ?: [];
        return [
            'requested' => (int)($row['requested'] ?? 0),
            'approved' => (int)($row['approved'] ?? 0),
            'paid' => (int)($row['paid'] ?? 0),
            'pending_amt' => (float)($row['pending_amt'] ?? 0),
        ];
    }

    /** @param array<string,mixed> $in */
    public static function updateWithdrawal(PDO $db, int $id, array $in, string $actor): void {
        self::ensureSchema($db);
        $w = self::getWithdrawal($db, $id);
        if (!$w) {
            throw new RuntimeException('Withdrawal request not found');
        }
        $oldStatus = strtoupper((string)$w['status']);
        $status = strtoupper(trim((string)($in['status'] ?? $oldStatus)));
        if (!in_array($status, self::WITHDRAW_STATUSES, true)) {
            throw new InvalidArgumentException('Invalid status');
        }
        $adminNote = trim((string)($in['admin_note'] ?? ($w['admin_note'] ?? '')));
        $bankRef = trim((string)($in['bank_ref'] ?? ($w['bank_ref'] ?? '')));
        $amount = (float)$w['amount'];
        $customerId = (int)$w['customer_id'];

        if ($status === $oldStatus && $adminNote === trim((string)($w['admin_note'] ?? '')) && $bankRef === trim((string)($w['bank_ref'] ?? ''))) {
            return;
        }

        $db->beginTransaction();
        try {
            if ($status !== $oldStatus) {
                if (in_array($status, ['REJECTED', 'CANCELLED'], true) && in_array($oldStatus, ['REQUESTED', 'APPROVED', 'PROCESSING'], true)) {
                    self::releasePending($db, $customerId, $amount);
                } elseif ($status === 'PAID' && in_array($oldStatus, ['REQUESTED', 'APPROVED', 'PROCESSING'], true)) {
                    self::finalizeWithdrawal($db, $customerId, $amount);
                } elseif ($oldStatus === 'PAID' && $status !== 'PAID') {
                    throw new InvalidArgumentException('Paid withdrawals cannot be changed');
                }
            }
            $processedAt = in_array($status, ['PAID', 'REJECTED', 'CANCELLED'], true) ? gmdate('Y-m-d H:i:s') : ($w['processed_at'] ?? null);
            $st = $db->prepare('UPDATE withdrawal_requests SET status=?, admin_note=?, bank_ref=?, processed_by=?, processed_at=? WHERE id=?');
            $st->execute([
                $status,
                $adminNote !== '' ? $adminNote : null,
                $bankRef !== '' ? $bankRef : null,
                $actor,
                $processedAt,
                $id,
            ]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function statusClass(string $status): string {
        return match (strtoupper($status)) {
            'REQUESTED' => 'status-PENDING',
            'APPROVED', 'PROCESSING' => 'status-PENDING',
            'PAID', 'SUCCESS' => 'status-SUCCESS',
            'REJECTED', 'FAILED', 'CANCELLED' => 'muted',
            default => '',
        };
    }

    private static function ensureBalanceRow(PDO $db, int $customerId): void {
        $db->prepare('INSERT IGNORE INTO wallet_balances(customer_id,available_balance,pending_balance,updated_at) VALUES(?,0,0,UTC_TIMESTAMP())')->execute([$customerId]);
    }

    private static function releasePending(PDO $db, int $customerId, float $amount): void {
        self::ensureBalanceRow($db, $customerId);
        $amt = number_format($amount, 2, '.', '');
        $db->prepare('UPDATE wallet_balances SET available_balance = available_balance + ?, pending_balance = pending_balance - ?, updated_at=UTC_TIMESTAMP() WHERE customer_id=? AND pending_balance >= ?')
            ->execute([$amt, $amt, $customerId, $amt]);
    }

    private static function finalizeWithdrawal(PDO $db, int $customerId, float $amount): void {
        self::ensureBalanceRow($db, $customerId);
        $amt = number_format($amount, 2, '.', '');
        $db->prepare('UPDATE wallet_balances SET pending_balance = pending_balance - ?, updated_at=UTC_TIMESTAMP() WHERE customer_id=? AND pending_balance >= ?')
            ->execute([$amt, $customerId, $amt]);
    }

    private static function newReference(PDO $db, string $prefix): string {
        for ($i = 0; $i < 8; $i++) {
            $ref = $prefix.gmdate('Ymd').strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            $table = str_starts_with($prefix, 'WR') ? 'wallet_recharges' : 'withdrawal_requests';
            $st = $db->prepare("SELECT 1 FROM {$table} WHERE reference = ?");
            $st->execute([$ref]);
            if (!$st->fetchColumn()) {
                return $ref;
            }
        }
        throw new RuntimeException('Could not allocate reference');
    }
}
