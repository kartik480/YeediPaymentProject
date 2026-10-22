<?php
declare(strict_types=1);

define('YEEDIPAY_ROOT', dirname(__DIR__));

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secureCookie = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
        || (string)($_SERVER['SERVER_PORT'] ?? '') === '443';
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__.'/Env.php';
Env::load(YEEDIPAY_ROOT.'/.env');

require_once __DIR__.'/Helpers.php';
require_once __DIR__.'/Database.php';
require_once __DIR__.'/Runtime.php';
require_once __DIR__.'/BankService.php';
require_once __DIR__.'/BankSmsService.php';
require_once __DIR__.'/TransactionService.php';
require_once __DIR__.'/PdfDocument.php';
require_once __DIR__.'/InvoiceService.php';
require_once __DIR__.'/ReconciliationService.php';
require_once __DIR__.'/OtpService.php';
require_once __DIR__.'/StatementService.php';
require_once __DIR__.'/BillingService.php';
require_once __DIR__.'/TicketService.php';
require_once __DIR__.'/WalletService.php';
require_once __DIR__.'/WebhookService.php';
require_once __DIR__.'/MerchantApiService.php';
