<?php
declare(strict_types=1);

if (!defined('YEEDIPAY_ROOT')) {
    define('YEEDIPAY_ROOT', __DIR__ . '/..');
}
require_once __DIR__ . '/Env.php';
Env::load(YEEDIPAY_ROOT . '/.env');

date_default_timezone_set(Env::get('APP_TIMEZONE', 'Asia/Kolkata'));
if (Env::get('APP_ENV','production') === 'production') { ini_set('display_errors','0'); ini_set('log_errors','1'); }

$secure = filter_var(Env::get('SESSION_COOKIE_SECURE', 'true'), FILTER_VALIDATE_BOOLEAN);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('YEEDIPAYSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Helpers.php';
require_once __DIR__ . '/Crypto.php';
require_once __DIR__ . '/Audit.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/BankService.php';
require_once __DIR__ . '/LimitService.php';
require_once __DIR__ . '/SmsService.php';
require_once __DIR__ . '/OtpService.php';
require_once __DIR__ . '/TransactionService.php';
require_once __DIR__ . '/BankSmsService.php';
require_once __DIR__ . '/AdcLink.php';
require_once __DIR__ . '/ReconciliationService.php';
require_once __DIR__ . '/PaymentConfirmService.php';
require_once __DIR__ . '/EmailService.php';
require_once __DIR__ . '/SettlementService.php';
require_once __DIR__ . '/TenantService.php';
require_once __DIR__ . '/PricingService.php';
require_once __DIR__ . '/ConnectorService.php';
require_once __DIR__ . '/BankVerificationService.php';
require_once __DIR__ . '/KybService.php';
require_once __DIR__ . '/PdfDocument.php';
require_once __DIR__ . '/StatementService.php';
require_once __DIR__ . '/InvoiceService.php';
require_once __DIR__ . '/SuperAdminService.php';
require_once __DIR__ . '/BillingService.php';
require_once __DIR__ . '/GstService.php';
require_once __DIR__ . '/ReportService.php';
require_once __DIR__ . '/TicketService.php';
require_once __DIR__ . '/WalletService.php';
