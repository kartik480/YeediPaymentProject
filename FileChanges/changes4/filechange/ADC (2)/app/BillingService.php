<?php
declare(strict_types=1);

final class BillingService {
    private static bool $schemaReady = false;

    public static function ensureSchema(PDO $db): void {
        if (self::$schemaReady) return;
        $db->exec("CREATE TABLE IF NOT EXISTS billing_settings (
            id TINYINT UNSIGNED PRIMARY KEY,
            legal_name VARCHAR(220) NOT NULL DEFAULT '',
            gstin VARCHAR(20) NULL,
            registered_state_code VARCHAR(2) NULL,
            gst_rate DECIMAL(8,3) NOT NULL DEFAULT 18.000,
            gst_summary_email VARCHAR(180) NULL,
            accounts_email VARCHAR(180) NULL,
            fee_invoice_prefix VARCHAR(32) NOT NULL DEFAULT 'YDFEE',
            duplicate_scan_minutes INT NOT NULL DEFAULT 10,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("INSERT IGNORE INTO billing_settings(id,legal_name,gst_summary_email,accounts_email,fee_invoice_prefix,duplicate_scan_minutes,gst_rate)
            VALUES(1,'YEEDI PAYMENT SOLUTIONS LIMITED','admin@yeedipay.local','admin@yeedipay.local','YDFEE',10,18.000)");

        TransactionService::ensureSchema($db);
        $cust = [];
        foreach ($db->query('SHOW COLUMNS FROM customers') as $r) {
            $cust[strtolower((string)$r['Field'])] = true;
        }
        if (!isset($cust['fixed_fee'])) {
            $db->exec('ALTER TABLE customers ADD COLUMN fixed_fee DECIMAL(14,2) NULL');
        }
        if (!isset($cust['pct_fee'])) {
            $db->exec('ALTER TABLE customers ADD COLUMN pct_fee DECIMAL(8,3) NULL');
        }
        if (!isset($cust['setup_fee'])) {
            $db->exec('ALTER TABLE customers ADD COLUMN setup_fee DECIMAL(14,2) NULL');
        }
        if (!isset($cust['merchant_qr_fee'])) {
            $db->exec('ALTER TABLE customers ADD COLUMN merchant_qr_fee DECIMAL(14,2) NULL');
        }

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
        $feeCols = [];
        foreach ($db->query('SHOW COLUMNS FROM fee_entries') as $r) {
            $feeCols[strtolower((string)$r['Field'])] = true;
        }
        if (!isset($feeCols['entry_type'])) {
            $db->exec("ALTER TABLE fee_entries ADD COLUMN entry_type VARCHAR(64) NOT NULL DEFAULT 'PLATFORM_FEE'");
        }

        self::$schemaReady = true;
    }

    /** @return array<string,mixed> */
    public static function settings(PDO $db): array {
        self::ensureSchema($db);
        $row = $db->query('SELECT * FROM billing_settings WHERE id=1')->fetch();
        if (!$row) {
            self::ensureSchema($db);
            $row = $db->query('SELECT * FROM billing_settings WHERE id=1')->fetch() ?: [];
        }
        $company = trim((string)Env::get('COMPANY_NAME', ''));
        if ($company !== '' && trim((string)($row['legal_name'] ?? '')) === 'YEEDI PAYMENT SOLUTIONS LIMITED') {
            $row['legal_name'] = $company;
        }
        return $row;
    }

    /** @param array<string,mixed> $in */
    public static function saveSettings(PDO $db, array $in): void {
        self::ensureSchema($db);
        $legal = trim((string)($in['legal_name'] ?? ''));
        if ($legal === '') throw new InvalidArgumentException('Legal Name is required');
        $gstin = strtoupper(trim((string)($in['gstin'] ?? '')));
        if ($gstin !== '' && !preg_match('/^[0-9A-Z]{15}$/', $gstin)) {
            throw new InvalidArgumentException('GSTIN must be 15 characters when provided');
        }
        $state = trim((string)($in['registered_state_code'] ?? ''));
        if ($state !== '' && !preg_match('/^\d{2}$/', $state)) {
            throw new InvalidArgumentException('Registered State Code must be 2 digits');
        }
        $gstRate = trim((string)($in['gst_rate'] ?? '18.000'));
        if (!preg_match('/^\d+(?:\.\d{1,3})?$/', $gstRate)) {
            throw new InvalidArgumentException('Invalid GST rate');
        }
        $gstEmail = strtolower(trim((string)($in['gst_summary_email'] ?? '')));
        if ($gstEmail !== '' && !filter_var($gstEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid GST Summary Email');
        }
        $accountsEmail = strtolower(trim((string)($in['accounts_email'] ?? '')));
        if ($accountsEmail !== '' && !filter_var($accountsEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid Accounts Email');
        }
        $prefix = strtoupper(trim((string)($in['fee_invoice_prefix'] ?? 'YDFEE')));
        if ($prefix === '') throw new InvalidArgumentException('Fee Invoice Prefix is required');
        $dup = (int)($in['duplicate_scan_minutes'] ?? 10);
        if ($dup < 0) throw new InvalidArgumentException('Duplicate Scan Window must be 0 or more');

        $st = $db->prepare('UPDATE billing_settings SET legal_name=?,gstin=?,registered_state_code=?,gst_rate=?,gst_summary_email=?,accounts_email=?,fee_invoice_prefix=?,duplicate_scan_minutes=?,updated_at=UTC_TIMESTAMP() WHERE id=1');
        $st->execute([
            $legal,
            $gstin !== '' ? $gstin : null,
            $state !== '' ? $state : null,
            number_format((float)$gstRate, 3, '.', ''),
            $gstEmail !== '' ? $gstEmail : null,
            $accountsEmail !== '' ? $accountsEmail : null,
            substr($prefix, 0, 32),
            $dup,
        ]);
    }

    /** @return list<array<string,mixed>> */
    public static function customerCommissionPlans(PDO $db): array {
        self::ensureSchema($db);
        return $db->query("SELECT id,code,name,plan,fixed_fee,pct_fee,setup_fee,merchant_qr_fee,active
            FROM customers ORDER BY name ASC LIMIT 500")->fetchAll();
    }

    public static function moneyDisplay(mixed $v): string {
        if ($v === null || $v === '') return '—';
        return '₹'.amount_fmt((float)$v);
    }

    public static function pctDisplay(mixed $v): string {
        if ($v === null || $v === '') return '—';
        return rtrim(rtrim(number_format((float)$v, 3, '.', ''), '0'), '.').'%';
    }

    /** @return array<string,mixed> */
    public static function customerBilling(PDO $db, int $customerId): array {
        self::ensureSchema($db);
        $st = $db->prepare('SELECT code,name,plan,fixed_fee,pct_fee,setup_fee,merchant_qr_fee,subscription_ends,gst,legal_name FROM customers WHERE id=?');
        $st->execute([$customerId]);
        return $st->fetch() ?: [];
    }

    /** @return array{total_fees:float,total_gst:float,qr_scans:int,plan:string} */
    public static function customerPlatformSummary(PDO $db, int $customerId): array {
        self::ensureSchema($db);
        $billing = self::customerBilling($db, $customerId);
        $plan = strtoupper(trim((string)($billing['plan'] ?? '')));
        if ($plan === '') {
            $plan = 'NONE';
        }
        $st = $db->prepare("SELECT
            COALESCE(SUM(taxable_amount),0) total_fees,
            COALESCE(SUM(gst_amount),0) total_gst,
            COALESCE(SUM(CASE WHEN entry_type='QR_SCAN' THEN 1 ELSE 0 END),0) qr_scans
            FROM fee_entries WHERE customer_id=?");
        $st->execute([$customerId]);
        $row = $st->fetch() ?: [];
        return [
            'total_fees' => (float)($row['total_fees'] ?? 0),
            'total_gst' => (float)($row['total_gst'] ?? 0),
            'qr_scans' => (int)($row['qr_scans'] ?? 0),
            'plan' => $plan,
        ];
    }

    /** @return list<array{date:string,invoice:string,type:string,description:string,taxable:float,gst:float,gross:float}> */
    public static function customerFeeEntries(PDO $db, int $customerId): array {
        self::ensureSchema($db);
        $st = $db->prepare('SELECT entry_date,invoice_ref,entry_type,description,taxable_amount,gst_amount FROM fee_entries WHERE customer_id=? ORDER BY entry_date DESC, id DESC LIMIT 500');
        $st->execute([$customerId]);
        $rows = [];
        foreach ($st->fetchAll() as $r) {
            $taxable = (float)($r['taxable_amount'] ?? 0);
            $gst = (float)($r['gst_amount'] ?? 0);
            $rows[] = [
                'date' => self::fmtEntryDate((string)($r['entry_date'] ?? '')),
                'invoice' => trim((string)($r['invoice_ref'] ?? '')),
                'type' => strtoupper(trim((string)($r['entry_type'] ?? 'PLATFORM_FEE'))),
                'description' => (string)($r['description'] ?? ''),
                'taxable' => $taxable,
                'gst' => $gst,
                'gross' => round($taxable + $gst, 2),
            ];
        }
        return $rows;
    }

    /** @param list<array<string,mixed>> $rows */
    public static function downloadCustomerFeesCsv(array $c, array $rows): never {
        $code = preg_replace('/[^A-Z0-9]/', '', (string)($c['code'] ?? 'CUSTOMER')) ?: 'CUSTOMER';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$code.'-platform-fees.csv"');
        $o = fopen('php://output', 'wb');
        fwrite($o, "\xEF\xBB\xBF");
        fputcsv($o, ['date', 'invoice', 'type', 'description', 'taxable', 'gst', 'gross']);
        foreach ($rows as $r) {
            fputcsv($o, [
                $r['date'] ?? '',
                $r['invoice'] ?? '',
                $r['type'] ?? '',
                $r['description'] ?? '',
                $r['taxable'] ?? 0,
                $r['gst'] ?? 0,
                $r['gross'] ?? 0,
            ]);
        }
        exit;
    }

    /** @param array{total_fees:float,total_gst:float,qr_scans:int,plan:string} $summary @param list<array<string,mixed>> $rows */
    public static function downloadCustomerFeesPdf(array $c, array $summary, array $rows): never {
        $code = preg_replace('/[^A-Z0-9]/', '', (string)($c['code'] ?? 'CUSTOMER')) ?: 'CUSTOMER';
        $pdf = new PdfDocument();
        $left = $pdf->margin;
        $right = PdfDocument::PAGE_W - $pdf->margin;
        $pdf->text($left, $pdf->y, company_name(), 12, true);
        $pdf->y += 16;
        $pdf->text($left, $pdf->y, 'Platform Fees & GST — '.($c['code'] ?? ''), 10, false, [0.75, 0.78, 0.82]);
        $pdf->y += 22;
        $pdf->text($left, $pdf->y, 'Total Fees: Rs '.amount_fmt($summary['total_fees'] ?? 0).'   GST: Rs '.amount_fmt($summary['total_gst'] ?? 0).'   QR Scans: '.(string)($summary['qr_scans'] ?? 0).'   Plan: '.($summary['plan'] ?? 'NONE'), 9);
        $pdf->y += 20;
        $cols = [['Date', 62], ['Invoice', 72], ['Type', 58], ['Description', 120], ['Taxable', 58], ['GST', 48], ['Gross', 58]];
        $pdf->fillRect($left, $pdf->y - 2, $right - $left, 14, 0.07, 0.11, 0.22);
        $x = $left + 4;
        foreach ($cols as [$label, $w]) {
            $pdf->text($x, $pdf->y + 9, $label, 7, true, [1, 1, 1]);
            $x += $w;
        }
        $pdf->y += 18;
        if (!$rows) {
            $pdf->text($left, $pdf->y + 10, 'No fee entries yet.', 9, false, [0.45, 0.48, 0.52]);
        }
        foreach ($rows as $r) {
            if ($pdf->y + 16 > $pdf->bottom()) {
                $pdf->addPage();
            }
            $x = $left + 4;
            $base = $pdf->y + 10;
            $vals = [
                (string)($r['date'] ?? ''),
                (string)($r['invoice'] ?? ''),
                (string)($r['type'] ?? ''),
                (string)($r['description'] ?? ''),
                'Rs '.amount_fmt((float)($r['taxable'] ?? 0)),
                'Rs '.amount_fmt((float)($r['gst'] ?? 0)),
                'Rs '.amount_fmt((float)($r['gross'] ?? 0)),
            ];
            $i = 0;
            foreach ($cols as [, $w]) {
                $pdf->text($x, $base, $pdf->fit((string)$vals[$i], $w - 4, 7), 7, false);
                $x += $w;
                $i++;
            }
            $pdf->y += 14;
            $pdf->line($left, $pdf->y, $right);
        }
        $pdf->download($code.'-platform-fees.pdf');
    }

    private static function fmtEntryDate(string $ymd): string {
        $ymd = trim($ymd);
        if ($ymd === '') {
            return '';
        }
        try {
            return (new DateTimeImmutable($ymd))->format('d-m-Y');
        } catch (Throwable $e) {
            return $ymd;
        }
    }
}
