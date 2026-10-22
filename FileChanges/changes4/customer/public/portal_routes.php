<?php
declare(strict_types=1);

$portalNext = static function (): string {
    $next = (string)($_POST['next'] ?? $_GET['next'] ?? '');
    if ($next !== '' && !preg_match('#^/c/[A-Z0-9]+(?:/[A-Za-z0-9._~-]*)*$#', $next)) return '';
    return $next;
};

$loginView = static function (array $extra = []) use ($portalNext): void {
    view(__DIR__.'/../views/login.php', $extra + [
        'title' => 'Customer Login',
        'portalLayout' => 'bare',
        'next' => $portalNext(),
        'forgot' => isset($_GET['forgot']) || !empty($extra['forgot']),
        'login_value' => (string)($extra['login_value'] ?? ($_GET['login'] ?? '')),
    ]);
};

if ($path === '/login' && $method === 'GET') {
    if (isset($_GET['reset'])) {
        unset($_SESSION['portal_otp_for'], $_SESSION['portal_otp_login']);
        redirect('/login'.(($n = $portalNext()) !== '' ? '?next='.rawurlencode($n) : ''));
    }
    $pendingId = (int)($_SESSION['portal_otp_for'] ?? 0);
    if ($pendingId > 0 && !isset($_GET['forgot'])) {
        $c = Portal::customerById($db, $pendingId);
        if ($c) {
            $loginView([
                'sent' => true,
                'c' => $c,
                'masked_phone' => mask_phone((string)$c['phone']),
                'login_value' => (string)($_SESSION['portal_otp_login'] ?? ''),
            ]);
        }
    }
    $loginView();
}

