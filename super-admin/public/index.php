<?php
declare(strict_types=1);
if (!defined('YEEDIPAY_SURFACE')) define('YEEDIPAY_SURFACE', 'super-admin');
require_once __DIR__.'/../../app/bootstrap.php';
try {
    $db = Database::pdo();
    $path = route_path();
    $method = request_method();

    if ($path === '/super-admin/login' && $method === 'GET') {
        view(__DIR__.'/../views/login.php', ['title' => 'Login']);
    }
    if ($path === '/super-admin/login' && $method === 'POST') {
        require_csrf();
        if (Auth::superAdminLogin($db, trim((string)($_POST['username'] ?? '')), (string)($_POST['password'] ?? ''))) {
            Audit::add($db, Auth::superAdminUsername(), 'SUPER_ADMIN_LOGIN');
            redirect('/super-admin');
        }
        view(__DIR__.'/../views/login.php', ['title' => 'Login', 'error' => 'Invalid username or password']);
    }
    if ($path === '/super-admin/logout') {
        Auth::superAdminLogout();
        redirect('/super-admin/login');
    }

    Auth::requireSuperAdmin();
    $actor = Auth::superAdminUsername();

    if ($path === '/super-admin' && $method === 'GET') {
        $stats = SuperAdminService::dashboardStats($db);
        view(__DIR__.'/../views/dashboard.php', compact('stats') + ['title' => 'Dashboard']);
    }
    if ($path === '/super-admin/customer-accounts' && $method === 'GET') {
        $accounts = SuperAdminService::customerAccounts($db);
        $stats = SuperAdminService::customerAccountStats($db);
        view(__DIR__.'/../views/customer_accounts.php', compact('accounts', 'stats') + ['title' => 'Customer Accounts']);
    }
    if ($path === '/super-admin/transactions' && $method === 'GET') {
        $filters = SuperAdminService::transactionFiltersFromGet();
        $txns = SuperAdminService::transactions($db, $filters);
        view(__DIR__.'/../views/transactions.php', $filters + compact('txns') + ['title' => 'Transactions']);
    }
    if (preg_match('#^/super-admin/transactions/(\d+)/confirm$#', $path, $m) && $method === 'POST') {
        require_csrf();
        TransactionService::ensureSchema($db);
        $st = $db->prepare('SELECT * FROM transactions WHERE id=?');
        $st->execute([(int)$m[1]]);
        $t = $st->fetch();
        if (!$t) throw new RuntimeException('Transaction not found');
        $bank = BankService::get($db, (int)$t['bank_id']);
        if (!$bank) throw new RuntimeException('Bank not found');
        $status = strtoupper(trim((string)($_POST['status'] ?? 'SUCCESS')));
        if (!in_array($status, ['SUCCESS', 'FAILED'], true)) $status = 'SUCCESS';
        $rrn = trim((string)($t['customer_reported_utr'] ?? ''));
        $amt = (string)($t['requested_amount'] ?? '');
        [$ok, $msg] = ReconciliationService::apply($db, $bank, [
            'reference' => $t['reference'],
            'status' => $status,
            'amount' => $amt,
            'rrn' => $rrn,
            'upi_txn_id' => $_POST['upi_txn_id'] ?? '',
            'payer_vpa' => $_POST['payer_vpa'] ?? '',
        ], 'MANUAL');
        Audit::add($db, $actor, 'SUPER_ADMIN_TXN_CONFIRM', $t['reference'].' '.$status.' '.$msg);
        flash($ok ? 'ok' : 'error', $ok ? ('Marked '.$status.'.') : $msg);
        redirect('/super-admin/transactions');
    }
    if (preg_match('#^/super-admin/transactions/(\d+)/screenshot$#', $path, $m) && $method === 'GET') {
        TransactionService::ensureSchema($db);
        $st = $db->prepare('SELECT * FROM transactions WHERE id=?');
        $st->execute([(int)$m[1]]);
        $t = $st->fetch();
        if (!$t) throw new RuntimeException('Transaction not found');
        TransactionService::streamScreenshot($t);
    }
    if ($path === '/super-admin/customers') {
        redirect('/super-admin');
    }
    if ($path === '/super-admin/banks' && $method === 'GET') {
        $banks = SuperAdminService::banks($db);
        $form = [
            'code' => '',
            'bank_name' => '',
            'vpa' => '',
            'payee_name' => '',
            'priority' => '100',
            'verification_mode' => 'MANUAL',
            'webhook_secret' => '',
        ];
        view(__DIR__.'/../views/banks.php', compact('banks', 'form') + ['title' => 'Banks']);
    }
    if ($path === '/super-admin/banks/routing' && $method === 'POST') {
        require_csrf();
        try {
            BankService::applyRouting($db, (string)($_POST['routing_mode'] ?? 'MANUAL'), (int)($_POST['manual_active_bank_id'] ?? 0));
            $mode = strtoupper((string)($_POST['routing_mode'] ?? 'MANUAL'));
            Audit::add($db, $actor, 'ROUTING_APPLIED', $mode);
            flash('ok', $mode === 'AUTO' ? 'Hourly auto-switch is on. Each enabled bank is live for 1 hour.' : 'Manual bank is now live until you change it.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('/super-admin/banks');
    }
    if (preg_match('#^/super-admin/banks/(\d+)/activate$#', $path, $m) && $method === 'POST') {
        require_csrf();
        $id = (int)$m[1];
        $b = BankService::get($db, $id);
        if (!$b) throw new RuntimeException('Bank not found');
        $db->prepare('UPDATE bank_profiles SET enabled=1 WHERE id=?')->execute([$id]);
        $db->prepare('UPDATE system_config SET manual_active_bank_id=?,rotation_enabled=0,updated_at=UTC_TIMESTAMP() WHERE id=1')->execute([$id]);
        Audit::add($db, $actor, 'BANK_ACTIVATED', (string)$b['code']);
        flash('ok', $b['bank_name'].' is live now. Auto-switch is off until you turn it on.');
        redirect('/super-admin/banks');
    }
    if ($path === '/super-admin/banks' && $method === 'POST') {
        require_csrf();
        $form = [
            'code' => trim((string)($_POST['code'] ?? '')),
            'bank_name' => trim((string)($_POST['bank_name'] ?? '')),
            'vpa' => trim((string)($_POST['vpa'] ?? '')),
            'payee_name' => trim((string)($_POST['payee_name'] ?? '')),
            'priority' => trim((string)($_POST['priority'] ?? '100')),
            'verification_mode' => trim((string)($_POST['verification_mode'] ?? 'MANUAL')),
            'webhook_secret' => trim((string)($_POST['webhook_secret'] ?? '')),
            'upi_uri' => trim((string)($_POST['upi_uri'] ?? '')),
        ];
        try {
            $created = SuperAdminService::addBankRoute($db, $form, $_FILES);
            Audit::add($db, $actor, 'SUPER_ADMIN_BANK_ADDED', $created['code']);
            flash('ok', 'Bank route added: '.$created['code']);
            redirect('/super-admin/banks');
        } catch (Throwable $e) {
            $banks = SuperAdminService::banks($db);
            view(__DIR__.'/../views/banks.php', compact('banks', 'form') + ['title' => 'Banks', 'error' => $e->getMessage()]);
        }
    }
    if (preg_match('#^/super-admin/banks/(\d+)/qr$#', $path, $m) && $method === 'GET') {
        SuperAdminService::streamBankQr($db, (int)$m[1]);
    }
    if ($path === '/super-admin/admin-users' && $method === 'GET') {
        $users = SuperAdminService::adminUsers($db);
        $form = ['name' => '', 'email' => '', 'role' => 'CHECKER'];
        view(__DIR__.'/../views/admin_users.php', compact('users', 'form') + ['title' => 'Admin Users']);
    }
    if ($path === '/super-admin/admin-users' && $method === 'POST') {
        require_csrf();
        $form = [
            'name' => trim((string)($_POST['name'] ?? '')),
            'email' => trim((string)($_POST['email'] ?? '')),
            'role' => trim((string)($_POST['role'] ?? 'CHECKER')),
        ];
        try {
            $created = SuperAdminService::createAdminUser($db, $form + ['password' => (string)($_POST['password'] ?? '')]);
            Audit::add($db, $actor, 'SUPER_ADMIN_USER_CREATED', $created['email']);
            flash('ok', 'Admin user created: '.$created['email']);
            redirect('/super-admin/admin-users');
        } catch (Throwable $e) {
            $users = SuperAdminService::adminUsers($db);
            view(__DIR__.'/../views/admin_users.php', compact('users', 'form') + ['title' => 'Admin Users', 'error' => $e->getMessage()]);
        }
    }
    if ($path === '/super-admin/billing' && $method === 'GET') {
        $settings = BillingService::settings($db);
        $plans = BillingService::customerCommissionPlans($db);
        $form = $settings;
        view(__DIR__.'/../views/billing.php', compact('settings', 'plans', 'form') + ['title' => 'Billing']);
    }
    if ($path === '/super-admin/billing/settings' && $method === 'POST') {
        require_csrf();
        $form = [
            'legal_name' => trim((string)($_POST['legal_name'] ?? '')),
            'gstin' => trim((string)($_POST['gstin'] ?? '')),
            'registered_state_code' => trim((string)($_POST['registered_state_code'] ?? '')),
            'gst_rate' => trim((string)($_POST['gst_rate'] ?? '18.000')),
            'gst_summary_email' => trim((string)($_POST['gst_summary_email'] ?? '')),
            'accounts_email' => trim((string)($_POST['accounts_email'] ?? '')),
            'fee_invoice_prefix' => trim((string)($_POST['fee_invoice_prefix'] ?? 'YDFEE')),
            'duplicate_scan_minutes' => trim((string)($_POST['duplicate_scan_minutes'] ?? '10')),
        ];
        try {
            BillingService::saveSettings($db, $form);
            Audit::add($db, $actor, 'SUPER_ADMIN_BILLING_SETTINGS_SAVED');
            flash('ok', 'Billing settings saved.');
            redirect('/super-admin/billing');
        } catch (Throwable $e) {
            $settings = BillingService::settings($db);
            $plans = BillingService::customerCommissionPlans($db);
            view(__DIR__.'/../views/billing.php', compact('settings', 'plans', 'form') + ['title' => 'Billing', 'error' => $e->getMessage()]);
        }
    }
    if ($path === '/super-admin/gst' && $method === 'GET') {
        $month = GstService::normalizeMonth((string)($_GET['month'] ?? ''));
        $summary = GstService::summary($db, $month);
        $history = GstService::history($db);
        view(__DIR__.'/../views/gst.php', compact('month', 'summary', 'history') + ['title' => 'GST']);
    }
    if ($path === '/super-admin/gst/save' && $method === 'POST') {
        require_csrf();
        $month = GstService::normalizeMonth((string)($_POST['month'] ?? ''));
        try {
            GstService::recalculate($db, $month, GstService::parseAmount($_POST['eligible_itc'] ?? 0), GstService::parseAmount($_POST['other_adjustment'] ?? 0));
            Audit::add($db, $actor, 'SUPER_ADMIN_GST_RECALC', $month);
            flash('ok', 'GST summary saved and recalculated.');
            redirect('/super-admin/gst?month='.rawurlencode($month));
        } catch (Throwable $e) {
            $summary = GstService::summary($db, $month);
            $history = GstService::history($db);
            view(__DIR__.'/../views/gst.php', compact('month', 'summary', 'history') + ['title' => 'GST', 'error' => $e->getMessage()]);
        }
    }
    if ($path === '/super-admin/gst/regenerate' && $method === 'POST') {
        require_csrf();
        $month = GstService::normalizeMonth((string)($_POST['month'] ?? ''));
        $existing = GstService::summary($db, $month);
        GstService::recalculate($db, $month, (float)($existing['eligible_itc'] ?? 0), (float)($existing['other_adjustment'] ?? 0));
        Audit::add($db, $actor, 'SUPER_ADMIN_GST_REGENERATE', $month);
        flash('ok', 'GST summary regenerated from fee entries.');
        redirect('/super-admin/gst?month='.rawurlencode($month));
    }
    if ($path === '/super-admin/gst/email' && $method === 'POST') {
        require_csrf();
        $month = GstService::normalizeMonth((string)($_POST['month'] ?? ''));
        try {
            GstService::emailSummary($db, $month);
            Audit::add($db, $actor, 'SUPER_ADMIN_GST_EMAILED', $month);
            flash('ok', 'GST summary emailed.');
            redirect('/super-admin/gst?month='.rawurlencode($month));
        } catch (Throwable $e) {
            $summary = GstService::summary($db, $month);
            $history = GstService::history($db);
            view(__DIR__.'/../views/gst.php', compact('month', 'summary', 'history') + ['title' => 'GST', 'error' => $e->getMessage()]);
        }
    }
    if ($path === '/super-admin/gst/export.csv' && $method === 'GET') {
        GstService::exportCsv($db, GstService::normalizeMonth((string)($_GET['month'] ?? '')));
    }
    if ($path === '/super-admin/gst/export.pdf' && $method === 'GET') {
        GstService::exportPdf($db, GstService::normalizeMonth((string)($_GET['month'] ?? '')));
    }
    if ($path === '/super-admin/reports' && $method === 'GET') {
        $rows = ReportService::dailyCollectionSummary($db);
        view(__DIR__.'/../views/reports.php', compact('rows') + ['title' => 'Reports']);
    }
    if ($path === '/super-admin/tickets' && $method === 'GET') {
        $status = strtoupper(trim((string)($_GET['status'] ?? '')));
        $q = trim((string)($_GET['q'] ?? ''));
        $tickets = TicketService::search($db, compact('status', 'q'));
        $stats = TicketService::stats($db);
        view(__DIR__.'/../views/tickets.php', compact('tickets', 'stats', 'status', 'q') + ['title' => 'Support Tickets']);
    }
    if (preg_match('#^/super-admin/tickets/(\d+)$#', $path, $m) && $method === 'GET') {
        $ticket = TicketService::get($db, (int)$m[1]);
        if (!$ticket) {
            throw new RuntimeException('Ticket not found');
        }
        $replies = TicketService::replies($db, (int)$ticket['id']);
        view(__DIR__.'/../views/ticket_view.php', compact('ticket', 'replies') + ['title' => 'Ticket '.$ticket['reference']]);
    }
    if (preg_match('#^/super-admin/tickets/(\d+)/update$#', $path, $m) && $method === 'POST') {
        require_csrf();
        $ticket = TicketService::get($db, (int)$m[1]);
        if (!$ticket) {
            throw new RuntimeException('Ticket not found');
        }
        TicketService::update($db, (int)$ticket['id'], $_POST, $actor);
        Audit::add($db, $actor, 'TICKET_UPDATED', $ticket['reference']);
        redirect('/super-admin/tickets/'.$ticket['id']);
    }
    if (preg_match('#^/super-admin/tickets/(\d+)/reply$#', $path, $m) && $method === 'POST') {
        require_csrf();
        $ticket = TicketService::get($db, (int)$m[1]);
        if (!$ticket) {
            throw new RuntimeException('Ticket not found');
        }
        TicketService::addReply($db, (int)$ticket['id'], 'SUPERADMIN', $actor, (string)($_POST['message'] ?? ''));
        Audit::add($db, $actor, 'TICKET_REPLY', $ticket['reference']);
        redirect('/super-admin/tickets/'.$ticket['id']);
    }
    if ($path === '/super-admin/wallet' && $method === 'GET') {
        $tab = strtolower(trim((string)($_GET['tab'] ?? 'all')));
        $q = trim((string)($_GET['q'] ?? ''));
        $rechargeStatus = strtoupper(trim((string)($_GET['recharge_status'] ?? '')));
        $withdrawStatus = strtoupper(trim((string)($_GET['withdraw_status'] ?? '')));
        $rechargeStats = WalletService::rechargeStats($db);
        $withdrawStats = WalletService::withdrawalStats($db);
        $recharges = WalletService::searchRecharges($db, ['status' => $rechargeStatus, 'q' => $q]);
        $withdrawals = WalletService::searchWithdrawals($db, ['status' => $withdrawStatus, 'q' => $q]);
        view(__DIR__.'/../views/wallet.php', compact('tab', 'q', 'rechargeStatus', 'withdrawStatus', 'rechargeStats', 'withdrawStats', 'recharges', 'withdrawals') + ['title' => 'Wallet Transactions']);
    }
    if ($path === '/super-admin/wallet/withdrawals' && $method === 'GET') {
        redirect('/super-admin/wallet?tab=withdrawals');
    }
    if (preg_match('#^/super-admin/wallet/withdrawals/(\d+)$#', $path, $m) && $method === 'GET') {
        $withdrawal = WalletService::getWithdrawal($db, (int)$m[1]);
        if (!$withdrawal) {
            throw new RuntimeException('Withdrawal not found');
        }
        view(__DIR__.'/../views/withdrawal_view.php', compact('withdrawal') + ['title' => 'Withdrawal '.$withdrawal['reference']]);
    }
    if (preg_match('#^/super-admin/wallet/withdrawals/(\d+)/update$#', $path, $m) && $method === 'POST') {
        require_csrf();
        $withdrawal = WalletService::getWithdrawal($db, (int)$m[1]);
        if (!$withdrawal) {
            throw new RuntimeException('Withdrawal not found');
        }
        WalletService::updateWithdrawal($db, (int)$withdrawal['id'], $_POST, $actor);
        Audit::add($db, $actor, 'WITHDRAWAL_UPDATED', $withdrawal['reference']);
        redirect('/super-admin/wallet/withdrawals/'.$withdrawal['id']);
    }

    http_response_code(404);
    echo 'Not Found';
} catch (Throwable $e) {
    http_response_code(400);
    echo '<!doctype html><meta name="viewport" content="width=device-width"><link rel="stylesheet" href="'.e(asset_url('super.css')).'"><body class="sa-body"><div class="sa-wrap"><div class="sa-error"><b>Error:</b> '.e($e->getMessage()).'</div><p><a href="javascript:history.back()">Go back</a></p></div></body>';
}
