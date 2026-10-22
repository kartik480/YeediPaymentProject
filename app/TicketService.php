<?php
declare(strict_types=1);

final class TicketService {
    public const STATUSES = ['OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED'];
    public const PRIORITIES = ['LOW', 'NORMAL', 'HIGH'];
    public const CATEGORIES = ['GENERAL', 'PAYMENT', 'SETTLEMENT', 'BILLING', 'TECHNICAL'];
    /** Seller WhatsApp (E.164 without +) — https://wa.me/66642584053 */
    public const WHATSAPP_SELLER = '66642584053';

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
        $cols = [];
        foreach ($db->query('SHOW COLUMNS FROM support_tickets')->fetchAll(PDO::FETCH_ASSOC) as $col) {
            $cols[(string)$col['Field']] = true;
        }
        if (!isset($cols['order_reference'])) {
            $db->exec('ALTER TABLE support_tickets ADD COLUMN order_reference VARCHAR(80) NULL AFTER category');
        }
        if (!isset($cols['attachment_path'])) {
            $db->exec('ALTER TABLE support_tickets ADD COLUMN attachment_path VARCHAR(255) NULL AFTER message');
        }
        if (!isset($cols['attachment_orig'])) {
            $db->exec('ALTER TABLE support_tickets ADD COLUMN attachment_orig VARCHAR(180) NULL AFTER attachment_path');
        }
        self::$schemaReady = true;
    }

    public static function categoryLabel(string $category): string {
        return match (strtoupper(trim($category))) {
            'PAYMENT' => 'Payment issue',
            'SETTLEMENT' => 'Settlement / payout',
            'BILLING' => 'Billing & invoice',
            'TECHNICAL' => 'Technical / app',
            'GENERAL' => 'General',
            default => ucfirst(strtolower(str_replace('_', ' ', $category))),
        };
    }

    public static function statusLabel(string $status): string {
        return match (strtoupper(trim($status))) {
            'OPEN' => 'Open',
            'IN_PROGRESS' => 'In Progress',
            'RESOLVED' => 'Resolved',
            'CLOSED' => 'Closed',
            default => ucfirst(strtolower(str_replace('_', ' ', $status))),
        };
    }

    public static function whatsappSupportUrl(array $c, string $problem, ?string $ticketRef = null): string {
        $code = strtoupper(trim((string)($c['code'] ?? '')));
        $problem = trim($problem);
        if ($problem === '') {
            $problem = 'Support request';
        }
        if (strlen($problem) > 900) {
            $problem = substr($problem, 0, 897).'...';
        }
        $text = 'Hello Seller, I need help with my payment/order. My Customer ID is '.$code.'. Problem: '.$problem.'.';
        if ($ticketRef !== null && trim($ticketRef) !== '') {
            $text .= ' Ticket ID: '.strtoupper(trim($ticketRef)).'.';
        }
        return 'https://wa.me/'.self::WHATSAPP_SELLER.'?text='.rawurlencode($text);
    }

    /** @param array<string,mixed> $ticket */
    public static function problemSummary(array $ticket): string {
        $parts = [];
        $subject = trim((string)($ticket['subject'] ?? ''));
        if ($subject !== '') {
            $parts[] = $subject;
        }
        $order = trim((string)($ticket['order_reference'] ?? ''));
        if ($order !== '') {
            $parts[] = 'Order/Txn '.$order;
        }
        $cat = self::categoryLabel((string)($ticket['category'] ?? ''));
        if ($cat !== '') {
            $parts[] = $cat;
        }
        $msg = trim((string)($ticket['message'] ?? ''));
        if ($msg !== '') {
            $parts[] = $msg;
        }
        $text = implode(' — ', $parts);
        return strlen($text) > 900 ? substr($text, 0, 897).'...' : $text;
    }

    /** @param array<string,mixed> $in */
    public static function create(PDO $db, array $c, array $in, array $upload = []): array {
        self::ensureSchema($db);
        $subject = trim((string)($in['subject'] ?? ''));
        $message = trim((string)($in['message'] ?? ''));
        if ($subject === '') {
            throw new InvalidArgumentException('Subject is required');
        }
        if ($message === '') {
            throw new InvalidArgumentException('Detailed problem description is required');
        }
        $category = strtoupper(trim((string)($in['category'] ?? 'GENERAL')));
        if (!in_array($category, self::CATEGORIES, true)) {
            $category = 'GENERAL';
        }
        $orderRef = trim((string)($in['order_reference'] ?? ($in['order_id'] ?? '')));
        if (strlen($orderRef) > 80) {
            $orderRef = substr($orderRef, 0, 80);
        }
        $priority = strtoupper(trim((string)($in['priority'] ?? 'NORMAL')));
        if (!in_array($priority, self::PRIORITIES, true)) {
            $priority = 'NORMAL';
        }
        $ref = self::newReference($db);
        $st = $db->prepare('INSERT INTO support_tickets(reference,customer_id,subject,category,order_reference,message,status,priority,created_at,updated_at)
            VALUES(?,?,?,?,?,?,"OPEN",?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
        $st->execute([$ref, (int)$c['id'], $subject, $category, $orderRef !== '' ? $orderRef : null, $message, $priority]);
        $id = (int)$db->lastInsertId();
        [$attachPath, $attachOrig] = self::storeAttachment($upload, $id, true);
        if ($attachPath !== '') {
            $db->prepare('UPDATE support_tickets SET attachment_path=?, attachment_orig=?, updated_at=UTC_TIMESTAMP() WHERE id=?')
                ->execute([$attachPath, $attachOrig !== '' ? $attachOrig : null, $id]);
        }
        $ticket = self::get($db, $id);
        if (!$ticket) {
            throw new RuntimeException('Could not create ticket');
        }
        return $ticket;
    }

    /** @param array<string,mixed> $file @return array{0:string,1:string} */
    private static function storeAttachment(array $file, int $ticketId, bool $optional = false): array {
        $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE || empty($file['tmp_name'])) {
            if ($optional) {
                return ['', ''];
            }
            throw new InvalidArgumentException('Please upload a screenshot');
        }
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            throw new InvalidArgumentException('Image is too large (max 5 MB)');
        }
        if ($err !== UPLOAD_ERR_OK || !is_uploaded_file((string)$file['tmp_name'])) {
            throw new InvalidArgumentException('Image upload failed. Try again.');
        }
        if ((int)($file['size'] ?? 0) > 5 * 1024 * 1024) {
            throw new InvalidArgumentException('Image is too large (max 5 MB)');
        }
        $mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file((string)$file['tmp_name']);
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? '';
        if ($ext === '') {
            throw new InvalidArgumentException('Screenshot must be JPG, PNG, or WEBP');
        }
        $dir = YEEDIPAY_ROOT.'/storage/support_tickets';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new InvalidArgumentException('Could not save image. Try again.');
        }
        $name = $ticketId.'_'.bin2hex(random_bytes(8)).'.'.$ext;
        $dest = $dir.'/'.$name;
        if (!move_uploaded_file((string)$file['tmp_name'], $dest)) {
            throw new InvalidArgumentException('Could not save image. Try again.');
        }
        $orig = basename((string)($file['name'] ?? 'screenshot.'.$ext));
        $orig = substr(preg_replace('/[^A-Za-z0-9._-]/', '_', $orig) ?? 'screenshot', 0, 180);
        return ['storage/support_tickets/'.$name, $orig];
    }

    /** @param array<string,mixed> $ticket */
    public static function streamAttachment(array $ticket): void {
        $rel = trim((string)($ticket['attachment_path'] ?? ''));
        if ($rel === '' || str_contains($rel, '..')) {
            http_response_code(404);
            exit('Attachment not found');
        }
        $root = defined('YEEDIPAY_ROOT') ? YEEDIPAY_ROOT : dirname(__DIR__);
        $abs = realpath($root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $rel));
        $dir = realpath($root.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'support_tickets');
        if ($abs === false || $dir === false || !str_starts_with($abs, $dir) || !is_file($abs)) {
            http_response_code(404);
            exit('Attachment not found');
        }
        $mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file($abs);
        header('Content-Type: '.$mime);
        header('Content-Length: '.(string)filesize($abs));
        header('Content-Disposition: inline; filename="'.basename($abs).'"');
        header('Cache-Control: private, max-age=3600');
        header('X-Content-Type-Options: nosniff');
        readfile($abs);
        exit;
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