if ($path === '/login' && $method === 'POST') {
    require_csrf();
    $login = trim((string)($_POST['login'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $forgot = !empty($_POST['forgot']);
    $c = Portal::findCustomer($db, $login);
    if (!$c) {
        $loginView(['error' => 'No active account matches that email or mobile.', 'login_value' => $login, 'forgot' => $forgot]);
    }
    $hash = (string)($c['password_hash'] ?? '');
    if (!$forgot && $hash !== '' && !password_verify($password, $hash)) {
        $loginView(['error' => 'Password does not match.', 'login_value' => $login]);
    }
    try {
        $otp = OtpService::create($db, $c);
        SmsService::sendOtp($db, (string)$c['phone'], $otp);
    } catch (Throwable $e) {
        $loginView(['error' => $e->getMessage(), 'login_value' => $login, 'forgot' => $forgot]);
    }
    $_SESSION['portal_otp_for'] = (int)$c['id'];
    $_SESSION['portal_otp_login'] = $login;
    $loginView([
        'sent' => true,
        'c' => $c,
        'masked_phone' => mask_phone((string)$c['phone']),
        'login_value' => $login,
        'dev_otp' => Portal::showDevOtp() ? $otp : null,
        'forgot' => $forgot,
    ]);
}

if ($path === '/login/verify' && $method === 'POST') {
    require_csrf();
    $id = (int)($_SESSION['portal_otp_for'] ?? 0);
    $c = $id > 0 ? Portal::customerById($db, $id) : null;
    if (!$c) {
        $loginView(['error' => 'Request a new OTP to continue.']);
    }
    [$ok, $msg] = OtpService::verify($db, $c, trim((string)($_POST['otp'] ?? '')));
    if (!$ok) {
        $loginView([
            'sent' => true,
            'c' => $c,
            'masked_phone' => mask_phone((string)$c['phone']),
            'login_value' => (string)($_SESSION['portal_otp_login'] ?? ''),
            'error' => $msg,
        ]);
    }
    session_regenerate_id(true);
    Auth::customerLogin((int)$c['id']);
    $_SESSION['customer_code'] = strtoupper((string)$c['code']);
    unset($_SESSION['portal_otp_for'], $_SESSION['portal_otp_login']);
    redirect(Portal::safeNext($portalNext(), (string)$c['code']));
}

if (preg_match('#^/c/([A-Z0-9]+)/logout$#i', $path, $m)) {
    Auth::customerLogout();
    redirect('/login');
}

if (preg_match('#^/c/([A-Z0-9]+)/(billing|wallet)(\.|/|$)#i', $path, $m)) {
    redirect('/c/'.strtoupper($m[1]));
}

if (preg_match('#^/c/([A-Z0-9]+)$#i', $path, $m) && $method === 'GET') {
    $c = Portal::guard($db, $m[1]);
    $cards = Portal::cards($db, (int)$c['id']);
    view(__DIR__.'/../views/dashboard.php', [
        'title' => 'Dashboard',
        'c' => $c,
        'cards' => $cards,
        'nav' => 'dashboard',
        'portalLayout' => 'sidebar',
    ]);
}

if (preg_match('#^/c/([A-Z0-9]+)/qr$#i', $path, $m) && ($method === 'GET' || $method === 'POST')) {
    $account = Portal::guard($db, $m[1]);
    $pay = TransactionService::customerByCode($db, 'CUST0003');
    $error = null;
    $payQr = null;
    $pending = null;
    $bank = null;
    if (!$pay || !(int)$pay['active']) {
        $error = 'CUST0003 was not found or the payment link is disabled.';
    } else {
        TransactionService::ensureSchema($db);
        $bank = BankService::active($db);
        if ($method === 'POST') {
            require_csrf();
            try {
                $amount = TransactionService::parseEnteredAmount($_POST['amount'] ?? '');
                $created = TransactionService::createAttempt($db, $pay, $amount, trim((string)($_POST['invoice_ref'] ?? '')), 'UPI', 'UNSETTLED', true);
                redirect('/c/'.$account['code'].'/qr?ref='.rawurlencode((string)$created['reference']));
            } catch (Throwable $e) {
                $error = $e->getMessage();
                $pending = TransactionService::latestPending($db, (int)$pay['id']);
            }
        } else {
            $ref = strtoupper(trim((string)($_GET['ref'] ?? '')));
            if ($ref !== '') {
                $row = TransactionService::txnByRef($db, $ref);
                if ($row && (int)$row['customer_id'] === (int)$pay['id']) {
                    $row = TransactionService::refreshCustomerPayment($db, $row);
                    if (strtoupper((string)$row['status']) === 'SUCCESS') {
                        redirect('/c/'.$account['code'].'/qr?receipt='.rawurlencode((string)$row['reference']));
                    }
                    if (strtoupper((string)$row['status']) !== 'PENDING') {
                        redirect('/p/'.$pay['code'].'/result/'.$row['reference']);
                    }
                    $payQr = $row;
                }
            }
        }
    }
    $printReceipt = null;
    $receiptWant = strtoupper(trim((string)($_GET['receipt'] ?? '')));
    if ($pay && $receiptWant !== '') {
        $printed = TransactionService::txnByRef($db, $receiptWant);
        if ($printed && (int)$printed['customer_id'] === (int)$pay['id'] && strtoupper((string)$printed['status']) === 'SUCCESS') {
            $printReceipt = $printed;
        }
    }
    view(__DIR__.'/../views/merchant_pay.php', [
        'title' => 'Pay CUST0003',
        'c' => $account,
        'pay' => $pay,
        'bank' => $bank,
        'pay_qr' => $payQr,
        'pending' => $pending,
        'print_receipt' => $printReceipt,
        'error' => $error,
        'nav' => 'merchants',
        'portalLayout' => 'sidebar',
        'pay_ui' => true,
    ]);
}

if (preg_match('#^/c/([A-Z0-9]+)/start$#i', $path, $m) && $method === 'POST') {
    require_csrf();
    $c = Portal::guard($db, $m[1]);
    $bank = BankService::active($db);
    try {
        $amount = TransactionService::parseEnteredAmount($_POST['amount'] ?? '');
        $t = TransactionService::createAttempt($db, $c, $amount, '');
        redirect('/c/'.$c['code'].'/qr?ref='.rawurlencode((string)$t['reference']));
    } catch (Throwable $e) {
        $upi = '';
        $vpa = $bank ? trim((string)($bank['vpa'] ?? '')) : '';
        if ($bank) {
            try {
                if (trim((string)($bank['qr_payload'] ?? '')) !== '') $upi = BankService::exactIntent($bank);
            } catch (Throwable $ignored) {
                $upi = '';
            }
            if ($upi === '' && $vpa !== '') $upi = BankService::customerAmountIntent($bank, null, '');
        }
        view(__DIR__.'/../views/merchant_qr.php', [
            'title' => 'Merchant QR',
            'c' => $c,
            'bank' => $bank,
            'upi' => $upi,
            'vpa' => $vpa,
            'nav' => 'merchants',
            'portalLayout' => 'sidebar',
            'presets' => TransactionService::PUBLIC_PAY_AMOUNTS,
            'error' => $e->getMessage(),
        ]);
    }
}

if (preg_match('#^/c/([A-Z0-9]+)/statement(?:\.csv)?$#i', $path, $m) && $method === 'GET') {
    $c = Portal::guard($db, $m[1]);
    [$defStart, $defEnd] = Portal::monthRange();
    $start = trim((string)($_GET['start'] ?? $defStart));
    $end = trim((string)($_GET['end'] ?? $defEnd));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) $start = $defStart;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) $end = $defEnd;
    $rows = Portal::payments($db, (int)$c['id'], $start, $end);
    if (str_ends_with(strtolower($path), '.csv')) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$c['code'].'-statement.csv"');
        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['#', 'Date & Time', 'Transaction ID', 'Amount', 'Status', 'Payment Mode']);
        $n = 1;
        foreach ($rows as $t) {
            [$label] = Portal::payStatus($t);
            $amt = TransactionService::displayAmount($t);
            fputcsv($out, [$n++, Portal::when((string)$t['created_at']), $t['reference'], $amt, $label, Portal::payMode($t)]);
        }
        fclose($out);
        exit;
    }
    view(__DIR__.'/../views/statement.php', [
        'title' => 'Transaction Statement',
        'c' => $c,
        'rows' => $rows,
        'start' => $start,
        'end' => $end,
        'nav' => 'payments',
        'portalLayout' => 'sidebar',
        'print' => isset($_GET['print']),
    ]);
}

