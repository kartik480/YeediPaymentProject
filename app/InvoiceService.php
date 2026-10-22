<?php
declare(strict_types=1);

final class InvoiceService {
    /** Merchant title on the receipt (header uses the customer business name). */
    public static function brandForInvoice(array $c): string {
        $name = trim((string)($c['name'] ?? ''));
        if ($name !== '') {
            return $name;
        }
        return company_name();
    }

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
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        echo self::printHtml($c, $t, true);
        exit;
    }

    /** HTML receipt for browser print (same layout as post-SUCCESS auto-print). */
    public static function printHtml(array $c, array $t, bool $autoPrint = false): string {
        if (strtoupper((string)($t['status'] ?? '')) !== 'SUCCESS') {
            throw new InvalidArgumentException('Invoice is available only after payment success');
        }
        $brand = self::brandForInvoice($c);
        $amt = TransactionService::displayAmount($t);
        if ($amt === '') {
            $amt = '0.00';
        }
        $rows = self::detailRows($c, $t);
        $html = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
        $html .= '<title>'.e($brand).' — Payment Success Invoice</title>';
        $html .= '<style>';
        $html .= '*{box-sizing:border-box}body{margin:0;font-family:Arial,Helvetica,sans-serif;color:#121a2a;background:#fff}';
        $html .= '.inv{max-width:720px;margin:0 auto}.hdr{background:#121c38;padding:26px 32px 22px}.brand{margin:0;color:#f5c518;font-size:22px;font-weight:800;letter-spacing:.02em;line-height:1.25}';
        $html .= '.inv-title{margin:10px 0 0;color:#f5f7fa;font-size:14px;font-weight:400}.amt-band{margin:28px 32px 0;background:#edf7f0;padding:16px 20px;display:flex;align-items:flex-start;justify-content:space-between;gap:16px}';
        $html .= '.amt-lbl{font-size:11px;font-weight:700;color:#0b6b3a;letter-spacing:.04em}.amt-val{margin-top:6px;font-size:26px;font-weight:800;color:#121c38;line-height:1.1}';
        $html .= '.amt-ok{font-size:14px;font-weight:800;color:#0b6b3a;padding-top:4px}.details{margin:8px 32px 0;padding-bottom:24px}.row{display:flex;justify-content:space-between;gap:20px;padding:14px 0;border-bottom:1px solid #e2e6ec;align-items:flex-start}';
        $html .= '.row-lbl{font-size:13px;color:#6b7280;flex:0 0 auto}.row-val{font-size:13px;font-weight:700;text-align:right;color:#121a2a;word-break:break-word;max-width:72%}.row-val.paid{color:#d01212}';
        $html .= '.gst{margin:20px 32px 32px;font-size:12px;color:#6b7280;line-height:1.5}';
        $html .= '.preview-bar{margin:0;padding:12px 16px;background:#fff4f1;border-bottom:1px solid #f3d2cb;text-align:center;font-size:14px}';
        $html .= '.preview-bar button{padding:10px 18px;border:0;border-radius:9px;font-weight:800;cursor:pointer;background:linear-gradient(90deg,#F0442E,#ff7a45);color:#fff}';
        $html .= '@media screen and (max-width:640px){.brand{font-size:18px}.amt-band{margin:20px 16px 0;flex-wrap:wrap}.hdr,.details,.gst{padding-left:16px;padding-right:16px}.row{flex-direction:column;gap:6px}.row-val{text-align:left;max-width:100%}.amt-val{font-size:22px}}';
        $html .= '@page{size:A4;margin:14mm}@media print{body{background:#fff}.preview-bar{display:none!important}.inv{max-width:none}.hdr,.amt-band,.details,.gst{margin-left:0;margin-right:0;padding-left:0;padding-right:0}.hdr{padding:22px 0 18px}.amt-band{margin-top:20px;padding:14px 16px}.details{margin-top:4px}.gst{margin:16px 0 0}.row{flex-direction:row}.row-val{text-align:right}}';
        $html .= '</style></head><body>';
        if (!$autoPrint) {
            $html .= '<div class="preview-bar">Sample preview — same as after payment SUCCESS. <button type="button" onclick="window.print()">Open print dialog</button></div>';
        }
        $html .= '<div class="inv"><header class="hdr"><h1 class="brand">'.e($brand).'</h1><p class="inv-title">Payment Success Invoice</p></header>';
        $html .= '<div class="amt-band"><div><div class="amt-lbl">AMOUNT RECEIVED</div><div class="amt-val">Rs '.e($amt).'</div></div><div class="amt-ok">SUCCESS</div></div>';
        $html .= '<div class="details">';
        foreach ($rows as [$label, $value, $paidStyle]) {
            $cls = $paidStyle ? ' row-val paid' : ' row-val';
            $html .= '<div class="row"><span class="row-lbl">'.e($label).'</span><span class="'.$cls.'">'.e($value).'</span></div>';
        }
        $html .= '</div><p class="gst">Note: The bill amount is inclusive of applicable GST.</p></div>';
        if ($autoPrint) {
            $html .= '<script>window.addEventListener("load",function(){window.print();});</script>';
        }
        $html .= '</body></html>';
        return $html;
    }

    public static function render(array $c, array $t): PdfDocument {
        $pdf = new PdfDocument();
        $left = $pdf->margin;
        $right = PdfDocument::PAGE_W - $pdf->margin;
        $width = $right - $left;
        $amt = TransactionService::displayAmount($t);
        if ($amt === '') {
            $amt = '0.00';
        }

        $nameLines = self::wrap($pdf, self::brandForInvoice($c), $width, 13);
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

        foreach (self::detailRows($c, $t) as [$label, $value, $paidStyle]) {
            $pdf->text($left, $pdf->y, $label, 9, false, [0.4, 0.42, 0.46]);
            $valueColor = $paidStyle ? [0.82, 0.07, 0.07] : [0.12, 0.16, 0.22];
            $pdf->textRight($right, $pdf->y, $pdf->fit((string)$value, 320, 10), 10, true, $valueColor);
            $pdf->y += 6;
            $pdf->line($left, $pdf->y, $right);
            $pdf->y += 18;
        }

        $pdf->y += 8;
        $pdf->text($left, $pdf->y, 'Note: The bill amount is inclusive of applicable GST.', 8, false, [0.4, 0.42, 0.46]);
        return $pdf;
    }

    /**
     * @return list{array{0:string,1:string,2:bool}}
     */
    private static function detailRows(array $c, array $t): array {
        $ref = (string)($t['reference'] ?? '');
        $when = BankSmsService::formatLocal((string)($t['updated_at'] ?? $t['created_at'] ?? ''));
        $tzName = (string)Env::get('APP_TIMEZONE', 'Asia/Kolkata');
        $paidAt = $when !== '' ? $when.' '.$tzName : '—';

        $buyer = TransactionService::displayBuyerName($t);
        if ($buyer === 'Not Available') {
            $buyer = '—';
        }

        $account = trim((string)($c['account_number'] ?? ''));
        $utr = TransactionService::displayVerifiedUtr($t);
        if ($utr === 'Not Available') {
            $utr = TransactionService::displayUtr($t);
        }

        return [
            ['Payment ID', $ref !== '' ? $ref : '—', false],
            ['Status', 'PAID', true],
            ['Paid at', $paidAt, false],
            ['Customer', $buyer, false],
            ['Customer code', trim((string)($c['code'] ?? '')) !== '' ? (string)$c['code'] : '—', false],
            ['Account', $account !== '' ? $account : '—', false],
            ['Bank', self::field($t['bank_name'] ?? ''), false],
            ['Payee', self::field($t['payee_name'] ?? ''), false],
            ['UPI ID', self::field($t['vpa'] ?? ''), false],
            ['UTR', $utr !== '' ? $utr : '—', false],
        ];
    }

    private static function field(mixed $v): string {
        $s = trim((string)$v);
        return $s !== '' ? $s : '—';
    }

    /** @return list<string> */
    private static function wrap(PdfDocument $pdf, string $text, float $maxWidth, float $size): array {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $lines = [];
        $line = '';
        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }
            $try = $line === '' ? $word : $line.' '.$word;
            if ($line !== '' && $pdf->width($try, $size) > $maxWidth) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $try;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }
        return $lines !== [] ? $lines : [''];
    }
}
