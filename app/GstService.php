<?php
declare(strict_types=1);

final class GstService {
    private static bool $schemaReady = false;

    public static function ensureSchema(PDO $db): void {
        if (self::$schemaReady) return;
        BillingService::ensureSchema($db);
        $db->exec("CREATE TABLE IF NOT EXISTS fee_entries (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entry_date DATE NOT NULL,
            customer_id BIGINT UNSIGNED NULL,
            description VARCHAR(255) NOT NULL DEFAULT '',
            taxable_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            gst_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            invoice_ref VARCHAR(64) NULL,
            entry_type VARCHAR(64) NOT NULL DEFAULT 'PLATFORM_FEE',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_fee_entry_date(entry_date),
            INDEX idx_fee_entry_customer(customer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE IF NOT EXISTS gst_monthly_summaries (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            month_key VARCHAR(7) NOT NULL,
            taxable_fees DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            output_gst DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            cgst DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            sgst DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            igst DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            fee_entry_count INT NOT NULL DEFAULT 0,
            eligible_itc DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            other_adjustment DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            net_payable DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            status VARCHAR(24) NOT NULL DEFAULT 'GENERATED',
            generated_at DATETIME NULL,
            emailed_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_gst_month(month_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $gstCols = [];
        foreach ($db->query('SHOW COLUMNS FROM gst_monthly_summaries') as $r) {
            $gstCols[strtolower((string)$r['Field'])] = true;
        }
        if (!isset($gstCols['month_key'])) {
            $db->exec('DROP TABLE IF EXISTS gst_monthly_summaries');
            $db->exec("CREATE TABLE gst_monthly_summaries (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                month_key VARCHAR(7) NOT NULL,
                taxable_fees DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                output_gst DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                cgst DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                sgst DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                igst DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                fee_entry_count INT NOT NULL DEFAULT 0,
                eligible_itc DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                other_adjustment DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                net_payable DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                status VARCHAR(24) NOT NULL DEFAULT 'GENERATED',
                generated_at DATETIME NULL,
                emailed_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_gst_month(month_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
        self::$schemaReady = true;
    }

    public static function normalizeMonth(string $raw): string {
        $raw = trim($raw);
        if (preg_match('/^(\d{4})-(\d{2})$/', $raw, $m)) {
            $y = (int)$m[1];
            $mo = (int)$m[2];
            if ($mo >= 1 && $mo <= 12) return sprintf('%04d-%02d', $y, $mo);
        }
        $tz = new DateTimeZone(Env::get('APP_TIMEZONE', 'Asia/Kolkata'));
        return (new DateTimeImmutable('now', $tz))->format('Y-m');
    }

    /** @return array{start:string,end:string} */
    public static function monthBounds(string $monthKey): array {
        $monthKey = self::normalizeMonth($monthKey);
        $start = $monthKey.'-01';
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $start, new DateTimeZone('UTC'));
        if (!$dt) throw new InvalidArgumentException('Invalid month');
        $end = $dt->modify('last day of this month')->format('Y-m-d');
        return ['start' => $start, 'end' => $end];
    }

    /** @return array<string,mixed> */
    public static function summary(PDO $db, string $monthKey, bool $create = true): array {
        self::ensureSchema($db);
        $monthKey = self::normalizeMonth($monthKey);
        $st = $db->prepare('SELECT * FROM gst_monthly_summaries WHERE month_key=? LIMIT 1');
        $st->execute([$monthKey]);
        $row = $st->fetch();
        if (!$row && $create) {
            self::recalculate($db, $monthKey, 0.0, 0.0);
            $st->execute([$monthKey]);
            $row = $st->fetch();
        }
        return $row ?: self::emptySummary($monthKey);
    }

    /** @return list<array<string,mixed>> */
    public static function history(PDO $db, int $limit = 24): array {
        self::ensureSchema($db);
        $st = $db->prepare('SELECT * FROM gst_monthly_summaries ORDER BY month_key DESC LIMIT ?');
        $st->bindValue(1, max(1, min(120, $limit)), PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll();
    }

    /** @return array<string,mixed> */
    public static function recalculate(PDO $db, string $monthKey, float $eligibleItc, float $otherAdjustment): array {
        self::ensureSchema($db);
        $monthKey = self::normalizeMonth($monthKey);
        ['start' => $start, 'end' => $end] = self::monthBounds($monthKey);
        $st = $db->prepare('SELECT COALESCE(SUM(taxable_amount),0) taxable, COUNT(*) cnt FROM fee_entries WHERE entry_date>=? AND entry_date<=?');
        $st->execute([$start, $end]);
        $fees = $st->fetch() ?: ['taxable' => 0, 'cnt' => 0];
        $taxable = (float)($fees['taxable'] ?? 0);
        $feeCount = (int)($fees['cnt'] ?? 0);
        $settings = BillingService::settings($db);
        $gstRate = (float)($settings['gst_rate'] ?? 18.0);
        $outputGst = round($taxable * $gstRate / 100, 2);
        $state = trim((string)($settings['registered_state_code'] ?? ''));
        if ($state !== '') {
            $half = round($outputGst / 2, 2);
            $cgst = $half;
            $sgst = round($outputGst - $half, 2);
            $igst = 0.0;
        } else {
            $cgst = 0.0;
            $sgst = 0.0;
            $igst = $outputGst;
        }
        $eligibleItc = round(max(0, $eligibleItc), 2);
        $otherAdjustment = round($otherAdjustment, 2);
        $netPayable = round($outputGst - $eligibleItc + $otherAdjustment, 2);

        $existing = $db->prepare('SELECT id FROM gst_monthly_summaries WHERE month_key=?');
        $existing->execute([$monthKey]);
        $id = (int)($existing->fetchColumn() ?: 0);
        if ($id > 0) {
            $sql = 'UPDATE gst_monthly_summaries SET taxable_fees=?,output_gst=?,cgst=?,sgst=?,igst=?,fee_entry_count=?,eligible_itc=?,other_adjustment=?,net_payable=?,status=?,generated_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE id=?';
            $db->prepare($sql)->execute([$taxable, $outputGst, $cgst, $sgst, $igst, $feeCount, $eligibleItc, $otherAdjustment, $netPayable, 'GENERATED', $id]);
        } else {
            $sql = 'INSERT INTO gst_monthly_summaries(month_key,taxable_fees,output_gst,cgst,sgst,igst,fee_entry_count,eligible_itc,other_adjustment,net_payable,status,generated_at,created_at,updated_at)
                VALUES(?,?,?,?,?,?,?,?,?,?,\'GENERATED\',UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP())';
            $db->prepare($sql)->execute([$monthKey, $taxable, $outputGst, $cgst, $sgst, $igst, $feeCount, $eligibleItc, $otherAdjustment, $netPayable]);
        }
        return self::summary($db, $monthKey, false);
    }

    public static function parseAmount(mixed $v): float {
        $s = str_replace(',', '', trim((string)$v));
        if ($s === '' || !preg_match('/^-?\d+(?:\.\d{1,2})?$/', $s)) return 0.0;
        return (float)$s;
    }

    /** @return array<string,mixed> */
    public static function emailSummary(PDO $db, string $monthKey): array {
        self::ensureSchema($db);
        EmailService::ensureSchema($db);
        $summary = self::summary($db, $monthKey);
        $settings = BillingService::settings($db);
        $to = trim((string)($settings['gst_summary_email'] ?? ''));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Configure GST Summary Email in Billing settings first.');
        }
        $subject = 'YEEDIPAY GST Summary · '.$summary['month_key'];
        $body = "Monthly GST Summary (YEEDIPAY fee entries only)\n\n"
            ."Month: {$summary['month_key']}\n"
            .'Taxable Fees: Rs.'.amount_fmt($summary['taxable_fees'])."\n"
            .'Output GST: Rs.'.amount_fmt($summary['output_gst'])."\n"
            .'CGST: Rs.'.amount_fmt($summary['cgst']).' | SGST: Rs.'.amount_fmt($summary['sgst']).' | IGST: Rs.'.amount_fmt($summary['igst'])."\n"
            .'Eligible ITC: Rs.'.amount_fmt($summary['eligible_itc'])."\n"
            .'Adjustment: Rs.'.amount_fmt($summary['other_adjustment'])."\n"
            .'Estimated Net Payable: Rs.'.amount_fmt($summary['net_payable'])."\n\n"
            ."Verify with your CA/tax adviser before filing or payment.\n";
        $key = 'gst-summary:'.$summary['month_key'].':'.$to;
        $db->prepare("INSERT IGNORE INTO email_outbox(event_key,recipient,subject,body,status,created_at) VALUES(?,?,?,?,'QUEUED',UTC_TIMESTAMP())")
            ->execute([$key, $to, $subject, $body]);
        self::sendQueuedEmail($db, $key);
        $db->prepare('UPDATE gst_monthly_summaries SET emailed_at=UTC_TIMESTAMP(), updated_at=UTC_TIMESTAMP() WHERE month_key=?')
            ->execute([$summary['month_key']]);
        $summary['emailed_at'] = gmdate('Y-m-d H:i:s');
        return $summary;
    }

    private static function sendQueuedEmail(PDO $db, string $eventKey): void {
        $st = $db->prepare("SELECT * FROM email_outbox WHERE event_key=? AND status='QUEUED' LIMIT 1");
        $st->execute([$eventKey]);
        $row = $st->fetch();
        if (!$row) return;
        $from = trim((string)Env::get('MAIL_FROM', 'noreply@yeedipay.in'));
        $ok = @mail((string)$row['recipient'], (string)$row['subject'], (string)$row['body'], 'From: '.$from."\r\n".'Content-Type: text/plain; charset=UTF-8');
        if ($ok) {
            $db->prepare("UPDATE email_outbox SET status='SENT', sent_at=UTC_TIMESTAMP(), last_error='' WHERE id=?")->execute([$row['id']]);
        } else {
            $db->prepare("UPDATE email_outbox SET status='FAILED', last_error='mail() returned false' WHERE id=?")->execute([$row['id']]);
            throw new RuntimeException('Email could not be sent. Check server mail configuration.');
        }
    }

    public static function exportCsv(PDO $db, string $monthKey): never {
        $summary = self::summary($db, $monthKey);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="gst-summary-'.$summary['month_key'].'.csv"');
        $o = fopen('php://output', 'wb');
        fwrite($o, "\xEF\xBB\xBF");
        fputcsv($o, ['Month', 'Taxable Fees', 'Output GST', 'CGST', 'SGST', 'IGST', 'Eligible ITC', 'Adjustment', 'Net Payable', 'Fee Entries', 'Status']);
        fputcsv($o, [
            $summary['month_key'],
            $summary['taxable_fees'],
            $summary['output_gst'],
            $summary['cgst'],
            $summary['sgst'],
            $summary['igst'],
            $summary['eligible_itc'],
            $summary['other_adjustment'],
            $summary['net_payable'],
            $summary['fee_entry_count'],
            $summary['status'],
        ]);
        exit;
    }

    public static function exportPdf(PDO $db, string $monthKey): never {
        $summary = self::summary($db, $monthKey);
        $settings = BillingService::settings($db);
        $pdf = new PdfDocument();
        $left = $pdf->margin;
        $pdf->text($left, $pdf->y, 'YEEDIPAY GST Summary', 16, true);
        $pdf->y += 22;
        $pdf->text($left, $pdf->y, 'Month: '.$summary['month_key'], 12, true);
        $pdf->y += 18;
        $pdf->text($left, $pdf->y, 'Legal Name: '.(string)($settings['legal_name'] ?? ''), 10);
        $pdf->y += 14;
        $pdf->text($left, $pdf->y, 'GSTIN: '.(trim((string)($settings['gstin'] ?? '')) !== '' ? $settings['gstin'] : '—'), 10);
        $pdf->y += 20;
        $rows = [
            ['Taxable Fees', 'Rs.'.amount_fmt($summary['taxable_fees'])],
            ['Output GST', 'Rs.'.amount_fmt($summary['output_gst'])],
            ['CGST', 'Rs.'.amount_fmt($summary['cgst'])],
            ['SGST', 'Rs.'.amount_fmt($summary['sgst'])],
            ['IGST', 'Rs.'.amount_fmt($summary['igst'])],
            ['Eligible ITC', 'Rs.'.amount_fmt($summary['eligible_itc'])],
            ['Adjustment', 'Rs.'.amount_fmt($summary['other_adjustment'])],
            ['Estimated Net Payable', 'Rs.'.amount_fmt($summary['net_payable'])],
            ['Fee Entries', (string)($summary['fee_entry_count'] ?? 0)],
        ];
        foreach ($rows as [$label, $value]) {
            $pdf->text($left, $pdf->y, $label.': '.$value, 10);
            $pdf->y += 14;
        }
        $pdf->y += 8;
        $pdf->text($left, $pdf->y, 'Generated from YEEDIPAY fee entries only. Verify before filing.', 9, false, [0.4, 0.42, 0.46]);
        $pdf->download('gst-summary-'.$summary['month_key'].'.pdf');
    }

    public static function monthLabel(string $monthKey): string {
        $monthKey = self::normalizeMonth($monthKey);
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $monthKey.'-01', new DateTimeZone('UTC'));
        return $dt ? $dt->format('F, Y') : $monthKey;
    }

    public static function formatGeneratedAt(?string $utc): string {
        if ($utc === null || trim($utc) === '') return '—';
        $ts = strtotime($utc.' UTC');
        if ($ts === false) return $utc;
        $tz = new DateTimeZone(Env::get('APP_TIMEZONE', 'Asia/Kolkata'));
        return (new DateTimeImmutable('@'.$ts))->setTimezone($tz)->format('Y-m-d H:i:s');
    }

    /** @return array<string,mixed> */
    public static function emptySummary(string $monthKey): array {
        return [
            'month_key' => $monthKey,
            'taxable_fees' => 0,
            'output_gst' => 0,
            'cgst' => 0,
            'sgst' => 0,
            'igst' => 0,
            'fee_entry_count' => 0,
            'eligible_itc' => 0,
            'other_adjustment' => 0,
            'net_payable' => 0,
            'status' => 'GENERATED',
            'generated_at' => null,
            'emailed_at' => null,
        ];
    }
}