if (preg_match('#^/c/([A-Z0-9]+)/statement\.pdf$#i', $path, $m) && $method === 'GET') {
    $q = $_GET;
    unset($q['print']);
    $q['print'] = '1';
    redirect('/c/'.strtoupper($m[1]).'/statement?'.http_build_query($q));
}

if (preg_match('#^/c/([A-Z0-9]+)/invoice/([A-Z0-9]+)$#i', $path, $m) && $method === 'GET') {
    $c = Portal::guard($db, $m[1]);
    $t = TransactionService::txnByRef($db, strtoupper($m[2]));
    if (!$t || (int)$t['customer_id'] !== (int)$c['id'] || strtoupper((string)$t['status']) !== 'SUCCESS') {
        throw new RuntimeException('Invoice is available after a successful payment');
    }
    $amt = TransactionService::displayAmount($t);
    $split = Portal::gstSplit($amt === '' ? 0.0 : (float)str_replace(',', '', $amt));
    view(__DIR__.'/../views/invoice.php', [
        'title' => 'Tax Invoice',
        'c' => $c,
        't' => $t,
        'split' => $split,
        'nav' => 'payments',
        'portalLayout' => 'sidebar',
    ]);
}

if (preg_match('#^/c/([A-Z0-9]+)/customers$#i', $path, $m) && $method === 'GET') {
    $c = Portal::guard($db, $m[1]);
    view(__DIR__.'/../views/payers.php', [
        'title' => 'Customers',
        'c' => $c,
        'payers' => Portal::payers($db, (int)$c['id']),
        'nav' => 'customers',
        'portalLayout' => 'sidebar',
    ]);
}

if (preg_match('#^/c/([A-Z0-9]+)/withdrawals$#i', $path, $m) && $method === 'GET') {
    $c = Portal::guard($db, $m[1]);
    view(__DIR__.'/../views/withdrawals.php', [
        'title' => 'Withdrawals',
        'c' => $c,
        'rows' => Portal::withdrawals($db, (int)$c['id']),
        'nav' => 'withdrawals',
        'portalLayout' => 'sidebar',
    ]);
}

if (preg_match('#^/c/([A-Z0-9]+)/reports$#i', $path, $m) && $method === 'GET') {
    $c = Portal::guard($db, $m[1]);
    view(__DIR__.'/../views/reports.php', [
        'title' => 'Reports',
        'c' => $c,
        'cards' => Portal::cards($db, (int)$c['id']),
        'nav' => 'reports',
        'portalLayout' => 'sidebar',
    ]);
}

