<?php
declare(strict_types=1);

/** @var PDO $db */
/** @var string $path */
/** @var string $method */

if (!function_exists('account_customer_list')) {
    function account_customer_list(PDO $db): array {
        return $db->query('SELECT id, code, name FROM customers ORDER BY name ASC, id DESC')->fetchAll();
    }

    function account_view_vars(array $c, array $customers): array {
        return [
            'c' => $c,
            'admin_panel' => true,
            'custBase' => '/admin/accounts/'.$c['code'],
            'account_customers' => $customers,
            'pay_ui' => true,
            'dash_ui' => true,
        ];
    }
}

if ($path === '/admin/accounts' && $method === 'GET') {
    $code = normalize_code((string)($_GET['code'] ?? ''));
    $go = strtolower(trim((string)($_GET['go'] ?? 'dashboard')));
    $suffix = match ($go) {
        'statement' => '/statement',
        'billing' => '/billing',
        'wallet' => '/wallet',
        default => '',
    };
    if ($code !== '') {
        $c = TransactionService::customerByCode($db, $code);
        if (!$c) {
            throw new RuntimeException('Customer not found');
        }
        redirect('/admin/accounts/'.$c['code'].$suffix);
    }
    $customers = $db->query('SELECT * FROM customers ORDER BY name ASC, id DESC LIMIT 500')->fetchAll();
    $module = $go;
    view(__DIR__.'/../views/accounts_pick.php', compact('customers', 'module') + ['title' => 'Customer accounts']);
}

if (preg_match('#^/admin/accounts/([A-Z0-9]+)$#', $path, $m) && $method === 'GET') {
    TransactionService::ensureSchema($db);
    $c = TransactionService::customerByCode($db, $m[1]);
    if (!$c) {
        throw new RuntimeException('Customer not found');
    }
    $period = strtolower(trim((string)($_GET['period'] ?? 'all')));
    if ($period === '') {
        $period = 'all';
    }
    $status = strtoupper(trim((string)($_GET['status'] ?? '')));
    $start = trim((string)($_GET['start'] ?? ''));
    $end = trim((string)($_GET['end'] ?? ''));
    $queryPeriod = $period === 'all' ? 'custom' : $period;
    if ($period === 'all' && $start === '' && $end === '') {
        $start = '2000-01-01';
        $end = date('Y-m-d');
    }
    $txns = TransactionService::query($db, (int)$c['id'], $queryPeriod, $status, null, $start, $end, 1000);
    $totals = TransactionService::collectionTotals($db, (int)$c['id']);
    view(__DIR__.'/../../customer/views/dashboard.php', account_view_vars($c, account_customer_list($db)) + compact('period', 'status', 'start', 'end', 'txns', 'totals') + ['title' => 'Collections', 'nav' => 'dashboard']);
}

if (preg_match('#^/admin/accounts/([A-Z0-9]+)/statement$#', $path, $m) && $method === 'GET') {
    TransactionService::ensureSchema($db);
    $c = TransactionService::customerByCode($db, $m[1]);
    if (!$c) {
        throw new RuntimeException('Customer not found');
    }
    $start = trim((string)($_GET['start'] ?? ''));
    $end = trim((string)($_GET['end'] ?? ''));
    $totals = TransactionService::collectionTotals($db, (int)$c['id']);
    $rows = StatementService::ledgerRows($db, (int)$c['id'], $start, $end);
    view(__DIR__.'/../../customer/views/statement.php', account_view_vars($c, account_customer_list($db)) + compact('start', 'end', 'totals', 'rows') + ['title' => 'Account Statement', 'nav' => 'statement']);
}

if (preg_match('#^/admin/accounts/([A-Z0-9]+)/statement\.csv$#', $path, $m) && $method === 'GET') {
    $c = TransactionService::customerByCode($db, $m[1]);
    if (!$c) {
        http_response_code(404);
        exit('Customer not found');
    }
    $rows = TransactionService::query($db, (int)$c['id'], $_GET['period'] ?? 'month', strtoupper((string)($_GET['status'] ?? '')), ($_GET['bank_id'] ?? '') !== '' ? (int)$_GET['bank_id'] : null, $_GET['start'] ?? '', $_GET['end'] ?? '', 5000);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$c['code'].'-statement.csv"');
    $o = fopen('php://output', 'wb');
    fwrite($o, "\xEF\xBB\xBF");
    fputcsv($o, ['date', 'bank', 'reference', 'status', 'requested_amount', 'confirmed_amount', 'customer_reported_utr', 'upi_txn_id', 'rrn', 'payer_vpa', 'source', 'limit_breach']);
    foreach ($rows as $r) {
        fputcsv($o, [$r['created_at'], $r['bank_name'], $r['reference'], $r['status'], $r['requested_amount'], $r['confirmed_amount'], $r['customer_reported_utr'], $r['upi_txn_id'], $r['rrn'], $r['payer_vpa'], $r['confirmation_source'], $r['limit_breach']]);
    }
    exit;
}

