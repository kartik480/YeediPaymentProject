<?php
declare(strict_types=1);

final class InvoiceService {
    public static function download(array $c, array $t): never {
        if (strtoupper((string)($t['status'] ?? '')) !== 'SUCCESS') {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            exit('Invoice is available only after payment success');
        }
        $ref = preg_replace('/[^A-Z0-9]/', '', (string)($t['reference'] ?? 'PAYMENT')) ?: 'PAYMENT';
        self::render($c, $t)->download($ref.'-payment-success-invoice.pdf');
    }

    public static function printPage(array $c, array $t): never {
        if (strtoupper((string)($t['status'] ?? '')) !== 'SUCCESS') {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            exit('Invoice is available only after payment success');
        }
        $ref = (string)($t['reference'] ?? '');
        $amt = TransactionService::displayAmount($t);
        if ($amt === '') $amt = '0.00';
        $when = BankSmsService::formatLocal((string)($t['updated_at'] ?? $t['created_at'] ?? ''));
        $utr = BankSmsService::displayUtr($t);
        $tzName = (string)Env::get('APP_TIMEZONE', 'Asia/Kolkata');
        $rows = [
            ['Payment ID', $ref !== '' ? $ref : '—'],
            ['Paid at', $when !== '' ? $when.' '.$tzName : '—'],
            ['Customer', trim((string)($c['name'] ?? '')) !== '' ? (string)$c['name'] : '—'],
            ['Customer code', trim((string)($c['code'] ?? '')) !== '' ? (string)$c['code'] : '—'],
            ['Amount', 'Rs '.$amt],
            ['Bank', trim((string)($t['bank_name'] ?? '')) !== '' ? (string)$t['bank_name'] : '—'],
        ];
        if ($utr !== '') $rows[] = ['UTR', $utr];
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>'.e($ref).' receipt</title>';
        echo '<style>body{font-family:Arial,sans-serif;color:#111;margin:24px}h1{font-size:20px;margin:0 0 4px}p{margin:0 0 16px;color:#444}.paid{color:#d01212;font-weight:800}table{width:100%;border-collapse:collapse}td{padding:8px 0;border-bottom:1px solid #ddd}td:last-child{text-align:right;font-weight:700}@media print{body{margin:12px}}</style>';
        echo '</head><body><h1>'.e(company_name()).'</h1><p>Payment Success Invoice</p><table>';
        foreach ($rows as [$label, $value]) {
            $cell = e($value);
            if ($label === 'Payment ID' && $ref !== '') {
                $cell .= '<br><span class="paid">PAID</span>';
            }
            echo '<tr><td>'.e($label).'</td><td>'.$cell.'</td></tr>';
        }
        echo '</table><p>Note: The bill amount is inclusive of applicable GST.</p>';
        echo '<script>window.addEventListener("load",function(){window.print();});</script></body></html>';
        exit;
    }

    public static function render(array $c, array $t): PdfDocument {
        $pdf = new PdfDocument();
        $left = $pdf->margin;
        $right = PdfDocument::PAGE_W - $pdf->margin;
        $width = $right - $left;
        $ref = (string)($t['reference'] ?? '');
        $amt = TransactionService::displayAmount($t);
        if ($amt === '') $amt = '0.00';
        $when = BankSmsService::formatLocal((string)($t['updated_at'] ?? $t['created_at'] ?? ''));
        $utr = BankSmsService::displayUtr($t);
        $invoiceRef = trim((string)($t['invoice_ref'] ?? ''));
        $account = trim((string)($c['account_number'] ?? ''));
        $tzName = (string)Env::get('APP_TIMEZONE', 'Asia/Kolkata');

        $nameLines = self::wrap($pdf, company_name(), $width, 13);
        $headerH = 22 + (count($nameLines) * 18) + 28;
        $pdf->fillRect(0, 0, PdfDocument::PAGE_W, $headerH, 0.07, 0.11, 0.22);
        $nameY = 24;
        foreach ($nameLines as $line) {
            $pdf->text($left, $nameY, $line, 13, true, [0.96, 0.77, 0.09]);
            $nameY += 18;
        }
        $pdf->text($left, $nameY + 6, 'Payment Success Invoice', 12, false, [0.96, 0.97, 0.98]);

        $pdf->y = $headerH + 26;
        $pdf->fillRect($left, $pdf->y, $width, 58, 0.93, 0.97, 0.94);
        $pdf->text($left + 14, $pdf->y + 20, 'AMOUNT RECEIVED', 8, true, [0.03, 0.45, 0.19]);
        $pdf->text($left + 14, $pdf->y + 44, 'Rs '.$amt, 20, true, [0.07, 0.11, 0.22]);
        $pdf->textRight($right - 14, $pdf->y + 32, 'SUCCESS', 12, true, [0.03, 0.45, 0.19]);
        $pdf->y += 80;

        $rows = [
            ['Payment ID', $ref !== '' ? $ref : '—'],
            ['Status', 'PAID'],
            ['Paid at', $when !== '' ? $when.' '.$tzName : '—'],
            ['Customer', trim((string)($c['name'] ?? '')) !== '' ? (string)$c['name'] : '—'],
            ['Customer code', trim((string)($c['code'] ?? '')) !== '' ? (string)$c['code'] : '—'],
        ];
        if ($account !== '') $rows[] = ['Account', $account];
        if ($invoiceRef !== '') $rows[] = ['Invoice reference', $invoiceRef];
        $rows[] = ['Bank', trim((string)($t['bank_name'] ?? '')) !== '' ? (string)$t['bank_name'] : '—'];
        $payee = trim((string)($t['payee_name'] ?? ''));
        if ($payee !== '') $rows[] = ['Payee', $payee];
        $vpa = trim((string)($t['vpa'] ?? ''));
        if ($vpa !== '') $rows[] = ['UPI ID', $vpa];
        if ($utr !== '') $rows[] = ['UTR', $utr];

        foreach ($rows as [$label, $value]) {
            $pdf->text($left, $pdf->y, $label, 9, false, [0.4, 0.42, 0.46]);
            $valueColor = $label === 'Status' ? [0.82, 0.07, 0.07] : [0.12, 0.16, 0.22];
            $pdf->textRight($right, $pdf->y, $pdf->fit((string)$value, 320, 10), 10, true, $valueColor);
            $pdf->y += 6;
            $pdf->line($left, $pdf->y, $right);
            $pdf->y += 18;
        }

        $pdf->y += 8;
        $pdf->text($left, $pdf->y, 'Note: The bill amount is inclusive of applicable GST.', 8, false, [0.4, 0.42, 0.46]);
        return $pdf;
    }

    /** @return list<string> */
    private static function wrap(PdfDocument $pdf, string $text, float $maxWidth, float $size): array {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $lines = [];
        $line = '';
        foreach ($words as $word) {
            if ($word === '') continue;
            $try = $line === '' ? $word : $line.' '.$word;
            if ($line !== '' && $pdf->width($try, $size) > $maxWidth) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $try;
            }
        }
        if ($line !== '') $lines[] = $line;
        return $lines !== [] ? $lines : [''];
    }
}
