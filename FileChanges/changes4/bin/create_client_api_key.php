<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

require_once dirname(__DIR__).'/app/bootstrap.php';

$clientName = trim((string)($argv[1] ?? ''));
$customerCode = trim((string)($argv[2] ?? 'CUST0003'));
if ($clientName === '') {
    fwrite(STDERR, "Usage: php bin/create_client_api_key.php \"Client Name\" [CUSTOMER_CODE]\n");
    exit(1);
}

$created = ClientApiService::createKey(Database::pdo(), $clientName, $customerCode);
echo "API key created. Copy it now; only its hash is stored in MySQL.\n";
echo 'Client: '.$clientName.PHP_EOL;
echo 'Customer: '.$created['customer_code'].PHP_EOL;
echo 'API_KEY='.$created['api_key'].PHP_EOL;
