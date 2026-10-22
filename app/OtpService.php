<?php
declare(strict_types=1);
final class OtpService {
    private static function hash(int $cid,string $otp): string { return hash_hmac('sha256',$cid.':'.$otp,(string)Env::get('APP_KEY','change-me')); }
    public static function create(PDO $db,array $c): string {
        $st=$db->prepare('SELECT created_at FROM otp_codes WHERE customer_id=? ORDER BY id DESC LIMIT 1');$st->execute([$c['id']]);$last=$st->fetchColumn();if($last && time()-strtotime($last)<45)throw new RuntimeException('Please wait before requesting another OTP.');
        $otp=str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);$q=$db->prepare("INSERT INTO otp_codes(customer_id,otp_hash,expires_at,consumed,attempts,created_at) VALUES(?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 5 MINUTE),0,0,UTC_TIMESTAMP())");$q->execute([$c['id'],self::hash((int)$c['id'],$otp)]);return $otp;
    }
    public static function verify(PDO $db,array $c,string $otp): array {
        $st=$db->prepare('SELECT * FROM otp_codes WHERE customer_id=? AND consumed=0 ORDER BY id DESC LIMIT 1');$st->execute([$c['id']]);$r=$st->fetch();if(!$r)return [false,'No active OTP. Request a new OTP.'];if(strtotime($r['expires_at'])<time())return [false,'OTP expired.'];if((int)$r['attempts']>=5)return [false,'Too many attempts. Request a new OTP.'];$db->prepare('UPDATE otp_codes SET attempts=attempts+1 WHERE id=?')->execute([$r['id']]);if(!hash_equals($r['otp_hash'],self::hash((int)$c['id'],$otp)))return [false,'Invalid OTP.'];$db->prepare('UPDATE otp_codes SET consumed=1 WHERE id=?')->execute([$r['id']]);return [true,'OK'];
    }
}
