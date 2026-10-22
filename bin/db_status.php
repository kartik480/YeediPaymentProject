<?php
declare(strict_types=1);
require_once __DIR__.'/../app/bootstrap.php';
$db = Database::pdo();
$tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
echo "Database: ".Env::get('DB_NAME')."\n";
echo "Tables: ".count($tables)."\n\n";
$check = ['customers','transactions','support_tickets','wallet_balances','wallet_recharges','withdrawal_requests','admin_users'];
foreach ($check as $t) {
    echo (in_array($t, $tables, true) ? '[OK]' : '[MISSING]')." {$t}\n";
}
$c = (int)$db->query('SELECT COUNT(*) FROM customers')->fetchColumn();
$a = (int)$db->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
echo "\nCustomers: {$c}\nAdmin users: {$a}\n";
