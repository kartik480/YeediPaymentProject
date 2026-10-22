<?php
declare(strict_types=1);

final class Portal {
    public static function lang(): string {
        return 'en';
    }

    public static function t(string $key): string {
        $en = [
            'dashboard' => 'Dashboard',
            'payments' => 'Payments',
            'customers' => 'Customers',
            'merchants' => 'Merchants',
            'withdrawals' => 'Withdrawals',
            'reports' => 'Reports',
            'settings' => 'Settings',
            'support' => 'Support',
            'logout' => 'Logout',
            'welcome' => 'Welcome back!',
            'customer_dashboard' => 'Customer Dashboard',
            'total' => 'Total Payments',
            'success' => 'Success',
            'pending' => 'Pending',
            'failed' => 'Failed',
            'today' => 'Today',
            'scan' => 'Scan & Pay',
            'statement' => 'Transaction Statement',
            'export' => 'Export',
            'new_ticket' => 'New Ticket',
            'tickets' => 'Support Tickets',
            'invoice' => 'Tax Invoice',
            'login_title' => 'Customer Login',
            'login_btn' => 'Login with OTP',
            'forgot' => 'Forgot Password?',
            'email' => 'Email / Mobile Number',
            'password' => 'Enter Password',
            'verify' => 'Verify OTP',
            'create_qr' => 'Create QR',
        ];
        return $en[$key] ?? $key;
    }

