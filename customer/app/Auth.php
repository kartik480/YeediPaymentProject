<?php
declare(strict_types=1);
final class Auth {
    public static function adminOk(): bool { return !empty($_SESSION['admin_user_id']); }
    public static function requireAdmin(): void { if(!self::adminOk()) redirect('/admin/login'); }
    public static function adminLogin(PDO $db, string $username, string $password): bool {
        $key='admin-login:'.hash('sha256',client_ip());
        $st=$db->prepare('SELECT * FROM auth_rate_limits WHERE rate_key=?');$st->execute([$key]);$rl=$st->fetch();
        if($rl && !empty($rl['blocked_until']) && strtotime($rl['blocked_until'])>time()) return false;
        $st=$db->prepare('SELECT id,password_hash,active FROM admin_users WHERE username=? LIMIT 1'); $st->execute([$username]); $u=$st->fetch();
        $ok=(bool)($u && (int)$u['active'] && password_verify($password,$u['password_hash']));
        if(!$ok){
            if(!$rl || strtotime($rl['window_started_at']) < time()-900){$q=$db->prepare('INSERT INTO auth_rate_limits(rate_key,attempts,window_started_at,blocked_until,updated_at) VALUES(?,1,UTC_TIMESTAMP(),NULL,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE attempts=1,window_started_at=UTC_TIMESTAMP(),blocked_until=NULL,updated_at=UTC_TIMESTAMP()');$q->execute([$key]);}
            else{$attempts=(int)$rl['attempts']+1;$block=$attempts>=5?gmdate('Y-m-d H:i:s',time()+900):null;$q=$db->prepare('UPDATE auth_rate_limits SET attempts=?,blocked_until=?,updated_at=UTC_TIMESTAMP() WHERE rate_key=?');$q->execute([$attempts,$block,$key]);}
            return false;
        }
        $db->prepare('DELETE FROM auth_rate_limits WHERE rate_key=?')->execute([$key]);
        session_regenerate_id(true); $_SESSION['admin_user_id']=(int)$u['id']; $_SESSION['admin_username']=$username; return true;
    }
    public static function adminLogout(): void { unset($_SESSION['admin_user_id'],$_SESSION['admin_username']); session_regenerate_id(true); }
    public static function customerLoggedIn(int $customerId): bool { return (int)($_SESSION['customer_id']??0)===$customerId; }
    public static function customerLogin(int $customerId): void { session_regenerate_id(true); $_SESSION['customer_id']=$customerId; }
    public static function customerLogout(): void { unset($_SESSION['customer_id']); session_regenerate_id(true); }
}
