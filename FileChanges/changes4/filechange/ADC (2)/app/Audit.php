<?php
declare(strict_types=1);
final class Audit {
    public static function add(PDO $db, string $actor, string $action, string $detail=''): void {
        $st=$db->prepare('INSERT INTO audit_logs(actor,action,detail,ip_address,created_at) VALUES(?,?,?,?,UTC_TIMESTAMP())');
        $st->execute([$actor,$action,substr($detail,0,5000),client_ip()]);
    }
}
