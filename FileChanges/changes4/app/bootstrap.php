<?php
declare(strict_types=1);

define('YEEDIPAY_ROOT', dirname(__DIR__));

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__.'/Env.php';
Env::load(YEEDIPAY_ROOT.'/.env');

require_once __DIR__.'/Helpers.php';
require_once __DIR__.'/Database.php';
require_once __DIR__.'/Runtime.php';
require_once __DIR__.'/AdcLink.php';
require_once __DIR__.'/BankService.php';
require_once __DIR__.'/BankSmsService.php';
require_once __DIR__.'/TransactionService.php';
require_once __DIR__.'/PdfDocument.php';
require_once __DIR__.'/InvoiceService.php';
require_once __DIR__.'/OtpService.php';
require_once __DIR__.'/TicketService.php';
require_once __DIR__.'/Portal.php';
require_once __DIR__.'/ReconciliationService.php';
require_once __DIR__.'/ClientApiService.php';
