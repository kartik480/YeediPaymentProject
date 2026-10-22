<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

$root = dirname(__DIR__);
require_once $root.'/app/Env.php';
Env::load($root.'/.env');
require_once $root.'/app/Database.php';
require_once $root.'/app/Helpers.php';
require_once $root.'/app/Crypto.php';
require_once $root.'/app/BankService.php';

$db = Database::pdo();
BankService::ensureSchema($db);

$uri = trim((string)@file_get_contents($root.'/docs/EXACT_INDUSIND_REFERENCE.txt'));
if ($uri === '') {
    $uri = 'upi://pay?pa=Pos.11427319@indus&pn=JAYALAKSHMI%20MUTUALLY%20AIDED%20COOPERATIVE%20THRIFT%20AND%20CREDIT%20SOCIETY%20LTD&cu=INR';
}

$st = $db->prepare("SELECT id FROM bank_profiles WHERE vpa=? OR qr_payload=? OR code='INDUS' LIMIT 1");
$st->execute(['Pos.11427319@indus', $uri]);
$existingId = (int)($st->fetchColumn() ?: 0);

$in = [
    'code' => 'INDUS',
    'bank_name' => 'IndusInd Bank',
    'upi_uri' => $uri,
    'vpa' => 'Pos.11427319@indus',
    'payee_name' => 'JAYALAKSHMI MUTUALLY AIDED COOPERATIVE THRIFT AND CREDIT SOCIETY LTD',
    'enabled' => 1,
    'rotation_order' => 1,
    'rotation_minutes' => 60,
];

if ($existingId > 0) {
    BankService::update($db, $existingId, $in);
    $id = $existingId;
    echo "updated bank id={$id}\n";
} else {
    $id = BankService::add($db, $in);
    echo "created bank id={$id}\n";
}

BankService::applyRouting($db, 'MANUAL', $id);
$bank = BankService::get($db, $id);
echo 'code='.(string)$bank['code'].PHP_EOL;
echo 'vpa='.(string)$bank['vpa'].PHP_EOL;
echo 'qr='.(string)$bank['qr_payload'].PHP_EOL;
echo "active\n";
