<?php
declare(strict_types=1);

/** Small one-page PDF used by the payment success invoice. */
final class PdfDocument {
    public const PAGE_W = 595.0;
    public const PAGE_H = 842.0;

    public float $margin = 40.0;
    public float $y = 40.0;

    private string $ops = '';

    public function fillRect(float $x, float $y, float $w, float $h, float $r, float $g, float $b): void {
        $py = self::PAGE_H - $y - $h;
        $this->ops .= sprintf("%.3F %.3F %.3F rg\n%.2F %.2F %.2F %.2F re f\n", $r, $g, $b, $x, $py, $w, $h);
    }

    /** @param array{0:float,1:float,2:float} $rgb */
    public function text(float $x, float $y, string $text, float $size, bool $bold = false, array $rgb = [0, 0, 0]): void {
        $this->draw($x, $y, $text, $size, $bold, $rgb);
    }

    /** @param array{0:float,1:float,2:float} $rgb */
    public function textRight(float $x, float $y, string $text, float $size, bool $bold = false, array $rgb = [0, 0, 0]): void {
        $this->draw($x - $this->width($text, $size), $y, $text, $size, $bold, $rgb);
    }

    public function line(float $x1, float $y, float $x2): void {
        $py = self::PAGE_H - $y;
        $this->ops .= sprintf("0.75 0.76 0.78 RG\n0.6 w\n%.2F %.2F m %.2F %.2F l S\n", $x1, $py, $x2, $py);
    }

    public function width(string $text, float $size): float {
        $text = $this->latin1($text);
        $units = 0;
        $len = strlen($text);
        for ($i = 0; $i < $len; $i++) {
            $units += self::CHAR_W[ord($text[$i])] ?? 500;
        }
        return $units * $size / 1000;
    }

    public function fit(string $text, float $maxWidth, float $size): string {
        if ($this->width($text, $size) <= $maxWidth) return $text;
        $end = '...';
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        while ($chars !== [] && $this->width(implode('', $chars).$end, $size) > $maxWidth) {
            array_pop($chars);
        }
        return implode('', $chars).$end;
    }

    public function download(string $filename): never {
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '', $filename) ?: 'invoice.pdf';
        $bytes = $this->bytes();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="'.$safe.'"');
        header('Content-Length: '.(string)strlen($bytes));
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        echo $bytes;
        exit;
    }

    public function bytes(): string {
        $stream = $this->ops;
        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ".self::PAGE_W.' '.self::PAGE_H."] /Contents 4 0 R /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> >>\nendobj\n",
            "4 0 obj\n<< /Length ".strlen($stream)." >>\nstream\n".$stream."endstream\nendobj\n",
            "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
            "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>\nendobj\n",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";
        return $pdf;
    }

    /** @param array{0:float,1:float,2:float} $rgb */
    private function draw(float $x, float $y, string $text, float $size, bool $bold, array $rgb): void {
        $font = $bold ? '/F2' : '/F1';
        $py = self::PAGE_H - $y;
        $this->ops .= sprintf(
            "BT %s %.2F Tf %.3F %.3F %.3F rg 1 0 0 1 %.2F %.2F Tm %s Tj ET\n",
            $font,
            $size,
            $rgb[0],
            $rgb[1],
            $rgb[2],
            $x,
            $py,
            $this->pdfString($text)
        );
    }

    private function pdfString(string $text): string {
        $text = $this->latin1($text);
        return '(' . str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text) . ')';
    }

    private function latin1(string $text): string {
        $out = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        return $out === false ? '' : $out;
    }

    /** Helvetica character widths in 1000-unit em. */
    private const CHAR_W = [
        32 => 278, 33 => 278, 34 => 355, 35 => 556, 36 => 556, 37 => 889, 38 => 667, 39 => 191,
        40 => 333, 41 => 333, 42 => 389, 43 => 584, 44 => 278, 45 => 333, 46 => 278, 47 => 278,
        48 => 556, 49 => 556, 50 => 556, 51 => 556, 52 => 556, 53 => 556, 54 => 556, 55 => 556, 56 => 556, 57 => 556,
        58 => 278, 59 => 278, 60 => 584, 61 => 584, 62 => 584, 63 => 556, 64 => 1015,
        65 => 667, 66 => 667, 67 => 722, 68 => 722, 69 => 667, 70 => 611, 71 => 778, 72 => 722, 73 => 278, 74 => 500,
        75 => 667, 76 => 556, 77 => 833, 78 => 722, 79 => 778, 80 => 667, 81 => 778, 82 => 722, 83 => 667, 84 => 611,
        85 => 722, 86 => 667, 87 => 944, 88 => 667, 89 => 667, 90 => 611,
        91 => 278, 92 => 278, 93 => 278, 94 => 469, 95 => 556, 96 => 333,
        97 => 556, 98 => 556, 99 => 500, 100 => 556, 101 => 556, 102 => 278, 103 => 556, 104 => 556, 105 => 222, 106 => 222,
        107 => 500, 108 => 222, 109 => 833, 110 => 556, 111 => 556, 112 => 556, 113 => 556, 114 => 333, 115 => 500, 116 => 278,
        117 => 556, 118 => 500, 119 => 722, 120 => 500, 121 => 500, 122 => 500,
        123 => 334, 124 => 260, 125 => 334, 126 => 584,
    ];
}
