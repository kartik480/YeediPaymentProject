<?php
declare(strict_types=1);
require_once __DIR__.'/../app/bootstrap.php';
TransactionService::ensureSchema(Database::pdo());
echo "transactions-schema-ok\n";