    public static function money(float $amount, int $decimals = 2): string {
        $neg = $amount < 0;
        $formatted = number_format(abs($amount), $decimals, '.', '');
        [$whole, $frac] = array_pad(explode('.', $formatted, 2), 2, '');
        if (strlen($whole) > 3) {
            $tail = substr($whole, -3);
            $head = substr($whole, 0, -3);
            $head = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $head) ?? $head;
            $whole = $head.','.$tail;
        }
        $out = $decimals > 0 ? $whole.'.'.$frac : $whole;
        return ($neg ? '-' : '').$out;
    }

    public static function chip(string $name): string {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $parts = array_values(array_filter($parts, static fn($p) => $p !== ''));
        if (count($parts) >= 2) {
            return strtoupper(substr($parts[0], 0, 3).' '.substr($parts[1], 0, 3));
        }
        $one = strtoupper(substr((string)($parts[0] ?? 'ACCOUNT'), 0, 8));
        return $one !== '' ? $one : 'ACCOUNT';
    }

    public static function mark(string $name): string {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $word = strtoupper(substr((string)($parts[0] ?? 'Y'), 0, 3));
        return $word !== '' ? $word : 'Y';
    }

    public static function when(?string $utc, string $format = 'd-m-Y H:i'): string {
        $utc = trim((string)$utc);
        if ($utc === '') return '';
        try {
            $dt = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
        } catch (Throwable $e) {
            return $utc;
        }
        $tz = new DateTimeZone(Env::get('APP_TIMEZONE', 'Asia/Kolkata') ?: 'Asia/Kolkata');
        return $dt->setTimezone($tz)->format($format);
    }

    public static function customerById(PDO $db, int $id): ?array {
        $st = $db->prepare('SELECT * FROM customers WHERE id=? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function findCustomer(PDO $db, string $login): ?array {
        TransactionService::ensureSchema($db);
        $login = trim($login);
        if ($login === '') return null;
        $st = $db->prepare('SELECT * FROM customers WHERE active=1 AND (LOWER(IFNULL(email,""))=LOWER(?) OR phone=? OR code=? OR account_number=?) LIMIT 1');
        $st->execute([$login, $login, strtoupper($login), $login]);
        $row = $st->fetch();
        if ($row) return $row;
        $digits = preg_replace('/\D+/', '', $login) ?? '';
        if (strlen($digits) >= 10) {
            $last = substr($digits, -10);
            $st = $db->prepare("SELECT * FROM customers WHERE active=1 AND RIGHT(REPLACE(REPLACE(REPLACE(phone,'+',''),' ',''),'-',''),10)=? LIMIT 1");
            $st->execute([$last]);
            $row = $st->fetch();
            return $row ?: null;
        }
        return null;
    }

    public static function guard(PDO $db, string $code): array {
        TransactionService::ensureSchema($db);
        $c = TransactionService::customerByCode($db, strtoupper($code));
        if (!$c || !(int)$c['active']) {
            throw new RuntimeException('Customer not found or disabled');
        }
        if (!Auth::customerLoggedIn((int)$c['id'])) {
            $next = route_path();
            redirect('/login?next='.rawurlencode($next));
        }
        $_SESSION['customer_code'] = strtoupper((string)$c['code']);
        return $c;
    }

    public static function safeNext(string $next, string $code): string {
        $next = rawurldecode(trim($next));
        $want = '/c/'.strtoupper($code);
        $upperNext = strtoupper($next);
        $upperWant = strtoupper($want);
        if ($upperNext === $upperWant || str_starts_with($upperNext, $upperWant.'/')) {
            return $next;
        }
        return $want;
    }

    /** @return list<array{key:string,amount:float,today:float,pct:float}> */
    public static function cards(PDO $db, int $customerId): array {
        TransactionService::ensureSchema($db);
        [$todayStart, $todayEnd] = local_period_bounds('day');
        $tz = new DateTimeZone(Env::get('APP_TIMEZONE', 'Asia/Kolkata') ?: 'Asia/Kolkata');
        $utc = new DateTimeZone('UTC');
        $yStart = (new DateTimeImmutable('now', $tz))->modify('-1 day')->setTime(0, 0, 0)->setTimezone($utc)->format('Y-m-d H:i:s');
        $sql = "SELECT
            COALESCE(SUM(CASE WHEN status='SUCCESS' THEN COALESCE(confirmed_amount,requested_amount,0) ELSE 0 END),0) success_all,
            COALESCE(SUM(CASE WHEN status='PENDING' THEN COALESCE(requested_amount,0) ELSE 0 END),0) pending_all,
            COALESCE(SUM(CASE WHEN status IN ('FAILED','EXPIRED') THEN COALESCE(requested_amount,0) ELSE 0 END),0) failed_all,
            COALESCE(SUM(CASE WHEN status='SUCCESS' AND created_at>=? AND created_at<? THEN COALESCE(confirmed_amount,requested_amount,0) ELSE 0 END),0) success_today,
            COALESCE(SUM(CASE WHEN status='PENDING' AND created_at>=? AND created_at<? THEN COALESCE(requested_amount,0) ELSE 0 END),0) pending_today,
            COALESCE(SUM(CASE WHEN status IN ('FAILED','EXPIRED') AND created_at>=? AND created_at<? THEN COALESCE(requested_amount,0) ELSE 0 END),0) failed_today,
            COALESCE(SUM(CASE WHEN status='SUCCESS' AND created_at>=? AND created_at<? THEN COALESCE(confirmed_amount,requested_amount,0) ELSE 0 END),0) success_yday,
            COALESCE(SUM(CASE WHEN status='PENDING' AND created_at>=? AND created_at<? THEN COALESCE(requested_amount,0) ELSE 0 END),0) pending_yday,
            COALESCE(SUM(CASE WHEN status IN ('FAILED','EXPIRED') AND created_at>=? AND created_at<? THEN COALESCE(requested_amount,0) ELSE 0 END),0) failed_yday
            FROM transactions WHERE customer_id=?";
        $st = $db->prepare($sql);
        $st->execute([
            $todayStart, $todayEnd, $todayStart, $todayEnd, $todayStart, $todayEnd,
            $yStart, $todayStart, $yStart, $todayStart, $yStart, $todayStart,
            $customerId,
        ]);
        $row = $st->fetch() ?: [];
        $success = (float)($row['success_all'] ?? 0);
        $pending = (float)($row['pending_all'] ?? 0);
        $failed = (float)($row['failed_all'] ?? 0);
        $successToday = (float)($row['success_today'] ?? 0);
        $pendingToday = (float)($row['pending_today'] ?? 0);
        $failedToday = (float)($row['failed_today'] ?? 0);
        $successY = (float)($row['success_yday'] ?? 0);
        $pendingY = (float)($row['pending_yday'] ?? 0);
        $failedY = (float)($row['failed_yday'] ?? 0);
        $pct = static function (float $today, float $prev): float {
            if (abs($prev) < 0.00001) return abs($today) < 0.00001 ? 0.0 : 100.0;
            return round((($today - $prev) / $prev) * 100);
        };
        return [
            ['key' => 'total', 'amount' => $success + $pending + $failed, 'today' => $successToday + $pendingToday + $failedToday, 'pct' => $pct($successToday + $pendingToday + $failedToday, $successY + $pendingY + $failedY)],
            ['key' => 'success', 'amount' => $success, 'today' => $successToday, 'pct' => $pct($successToday, $successY)],
            ['key' => 'pending', 'amount' => $pending, 'today' => $pendingToday, 'pct' => $pct($pendingToday, $pendingY)],
            ['key' => 'failed', 'amount' => $failed, 'today' => $failedToday, 'pct' => $pct($failedToday, $failedY)],
        ];
    }

    /** @return array{0:string,1:string} */
    public static function monthRange(): array {
        $tz = new DateTimeZone(Env::get('APP_TIMEZONE', 'Asia/Kolkata') ?: 'Asia/Kolkata');
        $now = new DateTimeImmutable('now', $tz);
        return [$now->modify('first day of this month')->format('Y-m-d'), $now->format('Y-m-d')];
    }

    /** @return list<array<string,mixed>> */
    public static function payments(PDO $db, int $customerId, string $start, string $end): array {
        return TransactionService::query($db, $customerId, 'custom', '', null, $start, $end, 1000);
    }

    public static function payMode(array $t): string {
        $mode = trim((string)($t['service'] ?? ''));
        if ($mode === '' || strtoupper($mode) === 'UPI') return 'UPI';
        return $mode;
    }

    public static function payStatus(array $t): array {
        $status = strtoupper((string)($t['status'] ?? ''));
        return match ($status) {
            'SUCCESS' => ['Success', 'yp-pill yp-pill-ok'],
            'PENDING' => ['Pending', 'yp-pill yp-pill-wait'],
            'FAILED', 'EXPIRED' => ['Failed', 'yp-pill yp-pill-bad'],
            default => [ucfirst(strtolower($status)), 'yp-pill'],
        };
    }

    /** @return array{base:float,gst:float,total:float} */
    public static function gstSplit(float $total): array {
        $total = round($total, 2);
        $gst = round($total * 18 / 118, 2);
        $base = round($total - $gst, 2);
        return ['base' => $base, 'gst' => $gst, 'total' => $total];
    }

    /** @return list<array<string,mixed>> */
    public static function payers(PDO $db, int $customerId): array {
        TransactionService::ensureSchema($db);
        $sql = "SELECT payer, COUNT(*) n,
            COALESCE(SUM(CASE WHEN status='SUCCESS' THEN COALESCE(confirmed_amount,requested_amount,0) ELSE 0 END),0) paid
            FROM (
                SELECT COALESCE(NULLIF(TRIM(payer_vpa),''), NULLIF(TRIM(customer_reported_email),''), 'Unknown') payer,
                       status, confirmed_amount, requested_amount
                FROM transactions WHERE customer_id=?
            ) x
            GROUP BY payer
            ORDER BY paid DESC
            LIMIT 200";
        $st = $db->prepare($sql);
        $st->execute([$customerId]);
        return $st->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public static function withdrawals(PDO $db, int $customerId): array {
        TransactionService::ensureSchema($db);
        $st = $db->prepare("SELECT t.*, b.bank_name FROM transactions t JOIN bank_profiles b ON b.id=t.bank_id WHERE t.customer_id=? AND t.status='SUCCESS' AND COALESCE(t.settlement_status,'UNSETTLED')='SETTLED' ORDER BY t.id DESC LIMIT 200");
        $st->execute([$customerId]);
        return $st->fetchAll();
    }

    public static function showDevOtp(): bool {
        $host = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')) ?? '');
        return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }
}
