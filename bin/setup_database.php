<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

require_once __DIR__.'/../app/Env.php';
Env::load(dirname(__DIR__).'/.env');

$host = Env::get('DB_HOST', '127.0.0.1');
$port = Env::get('DB_PORT', '3306');
$name = Env::get('DB_NAME', 'yeedipay');
$user = Env::get('DB_USER', 'root');
$pass = Env::get('DB_PASS', '');

$opts = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

function run_sql_file(PDO $db, string $path): void {
    if (!is_file($path)) {
        echo "skip missing {$path}\n";
        return;
    }
    $sql = (string)file_get_contents($path);
    $parts = preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '' || str_starts_with($part, '--')) {
            continue;
        }
        try {
            $db->exec($part);
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            $ok = str_contains($msg, 'already exists')
                || str_contains($msg, 'Duplicate column')
                || str_contains($msg, 'Duplicate key name')
                || str_contains($msg, 'Duplicate foreign key')
                || str_contains($msg, 'Multiple primary key')
                || str_contains($msg, 'errno: 121');
            if ($ok) {
                echo "skip: ".$msg."\n";
                continue;
            }
            throw $e;
        }
    }
    echo "applied ".basename($path)."\n";
}

$dsnServer = "mysql:host={$host};port={$port};charset=utf8mb4";
$pdo = new PDO($dsnServer, $user, $pass, $opts);
$pdo->exec('CREATE DATABASE IF NOT EXISTS `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
echo "database {$name} ready\n";
$pdo->exec('USE `'.$name.'`');

$root = dirname(__DIR__);
run_sql_file($pdo, $root.'/database/schema.sql');
$migrates = glob($root.'/database/migrate_*.sql') ?: [];
sort($migrates);
foreach ($migrates as $file) {
    run_sql_file($pdo, $file);
}

require_once $root.'/app/bootstrap.php';
$db = Database::pdo();
BankService::ensureSchema($db);
TransactionService::ensureSchema($db);
SettlementService::ensureSchema($db);
EmailService::ensureSchema($db);
BillingService::ensureSchema($db);
GstService::ensureSchema($db);
PricingService::ensureSchema($db);
ConnectorService::ensureSchema($db);
BankVerificationService::ensureSchema($db);
KybService::ensureSchema($db);
SuperAdminService::ensureCustomerSchema($db);
SuperAdminService::ensureAdminUserSchema($db);
TicketService::ensureSchema($db);
WalletService::ensureSchema($db);
BankSmsService::ensureSchema($db);
echo "ensureSchema ok\n";

$adminUser = 'admin';
$adminPass = 'YeedipayAdmin1';
$st = $db->prepare('INSERT INTO admin_users(username,password_hash,active,created_at,updated_at) VALUES(?,?,1,UTC_TIMESTAMP(),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE active=1,updated_at=UTC_TIMESTAMP()');
$st->execute([$adminUser, password_hash($adminPass, PASSWORD_DEFAULT)]);
echo "admin user ready: {$adminUser}\n";
echo "done\n";
