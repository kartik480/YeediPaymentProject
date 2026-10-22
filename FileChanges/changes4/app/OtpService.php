<?php
declare(strict_types=1);

final class OtpService {
    private static bool $ready = false;

    public static function ensure(PDO $db): void {
        if (self::$ready) return;
        $db->exec("CREATE TABLE IF NOT EXISTS otp_codes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            customer_id BIGINT UNSIGNED NOT NULL,
            otp_hash VARCHAR(128) NOT NULL,
            expires_at DATETIME NOT NULL,
            consumed TINYINT(1) NOT NULL DEFAULT 0,
            attempts INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            KEY idx_otp_customer (customer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        self::$ready = true;
    }

    public static function create(PDO $db, array $c): string {
        self::ensure($db);
        $st = $db->prepare('SELECT TIMESTAMPDIFF(SECOND, created_at, UTC_TIMESTAMP()) FROM otp_codes WHERE customer_id=? ORDER BY id DESC LIMIT 1');
        $st->execute([(int)$c['id']]);
        $age = $st->fetchColumn();
        if ($age !== false && (int)$age < 45) {
            throw new RuntimeException('Please wait a moment before requesting another OTP.');
        }
        $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $db->prepare('INSERT INTO otp_codes(customer_id,otp_hash,expires_at,consumed,attempts,created_at) VALUES(?,?,DATE_ADD(UTC_TIMESTAMP(), INTERVAL 5 MINUTE),0,0,UTC_TIMESTAMP())')
            ->execute([(int)$c['id'], self::hash((int)$c['id'], $otp)]);
        return $otp;
    }

    /** @return array{0:bool,1:string} */
    public static function verify(PDO $db, array $c, string $otp): array {
        self::ensure($db);
        $otp = preg_replace('/\D+/', '', $otp) ?? '';
        $st = $db->prepare('SELECT * FROM otp_codes WHERE customer_id=? AND consumed=0 ORDER BY id DESC LIMIT 1');
        $st->execute([(int)$c['id']]);
        $row = $st->fetch();
        if (!$row) return [false, 'No active OTP. Request a new one.'];
        $exp = $db->prepare('SELECT expires_at < UTC_TIMESTAMP() FROM otp_codes WHERE id=?');
        $exp->execute([(int)$row['id']]);
        if ((int)$exp->fetchColumn() === 1) return [false, 'OTP expired. Request a new one.'];
        if ((int)$row['attempts'] >= 5) return [false, 'Too many attempts. Request a new OTP.'];
        $db->prepare('UPDATE otp_codes SET attempts=attempts+1 WHERE id=?')->execute([(int)$row['id']]);
        if (!hash_equals((string)$row['otp_hash'], self::hash((int)$c['id'], $otp))) {
            return [false, 'Invalid OTP.'];
        }
        $db->prepare('UPDATE otp_codes SET consumed=1 WHERE id=?')->execute([(int)$row['id']]);
        return [true, 'OK'];
    }

    private static function hash(int $customerId, string $otp): string {
        return hash_hmac('sha256', $customerId.':'.$otp, (string)Env::get('APP_KEY', 'yeedipay'));
    }
}
