<?php
declare(strict_types=1);

final class TicketService {
    public const STATUSES = ['OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED'];
    public const CATEGORIES = ['TRANSACTION', 'QR', 'WITHDRAWAL', 'REPORTS', 'ACCOUNT'];

    private static bool $ready = false;

    public static function ensureSchema(PDO $db): void {
        if (self::$ready) return;
        $db->exec("CREATE TABLE IF NOT EXISTS support_tickets (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            reference VARCHAR(32) NOT NULL,
            customer_id BIGINT UNSIGNED NOT NULL,
            subject VARCHAR(220) NOT NULL,
            category VARCHAR(64) NOT NULL DEFAULT 'TRANSACTION',
            message TEXT NOT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'OPEN',
            priority VARCHAR(16) NOT NULL DEFAULT 'NORMAL',
            assigned_to VARCHAR(80) NULL,
            admin_note TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            resolved_at DATETIME NULL,
            UNIQUE KEY uq_ticket_ref (reference),
            KEY idx_ticket_customer (customer_id),
            KEY idx_ticket_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE IF NOT EXISTS support_ticket_replies (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ticket_id BIGINT UNSIGNED NOT NULL,
            author_type VARCHAR(16) NOT NULL,
            author_name VARCHAR(120) NOT NULL DEFAULT '',
            message TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            KEY idx_reply_ticket (ticket_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        self::$ready = true;
    }

    public static function categoryLabel(string $category): string {
        return match (strtoupper($category)) {
            'TRANSACTION' => 'Transaction',
            'QR' => 'QR / Merchant',
            'WITHDRAWAL' => 'Withdrawals',
            'REPORTS' => 'Reports',
            'ACCOUNT' => 'Account',
            default => ucfirst(strtolower($category)),
        };
    }

    public static function statusLabel(string $status): string {
        return match (strtoupper($status)) {
            'OPEN', 'IN_PROGRESS' => 'Open',
            'RESOLVED' => 'Resolved',
            'CLOSED' => 'Closed',
            default => ucfirst(strtolower($status)),
        };
    }

    public static function statusClass(string $status): string {
        return match (strtoupper($status)) {
            'RESOLVED', 'CLOSED' => 'yp-pill yp-pill-ok',
            'IN_PROGRESS' => 'yp-pill yp-pill-wait',
            default => 'yp-pill yp-pill-open',
        };
    }

    /** @param array<string,mixed> $in */
    public static function create(PDO $db, array $c, array $in): array {
        self::ensureSchema($db);
        $subject = trim((string)($in['subject'] ?? ''));
        $message = trim((string)($in['message'] ?? ''));
        if ($subject === '') throw new InvalidArgumentException('Subject is required');
        if ($message === '') throw new InvalidArgumentException('Message is required');
        $category = strtoupper(trim((string)($in['category'] ?? 'TRANSACTION')));
        if (!in_array($category, self::CATEGORIES, true)) $category = 'TRANSACTION';
        $ref = self::newReference($db);
        $db->prepare('INSERT INTO support_tickets(reference,customer_id,subject,category,message,status,priority,created_at,updated_at) VALUES(?,?,?,?,?,"OPEN","NORMAL",UTC_TIMESTAMP(),UTC_TIMESTAMP())')
            ->execute([$ref, (int)$c['id'], $subject, $category, $message]);
        $ticket = self::get($db, (int)$db->lastInsertId());
        if (!$ticket) throw new RuntimeException('Could not create ticket');
        return $ticket;
    }

    public static function get(PDO $db, int $id): ?array {
        self::ensureSchema($db);
        $st = $db->prepare('SELECT t.*, c.code customer_code, c.name customer_name FROM support_tickets t JOIN customers c ON c.id=t.customer_id WHERE t.id=?');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /** @return list<array<string,mixed>> */
    public static function forCustomer(PDO $db, int $customerId): array {
        self::ensureSchema($db);
        $st = $db->prepare('SELECT * FROM support_tickets WHERE customer_id=? ORDER BY id DESC LIMIT 200');
        $st->execute([$customerId]);
        return $st->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public static function replies(PDO $db, int $ticketId): array {
        self::ensureSchema($db);
        $st = $db->prepare('SELECT * FROM support_ticket_replies WHERE ticket_id=? ORDER BY id ASC');
        $st->execute([$ticketId]);
        return $st->fetchAll();
    }

    public static function addReply(PDO $db, int $ticketId, string $authorName, string $message): void {
        self::ensureSchema($db);
        $message = trim($message);
        if ($message === '') throw new InvalidArgumentException('Reply message is required');
        $db->prepare('INSERT INTO support_ticket_replies(ticket_id,author_type,author_name,message,created_at) VALUES(?,"CUSTOMER",?,?,UTC_TIMESTAMP())')
            ->execute([$ticketId, trim($authorName), $message]);
        $db->prepare('UPDATE support_tickets SET updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$ticketId]);
    }

    private static function newReference(PDO $db): string {
        for ($i = 0; $i < 8; $i++) {
            $ref = 'TKT'.gmdate('ymd').strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            $st = $db->prepare('SELECT 1 FROM support_tickets WHERE reference=?');
            $st->execute([$ref]);
            if (!$st->fetchColumn()) return $ref;
        }
        throw new RuntimeException('Could not allocate ticket reference');
    }
}
