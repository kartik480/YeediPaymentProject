<?php
declare(strict_types=1);

final class TicketService {
    private static bool $ready=false;
    public static function ensureSchema(PDO $db): void {
        if(self::$ready)return;
        $db->exec("CREATE TABLE IF NOT EXISTS support_tickets (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            customer_id BIGINT UNSIGNED NOT NULL,
            reference VARCHAR(40) NOT NULL,
            subject VARCHAR(180) NOT NULL,
            category VARCHAR(60) NOT NULL DEFAULT 'GENERAL',
            status VARCHAR(32) NOT NULL DEFAULT 'OPEN',
            priority VARCHAR(20) NOT NULL DEFAULT 'NORMAL',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_ticket_reference(reference), INDEX idx_ticket_customer(customer_id,id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE IF NOT EXISTS support_ticket_replies (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ticket_id BIGINT UNSIGNED NOT NULL,
            sender_type VARCHAR(20) NOT NULL,
            sender_name VARCHAR(180) NOT NULL,
            message TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_ticket_reply(ticket_id,id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        self::$ready=true;
    }
    public static function forCustomer(PDO $db,int $customerId): array { self::ensureSchema($db);$st=$db->prepare('SELECT * FROM support_tickets WHERE customer_id=? ORDER BY id DESC');$st->execute([$customerId]);return $st->fetchAll(); }
    public static function get(PDO $db,int $id): ?array { self::ensureSchema($db);$st=$db->prepare('SELECT * FROM support_tickets WHERE id=?');$st->execute([$id]);$r=$st->fetch();return $r?:null; }
    public static function replies(PDO $db,int $ticketId): array { self::ensureSchema($db);$st=$db->prepare('SELECT * FROM support_ticket_replies WHERE ticket_id=? ORDER BY id');$st->execute([$ticketId]);return $st->fetchAll(); }
    public static function create(PDO $db,array $customer,array $post): array {
        self::ensureSchema($db);$subject=trim((string)($post['subject']??''));$message=trim((string)($post['message']??''));$category=strtoupper(trim((string)($post['category']??'GENERAL')));
        if($subject==='')throw new InvalidArgumentException('Subject is required');if(strlen($subject)>180)$subject=substr($subject,0,180);if($message==='')throw new InvalidArgumentException('Message is required');
        if(!in_array($category,['GENERAL','PAYMENT','WALLET','STATEMENT','BILLING','TECHNICAL'],true))$category='GENERAL';
        $ref='TKT'.gmdate('ymdHis').strtoupper(substr(bin2hex(random_bytes(3)),0,6));
        $db->beginTransaction();try{$db->prepare("INSERT INTO support_tickets(customer_id,reference,subject,category,status,priority,created_at,updated_at) VALUES(?,?,?,?, 'OPEN','NORMAL',UTC_TIMESTAMP(),UTC_TIMESTAMP())")->execute([(int)$customer['id'],$ref,$subject,$category]);$id=(int)$db->lastInsertId();self::addReply($db,$id,'CUSTOMER',(string)$customer['name'],$message);$db->commit();}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
        return self::get($db,$id)??[];
    }
    public static function addReply(PDO $db,int $ticketId,string $senderType,string $senderName,string $message): void {
        self::ensureSchema($db);$message=trim($message);if($message==='')throw new InvalidArgumentException('Reply message is required');
        $db->prepare('INSERT INTO support_ticket_replies(ticket_id,sender_type,sender_name,message,created_at) VALUES(?,?,?,?,UTC_TIMESTAMP())')->execute([$ticketId,strtoupper($senderType),substr(trim($senderName),0,180),$message]);
        $db->prepare('UPDATE support_tickets SET updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$ticketId]);
    }
}
