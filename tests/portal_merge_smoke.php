<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/app/bootstrap.php';

$errors = [];
$ok = static function (bool $pass, string $msg) use (&$errors): void {
    if (!$pass) $errors[] = $msg;
};

$ok(class_exists('Portal'), 'Portal class missing');
$ok(class_exists('ClientApiService'), 'ClientApiService class missing');
$ok(method_exists('TransactionService', 'refreshCustomerPayment'), 'TransactionService::refreshCustomerPayment missing');
$ok(method_exists('TransactionService', 'statusPayload'), 'TransactionService::statusPayload missing');
$ok(method_exists('InvoiceService', 'printPage'), 'InvoiceService::printPage missing');
$ok(is_file(dirname(__DIR__).'/customer/public/portal_routes.php'), 'portal_routes.php missing');
$ok(is_file(dirname(__DIR__).'/customer/public/assets/portal.css'), 'portal.css missing');
$ok(is_file(dirname(__DIR__).'/customer/views/login.php'), 'portal login view missing');

$ref = new ReflectionMethod('Portal', 'findCustomer');
$ok($ref->isStatic(), 'Portal::findCustomer should be static');

if ($errors) {
    fwrite(STDERR, "FAIL portal_merge_smoke\n - ".implode("\n - ", $errors)."\n");
    exit(1);
}
echo "PASS portal_merge_smoke\n";
