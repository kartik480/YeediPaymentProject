<?php
declare(strict_types=1);
require_once __DIR__.'/../app/bootstrap.php';
BankService::ensureSchema(Database::pdo());
foreach (Database::pdo()->query('SHOW COLUMNS FROM bank_profiles') as $c) {
    echo $c['Field'].PHP_EOL;
}
echo "schema-ok\n";
