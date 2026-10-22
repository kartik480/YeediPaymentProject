<?php
declare(strict_types=1);

final class InvoiceService {
    public static function download(array $c, array $t): never {
        $ref = preg_replace('/[^A-Z0-9]/', '', (string)($t['reference'] ?? 'PAYMENT')) ?: 'PAYMENT';
        self::render($c, $t)->download($ref.'-payment-success-invoice.pdf');
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

        $pdf->fillRect(0, 0, PdfDocument::PAGE_W, 78, 0.07, 0.11, 0.22);
        $pdf->text($left, 30, company_name(), 16, true, [0.96, 0.77, 0.09]);
        $pdf->text($left, 52, 'Payment Success Invoice', 12, false, [0.96, 0.97, 0.98]);
        $pdf->textRight($right, 30, 'PAID', 16, true, [0.55, 0.9, 0.62]);
        $pdf->textRight($right, 52, $ref, 9, false, [0.8, 0.84, 0.88]);

        $pdf->y = 104;
        $pdf->fillRect($left, $pdf->y, $width, 58, 0.93, 0.97, 0.94);
        $pdf->text($left + 14, $pdf->y + 20, 'AMOUNT RECEIVED', 8, true, [0.03, 0.45, 0.19]);
        $pdf->text($left + 14, $pdf->y + 44, 'Rs '.$amt, 20, true, [0.07, 0.11, 0.22]);
        $pdf->textRight($right - 14, $pdf->y + 32, 'SUCCESS', 12, true, [0.03, 0.45, 0.19]);
        $pdf->y += 80;

        $rows = [
            ['Payment ID', $ref !== '' ? $ref : '—'],
            ['Status', 'SUCCESS'],
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
            $pdf->textRight($right, $pdf->y, $pdf->fit((string)$value, 320, 10), 10, true);
            $pdf->y += 6;
            $pdf->line($left, $pdf->y, $right);
            $pdf->y += 18;
        }

        $pdf->y += 8;
        $pdf->text($left, $pdf->y, 'This invoice is issued only after the bank credit SMS confirmed the payment.', 8, false, [0.4, 0.42, 0.46]);
        return $pdf;
    }
}
