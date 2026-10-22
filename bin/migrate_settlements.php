<?php
declare(strict_types=1);
require_once __DIR__.'/../app/bootstrap.php';
SettlementService::ensureSchema(Database::pdo());
echo "settlements-schema-ok\n";