if (preg_match('#^/admin/accounts/([A-Z0-9]+)/statement\.pdf$#', $path, $m) && $method === 'GET') {
    $c = TransactionService::customerByCode($db, $m[1]);
    if (!$c) {
        http_response_code(404);
        exit('Customer not found');
    }
    $f = StatementService::filtersFromGet();
    StatementService::download($c, StatementService::rows($db, (int)$c['id'], $f), $f);
}

if (preg_match('#^/admin/accounts/([A-Z0-9]+)/billing$#', $path, $m) && $method === 'GET') {
    TransactionService::ensureSchema($db);
    $c = TransactionService::customerByCode($db, $m[1]);
    if (!$c) {
        throw new RuntimeException('Customer not found');
    }
    $summary = BillingService::customerPlatformSummary($db, (int)$c['id']);
    $entries = BillingService::customerFeeEntries($db, (int)$c['id']);
    view(__DIR__.'/../../customer/views/billing.php', account_view_vars($c, account_customer_list($db)) + compact('summary', 'entries') + ['title' => 'Platform Fees & GST', 'nav' => 'billing']);
}

if (preg_match('#^/admin/accounts/([A-Z0-9]+)/billing\.csv$#', $path, $m) && $method === 'GET') {
    $c = TransactionService::customerByCode($db, $m[1]);
    if (!$c) {
        http_response_code(404);
        exit('Customer not found');
    }
    BillingService::downloadCustomerFeesCsv($c, BillingService::customerFeeEntries($db, (int)$c['id']));
}

if (preg_match('#^/admin/accounts/([A-Z0-9]+)/billing\.pdf$#', $path, $m) && $method === 'GET') {
    $c = TransactionService::customerByCode($db, $m[1]);
    if (!$c) {
        http_response_code(404);
        exit('Customer not found');
    }
    $summary = BillingService::customerPlatformSummary($db, (int)$c['id']);
    BillingService::downloadCustomerFeesPdf($c, $summary, BillingService::customerFeeEntries($db, (int)$c['id']));
}

if (preg_match('#^/admin/accounts/([A-Z0-9]+)/wallet$#', $path, $m) && $method === 'GET') {
    WalletService::ensureSchema($db);
    $c = TransactionService::customerByCode($db, $m[1]);
    if (!$c) {
        throw new RuntimeException('Customer not found');
    }
    $balance = WalletService::balance($db, (int)$c['id']);
    $recharges = WalletService::rechargesForCustomer($db, (int)$c['id']);
    $withdrawals = WalletService::withdrawalsForCustomer($db, (int)$c['id']);
    view(__DIR__.'/../../customer/views/wallet.php', account_view_vars($c, account_customer_list($db)) + compact('balance', 'recharges', 'withdrawals') + ['title' => 'Wallet', 'nav' => 'wallet']);
}

if (preg_match('#^/admin/accounts/([A-Z0-9]+)/wallet/recharge$#', $path, $m) && $method === 'GET') {
    WalletService::ensureSchema($db);
    $c = TransactionService::customerByCode($db, $m[1]);
    if (!$c) {
        throw new RuntimeException('Customer not found');
    }
    $bank = BankService::active($db);
    view(__DIR__.'/../../customer/views/wallet_recharge.php', account_view_vars($c, account_customer_list($db)) + compact('bank') + ['title' => 'Recharge Wallet', 'nav' => 'wallet', 'presets' => TransactionService::PRESET_AMOUNTS]);
}

