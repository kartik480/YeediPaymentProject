<?php
declare(strict_types=1);

final class EmailService {
    private static bool $schemaReady = false;

    public static function ensureSchema(PDO $db): void {
        if (self::$schemaReady) return;
        $db->exec("CREATE TABLE IF NOT EXISTS email_outbox (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_key VARCHAR(160) NOT NULL UNIQUE,
            recipient VARCHAR(180) NOT NULL,
            subject VARCHAR(220) NOT NULL,
            body TEXT NOT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'QUEUED',
            last_error TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            sent_at DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        self::$schemaReady = true;
    }

    public static function enqueueTxn(PDO $db, int $txnId, string $status): void {
        self::ensureSchema($db);
        $st = $db->prepare('SELECT t.*,c.code customer_code,c.name customer_name,c.email customer_email,b.bank_name FROM transactions t JOIN customers c ON c.id=t.customer_id JOIN bank_profiles b ON b.id=t.bank_id WHERE t.id=?');
        $st->execute([$txnId]);
        $t = $st->fetch();
        if (!$t) return;
        $status = strtoupper($status);
        $amt = (string)($t['confirmed_amount'] ?? $t['requested_amount'] ?? '');
        $subject = 'YEEDIPAY payment '.$status.' · '.$t['reference'];
        $body = "Payment {$status}\nReference: {$t['reference']}\nCustomer: {$t['customer_name']} ({$t['customer_code']})\nAmount: Rs.{$amt}\nBank: {$t['bank_name']}\n";
        $targets = [];
        $cust = trim((string)($t['customer_email'] ?? ''));
        if ($cust !== '' && filter_var($cust, FILTER_VALIDATE_EMAIL)) {
            $targets[] = $cust;
        }
        $admin = trim((string)Env::get('MAIL_ADMIN', ''));
        if ($admin !== '' && filter_var($admin, FILTER_VALIDATE_EMAIL)) {
            $targets[] = $admin;
        }
        $targets = array_values(array_unique($targets));
        if (!$targets) return;
        foreach ($targets as $to) {
            $key = 'txn:'.$txnId.':'.$status.':'.$to;
            $q = $db->prepare("INSERT IGNORE INTO email_outbox(event_key,recipient,subject,body,status,created_at) VALUES(?,?,?,?,'QUEUED',UTC_TIMESTAMP())");
            $q->execute([$key, $to, $subject, $body]);
            self::sendQueued($db, $key);
        }
    }

    private static function sendQueued(PDO $db, string $eventKey): void {
        $st = $db->prepare("SELECT * FROM email_outbox WHERE event_key=? AND status='QUEUED' LIMIT 1");
        $st->execute([$eventKey]);
        $row = $st->fetch();
        if (!$row) return;
        $from = trim((string)Env::get('MAIL_FROM', 'noreply@yeedipay.in'));
        $ok = false;
        $err = '';
        try {
            $headers = 'From: '.$from."\r\n".'Content-Type: text/plain; charset=UTF-8';
            $ok = @mail((string)$row['recipient'], (string)$row['subject'], (string)$row['body'], $headers);
            if (!$ok) $err = 'mail() returned false';
        } catch (Throwable $e) {
            $err = $e->getMessage();
        }
        if ($ok) {
            $db->prepare("UPDATE email_outbox SET status='SENT', sent_at=UTC_TIMESTAMP(), last_error='' WHERE id=?")->execute([$row['id']]);
        } else {
            $db->prepare("UPDATE email_outbox SET status='FAILED', last_error=? WHERE id=?")->execute([substr($err, 0, 500), $row['id']]);
        }
    }
}
