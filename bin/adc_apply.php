<?php
require dirname(__DIR__).'/app/Env.php';
Env::load(dirname(__DIR__).'/.env');
require dirname(__DIR__).'/app/Database.php';
require dirname(__DIR__).'/app/AdcLink.php';
$db = Database::pdo();
AdcLink::ensureSchema($db);
echo 'db='.$db->query('SELECT DATABASE()')->fetchColumn()."\n";
foreach (['sms_relay_devices','merchant_clients','bank_receiving_accounts','qr_payment_orders','bank_sms_events','portal_bank_sms_events'] as $t) {
    $n = $db->query("SHOW TABLES LIKE ".$db->quote($t))->fetchColumn();
    echo $t.'='.($n ? 'YES' : 'NO')."\n";
}
foreach ($db->query('SELECT account_key, bank_name, upi_id, active FROM bank_receiving_accounts') as $r) {
    echo 'account '.$r['account_key'].' '.$r['bank_name'].' '.$r['upi_id'].' active='.$r['active']."\n";
}
$d = $db->query('SELECT device_id FROM sms_relay_devices LIMIT 1')->fetch();
echo 'device='.($d['device_id'] ?? '')."\n";
