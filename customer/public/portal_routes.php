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
    $loggedId = (int)($_SESSION['customer_id'] ?? 0);
    if ($loggedId > 0 && !isset($_GET['reset'])) {
        $code = strtoupper((string)($_SESSION['customer_code'] ?? ''));
        if ($code === '') {
            $row = Portal::customerById($db, $loggedId);
            $code = strtoupper((string)($row['code'] ?? ''));
        }
        if ($code !== '') redirect('/c/'.$code);
    }
    if (isset($_GET['reset'])) {
        unset($_SESSION['portal_otp_for'], $_SESSION['portal_otp_login']);
        redirect('/login'.(($n = $portalNext()) !== '' ? '?next='.rawurlencode($n) : ''));
    }
    $loginView();
}

if ($path === '/login' && $method === 'POST') {
    require_csrf();
    $login = trim((string)($_POST['login'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if ($password === '') {
        $loginView(['error' => 'Enter your password.', 'login_value' => $login]);
    }
    $c = Auth::portalPasswordLogin($db, $login, $password);
    if (!$c) {
        $hint = Portal::findCustomer($db, $login);
        if ($hint && (string)($hint['password_hash'] ?? '') === '') {
            $loginView([
                'error' => 'This account has no portal password yet. Ask admin to set Login ID and password under Customers.',
                'login_value' => $login,
            ]);
        }
        $loginView(['error' => 'Invalid login ID or password.', 'login_value' => $login]);
    }
    redirect(Portal::safeNext($portalNext(), (string)$c['code']));
}

if ($path === '/login/verify' && $method === 'POST') {
    require_csrf();
    $loginView(['error' => 'SMS OTP login is disabled. Sign in with your Login ID and password.']);
}

if (preg_match('#^/c/([A-Z0-9]+)/logout$#i', $path, $m)) {
    Auth::customerLogout();
    redirect('/login');
}

if (preg_match('#^/c/([A-Z0-9]+)/billing$#i', $path, $m) && $method === 'GET') {
    $c = Portal::guard($db, $m[1]);
    TransactionService::ensureSchema($db);
    $summary = BillingService::customerPlatformSummary($db, (int)$c['id']);
    $entries = BillingService::customerFeeEntries($db, (int)$c['id']);
    view(__DIR__.'/../views/billing.php', [
        'title' => 'Platform Fees & GST',
        'c' => $c,
        'summary' => $summary,
        'entries' => $entries,
        'nav' => 'billing',
        'portalLayout' => 'sidebar',
    ]);
}

if (preg_match('#^/c/([A-Z0-9]+)/billing\.csv$#i', $path, $m) && $method === 'GET') {
    $c = Portal::guard($db, $m[1]);
    BillingService::downloadCustomerFeesCsv($c, BillingService::customerFeeEntries($db, (int)$c['id']));
}

if (preg_match('#^/c/([A-Z0-9]+)/billing\.pdf$#i', $path, $m) && $method === 'GET') {
    $c = Portal::guard($db, $m[1]);
    $summary = BillingService::customerPlatformSummary($db, (int)$c['id']);
    BillingService::downloadCustomerFeesPdf($c, $summary, BillingService::customerFeeEntries($db, (int)$c['id']));
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
    $payCode = strtoupper((string)$account['code']);
    $pay = TransactionService::customerByCode($db, $payCode);
    $error = null;
    $payQr = null;
    $pending = null;
    $bank = null;
    if (!$pay || !(int)$pay['active']) {
        $error = $payCode.' was not found or the payment link is disabled.';
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
        'title' => 'Pay '.$payCode,
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
        fputcsv($out, ['#', 'Date & Time', 'Transaction ID', 'Amount', 'Status', 'Buyer Name', 'UTR Number', 'Payment Mode']);
        $n = 1;
        foreach ($rows as $t) {
            [$label] = Portal::payStatus($t);
            $amt = TransactionService::displayAmount($t);
            fputcsv($out, [$n++, Portal::when((string)$t['created_at']), $t['reference'], $amt, $label, TransactionService::displayBuyerName($t), TransactionService::displayVerifiedUtr($t), Portal::payMode($t)]);
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
    $c = Portal::guard($db, $m[1]);
    [$defStart, $defEnd] = Portal::monthRange();
    $start = trim((string)($_GET['start'] ?? $defStart));
    $end = trim((string)($_GET['end'] ?? $defEnd));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) $start = $defStart;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) $end = $defEnd;
    $f = [
        'period' => 'custom',
        'status' => '',
        'bank_id' => null,
        'start' => $start,
        'end' => $end,
    ];
    StatementService::download($c, StatementService::rows($db, (int)$c['id'], $f), $f);
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
    $submittedRef = strtoupper(trim((string)($_GET['submitted'] ?? '')));
    $submittedTicket = null;
    $whatsappRedirect = '';
    if ($submittedRef !== '') {
        $submittedTicket = TicketService::getByReference($db, $submittedRef);
        if (!$submittedTicket || (int)$submittedTicket['customer_id'] !== (int)$c['id']) {
            $submittedTicket = null;
        } else {
            $whatsappRedirect = TicketService::whatsappSupportUrl($c, TicketService::problemSummary($submittedTicket), (string)$submittedTicket['reference']);
        }
    }
    view(__DIR__.'/../views/tickets.php', [
        'title' => 'Support Tickets',
        'c' => $c,
        'tickets' => TicketService::forCustomer($db, (int)$c['id']),
        'nav' => 'support',
        'portalLayout' => 'sidebar',
        'show_form' => true,
        'submitted_ticket' => $submittedTicket,
        'whatsapp_redirect' => $whatsappRedirect,
    ]);
}

if (preg_match('#^/c/([A-Z0-9]+)/tickets$#i', $path, $m) && $method === 'POST') {
    require_csrf();
    $c = Portal::guard($db, $m[1]);
    try {
        $ticket = TicketService::create($db, $c, $_POST, $_FILES['screenshot'] ?? []);
        redirect('/c/'.$c['code'].'/tickets?submitted='.rawurlencode((string)$ticket['reference']));
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

if (preg_match('#^/c/([A-Z0-9]+)/tickets/(\d+)/attachment$#i', $path, $m) && $method === 'GET') {
    $c = Portal::guard($db, $m[1]);
    $ticket = TicketService::get($db, (int)$m[2]);
    if (!$ticket || (int)$ticket['customer_id'] !== (int)$c['id']) {
        throw new RuntimeException('Ticket not found');
    }
    TicketService::streamAttachment($ticket);
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
        TicketService::addReply($db, (int)$ticket['id'], 'CUSTOMER', (string)$c['name'], (string)($_POST['message'] ?? ''));
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

/* -------------------------------------------------- Razorpay (card / netbanking) */

if (preg_match('#^/c/([A-Z0-9]+)/razorpay$#i', $path, $m) && $method === 'GET') {
    $c = Portal::guard($db, $m[1]);
    $cfg = RazorpayService::publicConfig($db);
    view(__DIR__.'/../views/razorpay_pay.php', [
        'title' => 'Card / Netbanking Payment',
        'c' => $c,
        'cfg' => $cfg,
        'recent' => RazorpayService::recentForCustomer($db, (int)$c['id'], 15),
        'nav' => 'razorpay',
        'portalLayout' => 'sidebar',
    ]);
}

if (preg_match('#^/c/([A-Z0-9]+)/razorpay/order$#i', $path, $m) && $method === 'POST') {
    require_csrf();
    $c = Portal::guard($db, $m[1]);
    header('Content-Type: application/json; charset=utf-8');
    try {
        $cfg = RazorpayService::publicConfig($db);
        if (empty($cfg['enabled'])) { json_response(['ok' => false, 'error' => 'Razorpay payments are not available right now.'], 400); }
        $amount = RazorpayService::parseAmount($_POST['amount'] ?? '', (float)$cfg['min'], (float)$cfg['max']);
        $order = RazorpayService::createOrder($db, $c, $amount);
        json_response([
            'ok' => true,
            'key_id' => (string)$cfg['key_id'],
            'order_id' => (string)$order['rzp_order_id'],
            'amount' => (int)$order['amount_paise'],
            'currency' => (string)$order['currency'],
            'local_reference' => (string)$order['local_reference'],
            'name' => (string)($c['name'] ?? 'YEEDIPAY'),
            'label' => (string)$cfg['label'],
            'result_url' => url('/c/'.$c['code'].'/razorpay/result/'.$order['local_reference']),
            'prefill_email' => (string)($c['email'] ?? ''),
            'prefill_contact' => (string)($c['phone'] ?? ''),
        ]);
    } catch (Throwable $e) {
        json_response(['ok' => false, 'error' => $e->getMessage()], 400);
    }
}

if (preg_match('#^/c/([A-Z0-9]+)/razorpay/verify$#i', $path, $m) && $method === 'POST') {
    require_csrf();
    $c = Portal::guard($db, $m[1]);
    header('Content-Type: application/json; charset=utf-8');
    $orderId = trim((string)($_POST['razorpay_order_id'] ?? ''));
    $paymentId = trim((string)($_POST['razorpay_payment_id'] ?? ''));
    $signature = trim((string)($_POST['razorpay_signature'] ?? ''));
    $order = $orderId !== '' ? RazorpayService::orderByRzpId($db, $orderId) : null;
    if (!$order || (int)($order['customer_id'] ?? 0) !== (int)$c['id']) {
        json_response(['ok' => false, 'error' => 'Unknown order'], 404);
    }
    // Browser callback integrity check (not final proof).
    $sigOk = RazorpayService::verifyCheckoutSignature($orderId, $paymentId, $signature, RazorpayService::keySecret($db));
    // Final confirmation always comes from an authenticated server-side fetch.
    $order = RazorpayService::refreshFromApi($db, $order);
    json_response([
        'ok' => true,
        'signature_valid' => $sigOk,
        'status' => (string)$order['status'],
        'result_url' => url('/c/'.$c['code'].'/razorpay/result/'.$order['local_reference']),
    ]);
}

if (preg_match('#^/c/([A-Z0-9]+)/razorpay/status/([A-Za-z0-9\-]+)$#i', $path, $m) && $method === 'GET') {
    $c = Portal::guard($db, $m[1]);
    header('Content-Type: application/json; charset=utf-8');
    $order = RazorpayService::orderByLocalRef($db, $m[2]);
    if (!$order || (int)($order['customer_id'] ?? 0) !== (int)$c['id']) {
        json_response(['ok' => false, 'error' => 'Not found'], 404);
    }
    $order = RazorpayService::refreshFromApi($db, $order);
    json_response([
        'ok' => true,
        'status' => (string)$order['status'],
        'verified' => (bool)(int)$order['verified'],
        'amount' => RazorpayService::rupees((int)$order['amount_paise']),
        'payment_id' => (string)($order['rzp_payment_id'] ?? ''),
    ]);
}

if (preg_match('#^/c/([A-Z0-9]+)/razorpay/result/([A-Za-z0-9\-]+)$#i', $path, $m) && $method === 'GET') {
    $c = Portal::guard($db, $m[1]);
    $order = RazorpayService::orderByLocalRef($db, $m[2]);
    if (!$order || (int)($order['customer_id'] ?? 0) !== (int)$c['id']) {
        throw new RuntimeException('Payment not found');
    }
    $order = RazorpayService::refreshFromApi($db, $order);
    view(__DIR__.'/../views/razorpay_result.php', [
        'title' => 'Payment Status',
        'c' => $c,
        'order' => $order,
        'nav' => 'razorpay',
        'portalLayout' => 'sidebar',
    ]);
}