if (preg_match('#^/c/([A-Z0-9]+)/settings$#i', $path, $m) && $method === 'GET') {
    $c = Portal::guard($db, $m[1]);
    view(__DIR__.'/../views/settings.php', [
        'title' => 'Settings',
        'c' => $c,
        'nav' => 'settings',
        'portalLayout' => 'sidebar',
    ]);
}

if (preg_match('#^/c/([A-Z0-9]+)/settings$#i', $path, $m) && $method === 'POST') {
    require_csrf();
    $c = Portal::guard($db, $m[1]);
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        view(__DIR__.'/../views/settings.php', [
            'title' => 'Settings',
            'c' => $c,
            'nav' => 'settings',
            'portalLayout' => 'sidebar',
            'error' => 'Enter a valid email address.',
        ]);
    }
    $db->prepare('UPDATE customers SET email=? WHERE id=?')->execute([$email !== '' ? $email : null, (int)$c['id']]);
    flash('ok', 'Account email saved.');
    redirect('/c/'.$c['code'].'/settings');
}

if (preg_match('#^/c/([A-Z0-9]+)/tickets$#i', $path, $m) && $method === 'GET') {
    $c = Portal::guard($db, $m[1]);
    view(__DIR__.'/../views/tickets.php', [
        'title' => 'Support Tickets',
        'c' => $c,
        'tickets' => TicketService::forCustomer($db, (int)$c['id']),
        'nav' => 'support',
        'portalLayout' => 'sidebar',
        'show_form' => isset($_GET['new']),
    ]);
}

if (preg_match('#^/c/([A-Z0-9]+)/tickets$#i', $path, $m) && $method === 'POST') {
    require_csrf();
    $c = Portal::guard($db, $m[1]);
    try {
        $ticket = TicketService::create($db, $c, $_POST);
        redirect('/c/'.$c['code'].'/tickets/'.$ticket['id']);
    } catch (Throwable $e) {
        view(__DIR__.'/../views/tickets.php', [
            'title' => 'Support Tickets',
            'c' => $c,
            'tickets' => TicketService::forCustomer($db, (int)$c['id']),
            'nav' => 'support',
            'portalLayout' => 'sidebar',
            'show_form' => true,
            'error' => $e->getMessage(),
        ]);
    }
}

if (preg_match('#^/c/([A-Z0-9]+)/tickets/(\d+)$#i', $path, $m) && $method === 'GET') {
    $c = Portal::guard($db, $m[1]);
    $ticket = TicketService::get($db, (int)$m[2]);
    if (!$ticket || (int)$ticket['customer_id'] !== (int)$c['id']) throw new RuntimeException('Ticket not found');
    view(__DIR__.'/../views/ticket_view.php', [
        'title' => 'Ticket '.$ticket['reference'],
        'c' => $c,
        'ticket' => $ticket,
        'replies' => TicketService::replies($db, (int)$ticket['id']),
        'nav' => 'support',
        'portalLayout' => 'sidebar',
    ]);
}

if (preg_match('#^/c/([A-Z0-9]+)/tickets/(\d+)/reply$#i', $path, $m) && $method === 'POST') {
    require_csrf();
    $c = Portal::guard($db, $m[1]);
    $ticket = TicketService::get($db, (int)$m[2]);
    if (!$ticket || (int)$ticket['customer_id'] !== (int)$c['id']) throw new RuntimeException('Ticket not found');
    try {
        TicketService::addReply($db, (int)$ticket['id'], (string)$c['name'], (string)($_POST['message'] ?? ''));
        redirect('/c/'.$c['code'].'/tickets/'.$ticket['id']);
    } catch (Throwable $e) {
        view(__DIR__.'/../views/ticket_view.php', [
            'title' => 'Ticket '.$ticket['reference'],
            'c' => $c,
            'ticket' => $ticket,
            'replies' => TicketService::replies($db, (int)$ticket['id']),
            'nav' => 'support',
            'portalLayout' => 'sidebar',
            'error' => $e->getMessage(),
        ]);
    }
}
