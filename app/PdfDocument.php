<?php
declare(strict_types=1);

final class PdfDocument {
    public const PAGE_W = 595.0;
    public const PAGE_H = 842.0;

    /** @var list<string> */
    private array $pages = [];
    private string $buf = '';
    public float $y = 50.0;
    public float $margin = 36.0;

    private const HELV = [
        32=>278,33=>278,34=>355,35=>556,36=>556,37=>889,38=>667,39=>191,40=>333,41=>333,42=>389,43=>584,44=>278,45=>333,46=>278,47=>278,
        48=>556,49=>556,50=>556,51=>556,52=>556,53=>556,54=>556,55=>556,56=>556,57=>556,58=>278,59=>278,60=>584,61=>584,62=>584,63=>556,
        64=>1015,65=>667,66=>667,67=>722,68=>722,69=>667,70=>611,71=>778,72=>722,73=>278,74=>500,75=>667,76=>556,77=>833,78=>722,79=>778,
        80=>667,81=>778,82=>722,83=>667,84=>611,85=>722,86=>667,87=>944,88=>667,89=>667,90=>611,91=>278,92=>278,93=>278,94=>469,95=>556,
        96=>333,97=>556,98=>556,99=>500,100=>556,101=>556,102=>278,103=>556,104=>556,105=>222,106=>222,107=>500,108=>222,109=>833,110=>556,111=>556,
        112=>556,113=>556,114=>333,115=>500,116=>278,117=>556,118=>500,119=>722,120=>500,121=>500,122=>500,123=>334,124=>260,125=>334,126=>584,
    ];

    public function __construct() {
        $this->addPage();
    }

    public function addPage(): void {
        if ($this->buf !== '') {
            $this->pages[] = $this->buf;
        }
        $this->buf = "0.12 0.16 0.22 rg\n";
        $this->y = $this->margin;
    }

    public function bottom(): float {
        return self::PAGE_H - $this->margin - 22;
    }

    public function need(float $h): void {
        if ($this->y + $h > $this->bottom()) {
            $this->addPage();
        }
    }

    public function fillRect(float $x, float $yTop, float $w, float $h, float $r, float $g, float $b): void {
        $y = self::PAGE_H - $yTop - $h;
        $this->buf .= sprintf("%.3f %.3f %.3f rg\n%.2f %.2f %.2f %.2f re f\n", $r, $g, $b, $x, $y, $w, $h);
    }

    public function line(float $x1, float $yTop, float $x2, float $r = 0.85, float $g = 0.87, float $b = 0.9): void {
        $y = self::PAGE_H - $yTop;
        $this->buf .= sprintf("%.3f %.3f %.3f RG 0.4 w\n%.2f %.2f m %.2f %.2f l S\n", $r, $g, $b, $x1, $y, $x2, $y);
    }

    public function text(float $x, float $yTop, string $s, float $size = 10, bool $bold = false, array $rgb = [0.12, 0.16, 0.22]): void {
        $y = self::PAGE_H - $yTop;
        $font = $bold ? '/F2' : '/F1';
        $this->buf .= sprintf("%.3f %.3f %.3f rg\nBT %s %.1f Tf 1 0 0 1 %.2f %.2f Tm (%s) Tj ET\n",
            $rgb[0], $rgb[1], $rgb[2], $font, $size, $x, $y, self::esc(self::winAnsi($s)));
    }

    public function textRight(float $xRight, float $yTop, string $s, float $size = 10, bool $bold = false, array $rgb = [0.12, 0.16, 0.22]): void {
        $w = $this->width($s, $size);
        $this->text($xRight - $w, $yTop, $s, $size, $bold, $rgb);
    }

    public function fit(string $s, float $maxWidth, float $size): string {
        if ($this->width($s, $size) <= $maxWidth) return $s;
        $out = '';
        $chars = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: str_split($s);
        foreach ($chars as $ch) {
            $try = $out.$ch;
            if ($this->width($try.'...', $size) > $maxWidth) break;
            $out = $try;
        }
        return $out.'...';
    }

    public function width(string $s, float $size): float {
        $s = self::winAnsi($s);
        $w = 0;
        $n = strlen($s);
        for ($i = 0; $i < $n; $i++) {
            $c = ord($s[$i]);
            $w += self::HELV[$c] ?? 500;
        }
        return $w * $size / 1000.0;
    }

    public function download(string $filename): never {
        $bin = $this->bytes();
        $name = str_replace(['"', "\r", "\n", '/', '\\'], '', $filename);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="'.$name.'"');
        header('Content-Length: '.(string)strlen($bin));
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        echo $bin;
        exit;
    }

    public function bytes(): string {
        $pages = $this->pages;
        if ($this->buf !== '') $pages[] = $this->buf;
        if (!$pages) $pages[] = "BT /F1 10 Tf 50 800 Td (Empty) Tj ET\n";
        $n = count($pages);
        $objs = [];
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $pageIds = [];
        $id = 5;
        foreach ($pages as $content) {
            $pageIds[] = $id;
            $id += 2;
        }
        $kids = implode(' ', array_map(static fn(int $p) => $p.' 0 R', $pageIds));
        $objs[2] = '<< /Type /Pages /Kids ['.$kids.'] /Count '.$n.' >>';
        $i = 0;
        foreach ($pages as $content) {
            $pageId = $pageIds[$i];
            $contentId = $pageId + 1;
            $objs[$pageId] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.0f %.0f] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',
                self::PAGE_W, self::PAGE_H, $contentId
            );
            $objs[$contentId] = '<< /Length '.strlen($content)." >>\nstream\n".$content.'endstream';
            $i++;
        }
        ksort($objs);
        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offs = [0];
        $max = (int)max(array_keys($objs));
        for ($i = 1; $i <= $max; $i++) {
            $offs[$i] = strlen($out);
            $out .= $i." 0 obj\n".($objs[$i] ?? '<< >>')."\nendobj\n";
        }
        $xref = strlen($out);
        $out .= 'xref'."\n0 ".($max + 1)."\n";
        $out .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $max; $i++) {
            $out .= sprintf("%010d 00000 n \n", $offs[$i]);
        }
        $out .= 'trailer << /Size '.($max + 1).' /Root 1 0 R >>'."\nstartxref\n".$xref."\n%%EOF";
        return $out;
    }

    private static function winAnsi(string $s): string {
        $s = str_replace(['₹', '—', '–', '’', '‘', '“', '”'], ['Rs ', '-', '-', "'", "'", '"', '"'], $s);
        $out = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s);
        if ($out === false) {
            $out = preg_replace('/[^\x20-\x7E]/', '?', $s) ?? $s;
        }
        return $out;
    }

    private static function esc(string $s): string {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
    }
}
