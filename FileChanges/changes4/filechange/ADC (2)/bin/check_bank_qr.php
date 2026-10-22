<?php
require __DIR__.'/../app/bootstrap.php';
$db = Database::pdo();
$ref = $argv[1] ?? '';
if ($ref === '') {
    $row = $db->query('SELECT reference FROM transactions ORDER BY id DESC LIMIT 1')->fetch();
    $ref = (string)($row['reference'] ?? '');
}
$t = TransactionService::txnByRef($db, $ref);
if (!$t) {
    echo "no txn\n";
    exit(1);
}
echo 'ref='.$t['reference'].PHP_EOL;
echo 'bank_id='.$t['bank_id'].PHP_EOL;
echo 'img='.($t['qr_image_path'] ?? '').PHP_EOL;
echo 'uses='.(TransactionService::usesUploadedBankQr($t) ? 'yes' : 'no').PHP_EOL;
echo 'url='.TransactionService::bankQrUrl($t).PHP_EOL;
echo 'file='.(is_file(dirname(__DIR__).'/'.str_replace('/', DIRECTORY_SEPARATOR, (string)$t['qr_image_path'])) ? 'ok' : 'missing').PHP_EOL;
