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

$vpa = 'msgreenrisingagrilimited.eazypay@icici';
$payee = 'SEEDS ORGANIC';
$uri = 'upi://pay?pa='.rawurlencode($vpa).'&pn='.rawurlencode($payee).'&cu=INR';
$imgRel = 'storage/bank_qr/icici_seeds_organic.png';
$imgAbs = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $imgRel);
if (!is_file($imgAbs)) {
    fwrite(STDERR, "Missing QR image: {$imgAbs}\n");
    exit(1);
}

$st = $db->prepare("SELECT id FROM bank_profiles WHERE vpa=? OR code='ICICI' LIMIT 1");
$st->execute([$vpa]);
$existingId = (int)($st->fetchColumn() ?: 0);

$in = [
    'code' => 'ICICI',
    'bank_name' => 'ICICI Bank',
    'upi_uri' => $uri,
    'vpa' => $vpa,
    'payee_name' => $payee,
    'enabled' => 1,
    'rotation_order' => 2,
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

$db->prepare('UPDATE bank_profiles SET qr_image_path=?, signed_qr=1, payee_name=?, qr_payload=?, qr_params_json=? WHERE id=?')->execute([
    $imgRel,
    $payee,
    $uri,
    json_encode(['pa' => $vpa, 'pn' => $payee, 'cu' => 'INR'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    $id,
]);

$bank = BankService::get($db, $id);
echo 'code='.(string)$bank['code'].PHP_EOL;
echo 'vpa='.(string)$bank['vpa'].PHP_EOL;
echo 'payee='.(string)$bank['payee_name'].PHP_EOL;
echo 'qr='.(string)$bank['qr_payload'].PHP_EOL;
echo 'image='.(string)$bank['qr_image_path'].PHP_EOL;
echo 'signed='.(string)$bank['signed_qr'].PHP_EOL;
echo "ok\n";
