<?php
declare(strict_types=1);

final class OtpService {
    private static bool $ready=false;
    public static function ensureSchema(PDO $db): void {
        if(self::$ready) return;
        $db->exec("CREATE TABLE IF NOT EXISTS customer_otps (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            customer_id BIGINT UNSIGNED NOT NULL,
            otp_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            attempts INT NOT NULL DEFAULT 0,
            consumed_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_customer_otp(customer_id,id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        self::$ready=true;
    }
    public static function create(PDO $db,array $customer): string {
        self::ensureSchema($db);
        $id=(int)($customer['id']??0); if($id<=0) throw new InvalidArgumentException('Invalid customer');
        $otp=(string)random_int(100000,999999);
        $hash=password_hash($otp,PASSWORD_DEFAULT);
        $db->prepare('UPDATE customer_otps SET consumed_at=UTC_TIMESTAMP() WHERE customer_id=? AND consumed_at IS NULL')->execute([$id]);
        $db->prepare('INSERT INTO customer_otps(customer_id,otp_hash,expires_at,attempts,created_at) VALUES(?,?,DATE_ADD(UTC_TIMESTAMP(), INTERVAL 5 MINUTE),0,UTC_TIMESTAMP())')->execute([$id,$hash]);
        return $otp;
    }
    /** @return array{0:bool,1:string} */
    public static function verify(PDO $db,array $customer,string $otp): array {
        self::ensureSchema($db);
        if(!preg_match('/^\d{6}$/',$otp)) return [false,'Enter the 6-digit OTP'];
        $st=$db->prepare('SELECT * FROM customer_otps WHERE customer_id=? AND consumed_at IS NULL ORDER BY id DESC LIMIT 1');
        $st->execute([(int)$customer['id']]); $row=$st->fetch();
        if(!$row) return [false,'OTP expired. Request a new OTP.'];
        if(strtotime((string)$row['expires_at'].' UTC')<time()) return [false,'OTP expired. Request a new OTP.'];
        if((int)$row['attempts']>=5) return [false,'Too many attempts. Request a new OTP.'];
        if(!password_verify($otp,(string)$row['otp_hash'])){
            $db->prepare('UPDATE customer_otps SET attempts=attempts+1 WHERE id=?')->execute([(int)$row['id']]);
            return [false,'Incorrect OTP'];
        }
        $db->prepare('UPDATE customer_otps SET consumed_at=UTC_TIMESTAMP() WHERE id=?')->execute([(int)$row['id']]);
        return [true,'Verified'];
    }
}
