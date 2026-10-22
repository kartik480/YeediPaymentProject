<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

require_once dirname(__DIR__).'/app/bootstrap.php';

$db = Database::pdo();
$dumpFile = dirname(__DIR__).'/ypay (4).sql';
if (!is_file($dumpFile)) {
    throw new RuntimeException('ypay (4).sql was not found');
}

$dump = (string)file_get_contents($dumpFile);
$withoutComments = preg_replace('/^\s*--.*$/m', '', $dump) ?? $dump;
$statements = preg_split('/;\s*(?:\r?\n|$)/', $withoutComments) ?: [];
$existing = array_fill_keys($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN), true);
$missing = [];

foreach ($statements as $statement) {
    if (preg_match('/^\s*CREATE TABLE\s+`([^`]+)`/i', $statement, $match)
        && !isset($existing[$match[1]])) {
        $missing[$match[1]] = true;
    }
}

$created = 0;
$altered = 0;
$db->exec('SET FOREIGN_KEY_CHECKS=0');
try {
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if ($statement === '') continue;
        if (preg_match('/^CREATE TABLE\s+`([^`]+)`/i', $statement, $match)
            && isset($missing[$match[1]])) {
            $statement = preg_replace('/^CREATE TABLE\s+/i', 'CREATE TABLE IF NOT EXISTS ', $statement, 1) ?? $statement;
            $db->exec($statement);
            $created++;
        }
    }
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if ($statement === '') continue;
        if (preg_match('/^ALTER TABLE\s+`([^`]+)`/i', $statement, $match)
            && isset($missing[$match[1]])) {
            $db->exec($statement);
            $altered++;
        }
    }
} finally {
    $db->exec('SET FOREIGN_KEY_CHECKS=1');
}

BankService::ensureSchema($db);

$vpa = 'msgreenrisingagrilimited.eazypay@icici';
$payee = 'K K K ENTERPRISES';
$payload = 'upi://pay?pa=msgreenrisingagrilimited.eazypay@icici&pn=&tr=EZYS9640943427&cu=INR&mc=1520';
$params = json_encode([
    'pa' => $vpa,
    'pn' => '',
    'tr' => 'EZYS9640943427',
    'cu' => 'INR',
    'mc' => '1520',
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

$find = $db->prepare("SELECT id, webhook_secret FROM bank_profiles WHERE code='KKKENT' LIMIT 1");
$find->execute();
$bank = $find->fetch();
if ($bank) {
    $bankId = (int)$bank['id'];
    $db->prepare(
        "UPDATE bank_profiles
         SET bank_name='ICICI Bank', label='K K K ENTERPRISES', vpa=?, payee_name=?,
             currency='INR', qr_payload=?, qr_params_json=?, enabled=1,
             rotation_order=1, rotation_minutes=60, signed_qr=1, mcc='1520'
         WHERE id=?"
    )->execute([$vpa, $payee, $payload, $params, $bankId]);
} else {
    $secret = bin2hex(random_bytes(24));
    $db->prepare(
        "INSERT INTO bank_profiles
         (code, bank_name, label, vpa, payee_name, currency, qr_payload,
          qr_params_json, enabled, rotation_order, rotation_minutes, signed_qr,
          webhook_secret, mcc, success_values, failed_values, created_at)
         VALUES ('KKKENT','ICICI Bank','K K K ENTERPRISES',?,?,'INR',?,?,
                 1,1,60,1,?,'1520','SUCCESS,SUCCESSFUL,PAID,COMPLETED',
                 'FAILED,FAILURE,DECLINED,REVERSED',UTC_TIMESTAMP())"
    )->execute([$vpa, $payee, $payload, $params, $secret]);
    $bankId = (int)$db->lastInsertId();
}

$config = $db->query('SELECT id FROM system_config WHERE id=1')->fetchColumn();
if ($config) {
    $db->prepare(
        'UPDATE system_config
         SET manual_active_bank_id=?, rotation_enabled=0, updated_at=UTC_TIMESTAMP()
         WHERE id=1'
    )->execute([$bankId]);
} else {
    $db->prepare(
        'INSERT INTO system_config
         (id, rotation_enabled, manual_active_bank_id, updated_at)
         VALUES (1,0,?,UTC_TIMESTAMP())'
    )->execute([$bankId]);
}

AdcLink::ensureSchema($db);
$db->prepare(
    "UPDATE bank_receiving_accounts
     SET bank_name='ICICI', account_label=?, account_last4='4270',
         upi_id=?, payee_name=?, active=1
     WHERE account_key='KKKENT'"
)->execute([$payee, $vpa, $payee]);
BankSmsService::ensureSchema($db);

echo 'Database: '.$db->query('SELECT DATABASE()')->fetchColumn().PHP_EOL;
echo "Imported missing ypay tables: {$created}; applied table alterations: {$altered}".PHP_EOL;
echo "Active bank: KKKENT / ICICI Bank / {$vpa}".PHP_EOL;
echo "QR payload: {$payload}".PHP_EOL;
echo "ADC payment confirmation schema is ready.".PHP_EOL;
