<?php
declare(strict_types=1);

final class Database {
    private static ?PDO $pdo = null;

    public static function pdo(): PDO {
        if (self::$pdo instanceof PDO) return self::$pdo;
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            Env::get('DB_HOST', '127.0.0.1'),
            Env::get('DB_PORT', '3306'),
            Env::get('DB_NAME', 'yeedipay')
        );
        self::$pdo = new PDO($dsn, (string)Env::get('DB_USER', 'root'), (string)Env::get('DB_PASS', ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return self::$pdo;
    }

    public static function ping(): bool {
        try {
            self::pdo()->query('SELECT 1');
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}
