<?php
declare(strict_types=1);

final class StatementService {
    public static function filtersFromGet(): array {
        $bank = trim((string)($_GET['bank_id'] ?? ''));
        return [
            'period' => (string)($_GET['period'] ?? 'month'),
            'status' => strtoupper(trim((string)($_GET['status'] ?? ''))),
            'bank_id' => $bank !== '' ? (int)$bank : null,
            'start' => trim((string)($_GET['start'] ?? '')),
            'end' => trim((string)($_GET['end'] ?? '')),
        ];
    }

    public static function rows(PDO $db, int $customerId, array $f): array {
        TransactionService::ensureSchema($db);
        return TransactionService::query(
            $db,
            $customerId,
            (string)($f['period'] ?? 'month'),
            (string)($f['status'] ?? ''),
            isset($f['bank_id']) ? ($f['bank_id'] !== null && $f['bank_id'] !== '' ? (int)$f['bank_id'] : null) : null,
            (string)($f['start'] ?? ''),
            (string)($f['end'] ?? ''),
            5000
        );
    }

    /** @return array<int,array{date:string,type:string,reference:string,utr:string,credit:float,debit:float,balance:float,note:string}> */
    public static function ledgerRows(PDO $db, int $customerId, string $start = '', string $end = ''): array {
        TransactionService::ensureSchema($db);
        SettlementService::ensureSchema($db);
        $st = $db->prepare("SELECT t.*, s.reference stl_ref, s.settled_at stl_settled_at
            FROM transactions t
            LEFT JOIN settlements s ON s.id = t.settlement_id AND s.status = 'SETTLED'
            WHERE t.customer_id = ?
            ORDER BY t.id ASC");
        $st->execute([$customerId]);
        $events = [];
        foreach ($st->fetchAll() as $t) {
            $status = strtoupper((string)($t['status'] ?? ''));
            $amt = (float)($t['confirmed_amount'] ?? $t['requested_amount'] ?? 0);
            $utr = trim((string)(($t['customer_reported_utr'] ?? '') !== '' ? $t['customer_reported_utr'] : ($t['rrn'] ?? '')));
            if ($status === 'SUCCESS' && $amt > 0) {
                $events[] = [
                    'ts' => (string)($t['created_at'] ?? ''),
                    'date' => self::fmtWhen((string)($t['created_at'] ?? ''), self::tz()),
                    'type' => 'Collection',
                    'reference' => (string)($t['reference'] ?? ''),
                    'utr' => $utr,
                    'credit' => $amt,
                    'debit' => 0.0,
                    'note' => 'UPI collection',
                ];
                if (strtoupper((string)($t['settlement_status'] ?? '')) === 'SETTLED') {
                    $stlTs = trim((string)($t['stl_settled_at'] ?? ''));
                    if ($stlTs === '') {
                        $stlTs = (string)($t['updated_at'] ?? $t['created_at'] ?? '');
                    }
                    $events[] = [
                        'ts' => $stlTs,
                        'date' => self::fmtWhen($stlTs, self::tz()),
                        'type' => 'Settlement',
                        'reference' => trim((string)($t['stl_ref'] ?? '')) !== '' ? (string)$t['stl_ref'] : (string)($t['reference'] ?? ''),
                        'utr' => $utr,
                        'credit' => 0.0,
                        'debit' => $amt,
                        'note' => 'Payout settlement',
                    ];
                }
            }
        }
        usort($events, static function (array $a, array $b): int {
            $cmp = strcmp($a['ts'], $b['ts']);
            return $cmp !== 0 ? $cmp : strcmp($a['reference'], $b['reference']);
        });
        $balance = 0.0;
        $rows = [];
        [$rangeStart, $rangeEnd] = self::ledgerRangeBounds($start, $end);
        foreach ($events as $ev) {
            $balance = round($balance + $ev['credit'] - $ev['debit'], 2);
            if (!self::eventInRange($ev['ts'], $rangeStart, $rangeEnd)) {
                continue;
            }
            $rows[] = [
                'date' => $ev['date'],
                'type' => $ev['type'],
                'reference' => $ev['reference'],
                'utr' => $ev['utr'],
                'credit' => $ev['credit'],
                'debit' => $ev['debit'],
                'balance' => max(0, $balance),
                'note' => $ev['note'],
            ];
        }
        return array_reverse($rows);
    }

    /** @return array{0:?string,1:?string} UTC bounds for ledger date filter */
    private static function ledgerRangeBounds(string $start, string $end): array {
        if ($start === '' && $end === '') {
            return [null, null];
        }
        if ($start === '' && $end !== '') {
            $start = $end;
        }
        if ($end === '' && $start !== '') {
            $end = $start;
        }
        return local_period_bounds('custom', $start, $end);
    }

    private static function eventInRange(string $utcTs, ?string $rangeStart, ?string $rangeEnd): bool {
        if ($rangeStart === null && $rangeEnd === null) {
            return true;
        }
        $utcTs = trim($utcTs);
        if ($utcTs === '') {
            return false;
        }
        if ($rangeStart !== null && $utcTs < $rangeStart) {
            return false;
        }
        if ($rangeEnd !== null && $utcTs >= $rangeEnd) {
            return false;
        }
        return true;
    }

    private static function tz(): DateTimeZone {
        return new DateTimeZone(Env::get('APP_TIMEZONE', 'Asia/Kolkata'));
    }

    public static function download(array $c, array $rows, array $f): never {
        $code = preg_replace('/[^A-Z0-9]/', '', (string)($c['code'] ?? 'CUSTOMER')) ?: 'CUSTOMER';
        self::build($c, $rows, $f)->download($code.'-payment-statement.pdf');
    }

    private static function build(array $c, array $rows, array $f): PdfDocument {
        $pdf = new PdfDocument();
        $tzName = Env::get('APP_TIMEZONE', 'Asia/Kolkata');
        $tz = new DateTimeZone($tzName);
        $generated = (new DateTimeImmutable('now', $tz))->format('d M Y, H:i');
        $period = self::periodLabel($f, $tz);
        $left = $pdf->margin;
        $right = PdfDocument::PAGE_W - $pdf->margin;
        $width = $right - $left;

        $successAmt = 0.0;
        $pendingAmt = 0.0;
        $rejectedAmt = 0.0;
        $successN = 0;
        $pendingN = 0;
        $rejectedN = 0;
        foreach ($rows as $r) {
            $st = strtoupper((string)($r['status'] ?? ''));
            if ($st === 'SUCCESS') {
                $successAmt += (float)($r['confirmed_amount'] ?? $r['requested_amount'] ?? 0);
                $successN++;
            } elseif ($st === 'PENDING') {
                $pendingAmt += (float)($r['requested_amount'] ?? 0);
                $pendingN++;
            } elseif ($st === 'FAILED') {
                $rejectedAmt += (float)($r['requested_amount'] ?? 0);
                $rejectedN++;
            }
        }

        $drawChrome = static function (PdfDocument $pdf, bool $first) use ($c, $period, $generated, $left, $right, $width, $successAmt, $pendingAmt, $rejectedAmt, $successN, $pendingN, $rejectedN, $tzName): void {
            $pdf->fillRect(0, 0, PdfDocument::PAGE_W, 54, 0.07, 0.11, 0.22);
            $pdf->text($left, 24, company_name(), 14, true, [0.96, 0.77, 0.09]);
            $pdf->text($left, 40, 'Customer payment statement', 10, false, [0.92, 0.93, 0.95]);
            $pdf->textRight($right, 24, (string)($c['code'] ?? ''), 11, true, [1, 1, 1]);
            $pdf->textRight($right, 40, $generated.' '.$tzName, 8, false, [0.75, 0.78, 0.82]);
            $pdf->y = 70;
            if (!$first) {
                $pdf->text($left, $pdf->y, (string)($c['name'] ?? '').'  ·  continued', 9, false, [0.35, 0.4, 0.45]);
                $pdf->y += 16;
                return;
            }
            $pdf->text($left, $pdf->y, (string)($c['name'] ?? ''), 16, true);
            $pdf->y += 18;
            $acc = trim((string)($c['account_number'] ?? ''));
            $bits = [
                'Customer ID '.($c['code'] ?? ''),
                $acc !== '' ? 'Account '.$acc : 'Account not generated yet',
                !empty($c['phone']) ? 'Mobile '.(string)$c['phone'] : '',
            ];
            $pdf->text($left, $pdf->y, implode('  ·  ', array_filter($bits)), 9, false, [0.35, 0.4, 0.45]);
            $pdf->y += 16;
            $pdf->text($left, $pdf->y, 'Period: '.$period, 9, false, [0.35, 0.4, 0.45]);
            $pdf->y += 18;

            $gap = 8.0;
            $boxW = ($width - $gap * 2) / 3;
            $pdf->fillRect($left, $pdf->y, $boxW, 38, 0.93, 0.97, 0.94);
            $pdf->fillRect($left + $boxW + $gap, $pdf->y, $boxW, 38, 0.99, 0.96, 0.9);
            $pdf->fillRect($left + ($boxW + $gap) * 2, $pdf->y, $boxW, 38, 0.99, 0.92, 0.92);
            $pdf->text($left + 10, $pdf->y + 14, 'SUCCESS', 8, true, [0.03, 0.45, 0.19]);
            $pdf->text($left + 10, $pdf->y + 28, 'Rs '.amount_fmt($successAmt).'  ('.$successN.')', 10, true, [0.07, 0.11, 0.22]);
            $pdf->text($left + $boxW + $gap + 10, $pdf->y + 14, 'PENDING', 8, true, [0.64, 0.42, 0]);
            $pdf->text($left + $boxW + $gap + 10, $pdf->y + 28, 'Rs '.amount_fmt($pendingAmt).'  ('.$pendingN.')', 10, true, [0.07, 0.11, 0.22]);
            $pdf->text($left + ($boxW + $gap) * 2 + 10, $pdf->y + 14, 'REJECTED', 8, true, [0.69, 0.12, 0.12]);
            $pdf->text($left + ($boxW + $gap) * 2 + 10, $pdf->y + 28, 'Rs '.amount_fmt($rejectedAmt).'  ('.$rejectedN.')', 10, true, [0.07, 0.11, 0.22]);
            $pdf->y += 52;
        };

        $cols = [
            ['Date', 68],
            ['Reference', 98],
            ['Buyer', 72],
            ['Bank', 72],
            ['Amount', 58],
            ['Status', 48],
            ['UTR', 96],
        ];

        $drawHead = static function (PdfDocument $pdf) use ($cols, $left, $right): void {
            $pdf->need(22);
            $pdf->fillRect($left, $pdf->y - 2, $right - $left, 16, 0.07, 0.11, 0.22);
            $x = $left + 4;
            foreach ($cols as [$label, $w]) {
                $pdf->text($x, $pdf->y + 10, $label, 8, true, [1, 1, 1]);
                $x += $w;
            }
            $pdf->y += 20;
        };

        $drawChrome($pdf, true);
        $drawHead($pdf);

        if (!$rows) {
            $pdf->text($left, $pdf->y + 12, 'No payments in this period.', 10, false, [0.45, 0.48, 0.52]);
            $pdf->y += 28;
        }

        foreach ($rows as $r) {
            if ($pdf->y + 18 > $pdf->bottom()) {
                $pdf->text($left, PdfDocument::PAGE_H - 22, 'SUCCESS is recorded only after bank confirmation.', 8, false, [0.5, 0.52, 0.55]);
                $pdf->addPage();
                $drawChrome($pdf, false);
                $drawHead($pdf);
            }
            $x = $left + 4;
            $base = $pdf->y + 11;
            $vals = [
                self::fmtWhen((string)($r['created_at'] ?? ''), $tz),
                (string)($r['reference'] ?? ''),
                TransactionService::displayBuyerName($r),
                (string)($r['bank_name'] ?? ''),
                (($a = TransactionService::displayAmount($r)) !== '' ? 'Rs '.$a : '—'),
                (string)($r['status'] ?? ''),
                TransactionService::displayVerifiedUtr($r),
            ];
            $i = 0;
            foreach ($cols as [, $w]) {
                $pdf->text($x, $base, $pdf->fit((string)$vals[$i], $w - 6, 8), 8, false);
                $x += $w;
                $i++;
            }
            $pdf->y += 16;
            $pdf->line($left, $pdf->y, $right);
        }

        $pdf->y += 18;
        $pdf->need(36);
        $pdf->text($left, $pdf->y, 'SUCCESS is recorded only after bank confirmation. This statement lists YEEDIPAY platform payment records.', 8, false, [0.4, 0.42, 0.46]);
        $pdf->y += 12;
        $pdf->text($left, $pdf->y, 'Customer payment links never change when the live bank QR changes.', 8, false, [0.4, 0.42, 0.46]);
        return $pdf;
    }

    private static function periodLabel(array $f, DateTimeZone $tz): string {
        $period = strtolower((string)($f['period'] ?? 'month'));
        $start = (string)($f['start'] ?? '');
        $end = (string)($f['end'] ?? '');
        if ($period === 'custom' && $start !== '' && $end !== '') {
            return self::dmy($start).' to '.self::dmy($end);
        }
        [$s, $e] = local_period_bounds($period, $start, $end);
        if (!$s || !$e) {
            return 'All dates in this view';
        }
        $from = (new DateTimeImmutable($s, new DateTimeZone('UTC')))->setTimezone($tz);
        $to = (new DateTimeImmutable($e, new DateTimeZone('UTC')))->setTimezone($tz)->modify('-1 second');
        $name = ['day' => 'Today', 'week' => 'This week', 'month' => 'This month'][$period] ?? ucfirst($period);
        return $name.' ('.$from->format('d M Y').' – '.$to->format('d M Y').')';
    }

    private static function fmtWhen(string $utc, DateTimeZone $tz): string {
        $utc = trim($utc);
        if ($utc === '') return '';
        try {
            return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone($tz)->format('d-m-Y H:i');
        } catch (Throwable $e) {
            return $utc;
        }
    }

    private static function dmy(string $ymd): string {
        try {
            return (new DateTimeImmutable($ymd))->format('d M Y');
        } catch (Throwable $e) {
            return $ymd;
        }
    }
}