<?php
declare(strict_types=1);

final class TicketService {
    public const STATUSES = ['OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED'];
    public const PRIORITIES = ['LOW', 'NORMAL', 'HIGH'];
    public const CATEGORIES = ['GENERAL', 'PAYMENT', 'SETTLEMENT', 'BILLING', 'TECHNICAL'];

    private static bool $schemaReady = false;

    public static function ensureSchema(PDO $db): void {
        if (self::$schemaReady) {
            return;
        }
        TransactionService::ensureSchema($db);
        $db->exec("CREATE TABLE IF NOT EXISTS support_tickets (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            reference VARCHAR(32) NOT NULL UNIQUE,
            customer_id BIGINT UNSIGNED NOT NULL,
            subject VARCHAR(220) NOT NULL,
            category VARCHAR(64) NOT NULL DEFAULT 'GENERAL',
            message TEXT NOT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'OPEN',
            priority VARCHAR(16) NOT NULL DEFAULT 'NORMAL',
            assigned_to VARCHAR(80) NULL,
            admin_note TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            resolved_at DATETIME NULL,
            INDEX idx_ticket_customer(customer_id),
            INDEX idx_ticket_status(status),
            INDEX idx_ticket_created(created_at),
            CONSTRAINT fk_ticket_customer FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE IF NOT EXISTS support_ticket_replies (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ticket_id BIGINT UNSIGNED NOT NULL,
            author_type VARCHAR(16) NOT NULL,
            author_name VARCHAR(120) NOT NULL DEFAULT '',
            message TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_reply_ticket(ticket_id),
            CONSTRAINT fk_reply_ticket FOREIGN KEY(ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        self::$schemaReady = true;
    }

    /** @param array<string,mixed> $in */
    public static function create(PDO $db, array $c, array $in): array {
        self::ensureSchema($db);
        $subject = trim((string)($in['subject'] ?? ''));
        $message = trim((string)($in['message'] ?? ''));
        if ($subject === '') {
            throw new InvalidArgumentException('Subject is required');
        }
        if ($message === '') {
            throw new InvalidArgumentException('Message is required');
        }
        $category = strtoupper(trim((string)($in['category'] ?? 'GENERAL')));
        if (!in_array($category, self::CATEGORIES, true)) {
            $category = 'GENERAL';
        }
        $priority = strtoupper(trim((string)($in['priority'] ?? 'NORMAL')));
        if (!in_array($priority, self::PRIORITIES, true)) {
            $priority = 'NORMAL';
        }
        $ref = self::newReference($db);
        $st = $db->prepare('INSERT INTO support_tickets(reference,customer_id,subject,category,message,status,priority,created_at,updated_at)
            VALUES(?,?,?,?,?,"OPEN",?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
        $st->execute([$ref, (int)$c['id'], $subject, $category, $message, $priority]);
        $id = (int)$db->lastInsertId();
        $ticket = self::get($db, $id);
        if (!$ticket) {
            throw new RuntimeException('Could not create ticket');
        }
        return $ticket;
    }

    public static function get(PDO $db, int $id): ?array {
        self::ensureSchema($db);
        $st = $db->prepare('SELECT t.*, c.code customer_code, c.name customer_name, c.phone customer_phone, c.email customer_email
            FROM support_tickets t
            JOIN customers c ON c.id = t.customer_id
            WHERE t.id = ?');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function getByReference(PDO $db, string $reference): ?array {
        self::ensureSchema($db);
        $st = $db->prepare('SELECT t.*, c.code customer_code, c.name customer_name, c.phone customer_phone, c.email customer_email
            FROM support_tickets t
            JOIN customers c ON c.id = t.customer_id
            WHERE t.reference = ?');
        $st->execute([strtoupper(trim($reference))]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /** @return list<array<string,mixed>> */
    public static function forCustomer(PDO $db, int $customerId, int $limit = 200): array {
        self::ensureSchema($db);
        $st = $db->prepare('SELECT t.*, c.code customer_code, c.name customer_name
            FROM support_tickets t
            JOIN customers c ON c.id = t.customer_id
            WHERE t.customer_id = ?
            ORDER BY t.id DESC
            LIMIT '.max(1, min(500, $limit)));
        $st->execute([$customerId]);
        return $st->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public static function search(PDO $db, array $f, int $limit = 500): array {
        self::ensureSchema($db);
        $where = [];
        $args = [];
        $status = strtoupper(trim((string)($f['status'] ?? '')));
        if ($status !== '' && in_array($status, self::STATUSES, true)) {
            $where[] = 't.status = ?';
            $args[] = $status;
        }
        $customerId = (int)($f['customer_id'] ?? 0);
        if ($customerId > 0) {
            $where[] = 't.customer_id = ?';
            $args[] = $customerId;
        }
        $q = trim((string)($f['q'] ?? ''));
        if ($q !== '') {
            $like = '%'.$q.'%';
            $where[] = '(t.reference LIKE ? OR t.subject LIKE ? OR c.code LIKE ? OR c.name LIKE ? OR c.phone LIKE ?)';
            array_push($args, $like, $like, $like, $like, $like);
        }
        $sql = 'SELECT t.*, c.code customer_code, c.name customer_name, c.phone customer_phone, c.email customer_email
            FROM support_tickets t
            JOIN customers c ON c.id = t.customer_id'
            .($where ? ' WHERE '.implode(' AND ', $where) : '')
            .' ORDER BY t.id DESC LIMIT '.max(1, min(500, $limit));
        $st = $db->prepare($sql);
        $st->execute($args);
        return $st->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public static function replies(PDO $db, int $ticketId): array {
        self::ensureSchema($db);
        $st = $db->prepare('SELECT * FROM support_ticket_replies WHERE ticket_id = ? ORDER BY id ASC');
        $st->execute([$ticketId]);
        return $st->fetchAll();
    }

    public static function addReply(PDO $db, int $ticketId, string $authorType, string $authorName, string $message): void {
        self::ensureSchema($db);
        $message = trim($message);
        if ($message === '') {
            throw new InvalidArgumentException('Reply message is required');
        }
        $ticket = self::get($db, $ticketId);
        if (!$ticket) {
            throw new RuntimeException('Ticket not found');
        }
        $type = strtoupper(trim($authorType));
        if (!in_array($type, ['CUSTOMER', 'ADMIN', 'SUPERADMIN'], true)) {
            $type = 'ADMIN';
        }
        $db->beginTransaction();
        try {
            $st = $db->prepare('INSERT INTO support_ticket_replies(ticket_id,author_type,author_name,message,created_at) VALUES(?,?,?,?,UTC_TIMESTAMP())');
            $st->execute([$ticketId, $type, trim($authorName), $message]);
            $db->prepare('UPDATE support_tickets SET updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$ticketId]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /** @param array<string,mixed> $in */
    public static function update(PDO $db, int $ticketId, array $in, string $actor): void {
        self::ensureSchema($db);
        $ticket = self::get($db, $ticketId);
        if (!$ticket) {
            throw new RuntimeException('Ticket not found');
        }
        $status = strtoupper(trim((string)($in['status'] ?? $ticket['status'] ?? 'OPEN')));
        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Invalid status');
        }
        $priority = strtoupper(trim((string)($in['priority'] ?? $ticket['priority'] ?? 'NORMAL')));
        if (!in_array($priority, self::PRIORITIES, true)) {
            $priority = (string)($ticket['priority'] ?? 'NORMAL');
        }
        $assigned = trim((string)($in['assigned_to'] ?? ($ticket['assigned_to'] ?? '')));
        $adminNote = trim((string)($in['admin_note'] ?? ($ticket['admin_note'] ?? '')));
        $resolvedAt = null;
        if (in_array($status, ['RESOLVED', 'CLOSED'], true)) {
            $resolvedAt = $ticket['resolved_at'] ?? gmdate('Y-m-d H:i:s');
        }
        $st = $db->prepare('UPDATE support_tickets SET status=?, priority=?, assigned_to=?, admin_note=?, resolved_at=?, updated_at=UTC_TIMESTAMP() WHERE id=?');
        $st->execute([
            $status,
            $priority,
            $assigned !== '' ? $assigned : null,
            $adminNote !== '' ? $adminNote : null,
            $resolvedAt,
            $ticketId,
        ]);
    }

    /** @return array{open:int,in_progress:int,resolved:int,total:int} */
    public static function stats(PDO $db): array {
        self::ensureSchema($db);
        $row = $db->query("SELECT
            COUNT(*) total,
            COALESCE(SUM(CASE WHEN status='OPEN' THEN 1 ELSE 0 END),0) open_n,
            COALESCE(SUM(CASE WHEN status='IN_PROGRESS' THEN 1 ELSE 0 END),0) in_progress,
            COALESCE(SUM(CASE WHEN status IN ('RESOLVED','CLOSED') THEN 1 ELSE 0 END),0) resolved
            FROM support_tickets")->fetch() ?: [];
        return [
            'total' => (int)($row['total'] ?? 0),
            'open' => (int)($row['open_n'] ?? 0),
            'in_progress' => (int)($row['in_progress'] ?? 0),
            'resolved' => (int)($row['resolved'] ?? 0),
        ];
    }

    public static function statusClass(string $status): string {
        return match (strtoupper($status)) {
            'OPEN' => 'status-PENDING',
            'IN_PROGRESS' => 'status-PENDING',
            'RESOLVED' => 'status-SUCCESS',
            'CLOSED' => 'muted',
            default => '',
        };
    }

    private static function newReference(PDO $db): string {
        for ($i = 0; $i < 8; $i++) {
            $ref = 'TKT'.gmdate('Ymd').strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            $st = $db->prepare('SELECT 1 FROM support_tickets WHERE reference = ?');
            $st->execute([$ref]);
            if (!$st->fetchColumn()) {
                return $ref;
            }
        }
        throw new RuntimeException('Could not allocate ticket reference');
    }
}