if (preg_match('#^/admin/accounts/([A-Z0-9]+)/wallet/recharge$#', $path, $m) && $method === 'POST') {
    require_csrf();
    $c = TransactionService::customerByCode($db, $m[1]);
    if (!$c || !(int)$c['active']) {
        throw new RuntimeException('Customer not found or disabled');
    }
    $bank = BankService::active($db);
    try {
        $amount = TransactionService::parsePresetAmount($_POST['amount'] ?? '');
        $recharge = WalletService::startRecharge($db, $c, $amount);
        redirect('/admin/accounts/'.$c['code'].'/wallet/recharge/'.$recharge['txn_reference']);
    } catch (Throwable $e) {
        view(__DIR__.'/../../customer/views/wallet_recharge.php', account_view_vars($c, account_customer_list($db)) + compact('bank') + ['title' => 'Recharge Wallet', 'nav' => 'wallet', 'presets' => TransactionService::PRESET_AMOUNTS, 'error' => $e->getMessage()]);
    }
}

if (preg_match('#^/admin/accounts/([A-Z0-9]+)/wallet/recharge/([A-Z0-9]+)$#', $path, $m) && $method === 'GET') {
    WalletService::ensureSchema($db);
    $c = TransactionService::customerByCode($db, $m[1]);
    if (!$c) {
        throw new RuntimeException('Customer not found');
    }
    $recharge = WalletService::getRechargeByTxnRef($db, $m[2]);
    if (!$recharge || (int)$recharge['customer_id'] !== (int)$c['id']) {
        throw new RuntimeException('Recharge not found');
    }
    $t = TransactionService::txnByRef($db, $m[2]);
    if (!$t) {
        throw new RuntimeException('Payment not found');
    }
    $t = PaymentConfirmService::refresh($db, $t);
    view(__DIR__.'/../../customer/views/wallet_recharge_pay.php', account_view_vars($c, account_customer_list($db)) + compact('t', 'recharge') + ['title' => 'Wallet Recharge', 'nav' => 'wallet']);
}

if (preg_match('#^/admin/accounts/([A-Z0-9]+)/wallet/recharge/([A-Z0-9]+)/utr$#', $path, $m) && $method === 'POST') {
    require_csrf();
    $c = TransactionService::customerByCode($db, $m[1]);
    if (!$c) {
        throw new RuntimeException('Customer not found');
    }
    $recharge = WalletService::getRechargeByTxnRef($db, $m[2]);
    if (!$recharge || (int)$recharge['customer_id'] !== (int)$c['id']) {
        throw new RuntimeException('Recharge not found');
    }
    [$ok, $msg] = TransactionService::reportUtr($db, $m[2], (string)($_POST['utr'] ?? ''), $_FILES['screenshot'] ?? [], (string)($_POST['email'] ?? ''));
    $t = TransactionService::txnByRef($db, $m[2]);
    if (!$t) {
        throw new RuntimeException('Payment not found');
    }
    view(__DIR__.'/../../customer/views/wallet_recharge_pay.php', account_view_vars($c, account_customer_list($db)) + compact('t', 'recharge') + ['title' => 'Wallet Recharge', 'nav' => 'wallet', 'utr_error' => $ok ? null : $msg]);
}

if (preg_match('#^/admin/accounts/([A-Z0-9]+)/wallet/withdraw$#', $path, $m) && $method === 'GET') {
    WalletService::ensureSchema($db);
    $c = TransactionService::customerByCode($db, $m[1]);
    if (!$c) {
        throw new RuntimeException('Customer not found');
    }
    $balance = WalletService::balance($db, (int)$c['id']);
    view(__DIR__.'/../../customer/views/wallet_withdraw.php', account_view_vars($c, account_customer_list($db)) + compact('balance') + ['title' => 'Withdraw', 'nav' => 'wallet']);
}

if (preg_match('#^/admin/accounts/([A-Z0-9]+)/wallet/withdraw$#', $path, $m) && $method === 'POST') {
    require_csrf();
    $c = TransactionService::customerByCode($db, $m[1]);
    if (!$c) {
        throw new RuntimeException('Customer not found');
    }
    $balance = WalletService::balance($db, (int)$c['id']);
    try {
        $wd = WalletService::createWithdrawal($db, $c, $_POST);
        redirect('/admin/accounts/'.$c['code'].'/wallet?withdraw='.$wd['reference']);
    } catch (Throwable $e) {
        view(__DIR__.'/../../customer/views/wallet_withdraw.php', account_view_vars($c, account_customer_list($db)) + compact('balance') + ['title' => 'Withdraw', 'nav' => 'wallet', 'error' => $e->getMessage()]);
    }
}
