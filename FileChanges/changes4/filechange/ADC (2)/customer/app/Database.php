<?php
declare(strict_types=1);
final class Database {
    private static ?PDO $pdo = null;
    public static function pdo(): PDO {
        if (self::$pdo) return self::$pdo;
        $host = Env::get('DB_HOST','127.0.0.1');
        $port = Env::get('DB_PORT','3306');
        $name = Env::get('DB_NAME','yeedipay');
        $user = Env::get('DB_USER','root');
        $pass = Env::get('DB_PASS','');
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        self::$pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        self::$pdo->exec("SET time_zone = '+00:00'");
        return self::$pdo;
    }
}
