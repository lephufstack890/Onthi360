<?php

namespace App\Support\Pdf;

/**
 * SỬA 4/11 (khách: "in bảng điểm xoay tùm lum — muốn PDF ngang") — bộ tạo PDF tối giản, KHÔNG cần cài thêm thư viện: trang A4
 * ngang/dọc, chữ tiếng Việt (nhúng phông TrueType Unicode — CIDFontType2 / Identity-H), kẻ khung, tô nền, ảnh JPEG.
 * PDF tạo ra có khổ trang ĐÚNG như chọn nên mở bằng trình xem nào cũng đứng thẳng, không phụ thuộc máy in / hộp in của trình duyệt.
 *
 * Toạ độ dùng mm, gốc ở GÓC TRÊN BÊN TRÁI (tự đổi sang hệ toạ độ PDF bên trong).
 */
final class SimplePdf
{
    private const K = 72 / 25.4; // pt / mm

    private float $w;          // mm
    private float $h;          // mm
    /** @var list<string> */
    private array $pages = [];
    private int $cur = -1;
    /** @var array<string, array{font: TtfFont, used: array<int, int>}> */
    private array $fonts = [];
    /** @var array<string, array{data: string, w: int, h: int, channels: int}> */
    private array $images = [];
    private ?string $fontKey = null;
    private float $fontSize = 11;

    public function __construct(bool $landscape = true)
    {
        [$this->w, $this->h] = $landscape ? [297.0, 210.0] : [210.0, 297.0];
    }

    public function width(): float { return $this->w; }

    public function height(): float { return $this->h; }

    public function pageCount(): int { return count($this->pages); }

    public function addPage(): void
    {
        $this->pages[] = '';
        $this->cur = count($this->pages) - 1;
    }

    /** Chuyển sang trang đã có (để vẽ chân trang sau khi biết tổng số trang). */
    public function setPage(int $index): void { $this->cur = $index; }

    public function addFont(string $key, string $ttfPath): void
    {
        $this->fonts[$key] = ['font' => new TtfFont($ttfPath), 'used' => []];
    }

    public function setFont(string $key, float $size): void
    {
        $this->fontKey = $key;
        $this->fontSize = $size;
    }

    /** Bề rộng chuỗi (mm) theo phông / cỡ chữ hiện tại. */
    public function textWidth(string $text, ?string $key = null, ?float $size = null): float
    {
        $f = $this->fonts[$key ?? $this->fontKey]['font'];

        return $f->stringWidth(self::nfc($text)) * ($size ?? $this->fontSize) / 1000 / self::K;
    }

    /** Viết chữ: $y là ĐƯỜNG CHÂN CHỮ (baseline) tính từ mép trên trang. */
    public function text(float $x, float $y, string $text, array $rgb = [0, 0, 0]): void
    {
        $text = self::nfc($text);
        if ($text === '') {
            return;
        }
        $entry = &$this->fonts[$this->fontKey];
        $hex = '';
        foreach (TtfFont::codepoints($text) as $cp) {
            $gid = $entry['font']->glyphId($cp);
            $entry['used'][$gid] = $cp;
            $hex .= sprintf('%04X', $gid);
        }
        $idx = array_search($this->fontKey, array_keys($this->fonts), true) + 1;
        $this->out(sprintf('BT %s rg /F%d %.2F Tf %.2F %.2F Td <%s> Tj ET',
            $this->rgb($rgb), $idx, $this->fontSize, $x * self::K, ($this->h - $y) * self::K, $hex));
    }

    public function fillRect(float $x, float $y, float $w, float $h, array $rgb): void
    {
        $this->out(sprintf('%s rg %.2F %.2F %.2F %.2F re f', $this->rgb($rgb), $x * self::K, ($this->h - $y - $h) * self::K, $w * self::K, $h * self::K));
    }

    public function line(float $x1, float $y1, float $x2, float $y2, float $widthPt = 0.5, array $rgb = [0, 0, 0]): void
    {
        $this->out(sprintf('%.2F w %s RG %.2F %.2F m %.2F %.2F l S', $widthPt, $this->rgb($rgb),
            $x1 * self::K, ($this->h - $y1) * self::K, $x2 * self::K, ($this->h - $y2) * self::K));
    }

    public function rect(float $x, float $y, float $w, float $h, float $widthPt = 0.5, array $rgb = [0, 0, 0]): void
    {
        $this->out(sprintf('%.2F w %s RG %.2F %.2F %.2F %.2F re S', $widthPt, $this->rgb($rgb),
            $x * self::K, ($this->h - $y - $h) * self::K, $w * self::K, $h * self::K));
    }

    /** Ảnh JPEG (khung $w x $h mm, góc trên trái tại $x,$y). */
    public function jpeg(string $path, float $x, float $y, float $w, float $h): void
    {
        if (! isset($this->images[$path])) {
            $info = @getimagesize($path);
            if (! $info || ($info[2] ?? 0) !== IMAGETYPE_JPEG) {
                return;
            }
            $this->images[$path] = ['data' => (string) file_get_contents($path), 'w' => $info[0], 'h' => $info[1], 'channels' => (int) ($info['channels'] ?? 3)];
        }
        $idx = array_search($path, array_keys($this->images), true) + 1;
        $this->out(sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /Im%d Do Q', $w * self::K, $h * self::K, $x * self::K, ($this->h - $y - $h) * self::K, $idx));
    }

    /** Tỉ lệ cao / rộng của ảnh JPEG (để giữ đúng hình). */
    public static function jpegRatio(string $path): ?float
    {
        $info = @getimagesize($path);

        return $info && $info[0] > 0 ? $info[1] / $info[0] : null;
    }

    /** Ngắt dòng theo bề rộng (mm) — tách theo khoảng trắng, từ quá dài thì cắt theo ký tự. @return list<string> */
    public function wrap(string $text, float $width, ?string $key = null, ?float $size = null): array
    {
        $lines = [];
        foreach (preg_split('/\R/u', self::nfc($text)) as $para) {
            $words = preg_split('/ +/u', trim($para));
            $line = '';
            foreach ($words as $word) {
                if ($word === '') {
                    continue;
                }
                $try = $line === '' ? $word : $line.' '.$word;
                if ($this->textWidth($try, $key, $size) <= $width) {
                    $line = $try;
                    continue;
                }
                if ($line !== '') {
                    $lines[] = $line;
                }
                // Từ dài hơn cả dòng: cắt theo ký tự.
                while ($this->textWidth($word, $key, $size) > $width && mb_strlen($word) > 1) {
                    $cut = mb_strlen($word);
                    while ($cut > 1 && $this->textWidth(mb_substr($word, 0, $cut), $key, $size) > $width) {
                        $cut--;
                    }
                    $lines[] = mb_substr($word, 0, $cut);
                    $word = mb_substr($word, $cut);
                }
                $line = $word;
            }
            $lines[] = $line;
        }

        return $lines;
    }

    public function output(): string
    {
        $objs = [];      // số đối tượng => nội dung
        $n = 2;          // 1 = Catalog, 2 = Pages
        $fontRefs = [];
        foreach ($this->fonts as $key => $entry) {
            $fontRefs[$key] = $this->buildFont($entry['font'], $entry['used'], $objs, $n);
        }
        $imgRefs = [];
        foreach ($this->images as $path => $img) {
            $id = ++$n;
            $cs = $img['channels'] === 1 ? '/DeviceGray' : ($img['channels'] === 4 ? '/DeviceCMYK' : '/DeviceRGB');
            $objs[$id] = "<< /Type /XObject /Subtype /Image /Width {$img['w']} /Height {$img['h']} /ColorSpace {$cs} /BitsPerComponent 8 /Filter /DCTDecode /Length ".strlen($img['data'])." >>\nstream\n".$img['data']."\nendstream";
            $imgRefs[] = $id;
        }
        $fontDict = '';
        $i = 1;
        foreach ($fontRefs as $ref) {
            $fontDict .= '/F'.($i++)." {$ref} 0 R ";
        }
        $imgDict = '';
        foreach ($imgRefs as $k => $ref) {
            $imgDict .= '/Im'.($k + 1)." {$ref} 0 R ";
        }
        $resId = ++$n;
        $objs[$resId] = "<< /ProcSet [/PDF /Text /ImageB /ImageC] /Font << {$fontDict}>> /XObject << {$imgDict}>> >>";

        $kids = [];
        foreach ($this->pages as $content) {
            $cId = ++$n;
            $z = gzcompress($content);
            $objs[$cId] = '<< /Length '.strlen($z)." /Filter /FlateDecode >>\nstream\n".$z."\nendstream";
            $pId = ++$n;
            $objs[$pId] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources %d 0 R /Contents %d 0 R >>', $this->w * self::K, $this->h * self::K, $resId, $cId);
            $kids[] = "{$pId} 0 R";
        }
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objs[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>';
        ksort($objs);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objs as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }
        $xref = strlen($pdf);
        $max = max(array_keys($objs));
        $pdf .= "xref\n0 ".($max + 1)."\n0000000000 65535 f \n";
        for ($id = 1; $id <= $max; $id++) {
            $pdf .= isset($offsets[$id]) ? sprintf("%010d 00000 n \n", $offsets[$id]) : "0000000000 65535 f \n";
        }
        $pdf .= "trailer\n<< /Size ".($max + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    /** Dựng 5 đối tượng của 1 phông Unicode; trả về số đối tượng Type0. */
    private function buildFont(TtfFont $f, array $used, array &$objs, int &$n): int
    {
        $name = $f->postscriptName();
        $raw = $f->data();
        $z = gzcompress($raw);
        $fileId = ++$n;
        $objs[$fileId] = '<< /Length '.strlen($z).' /Length1 '.strlen($raw)." /Filter /FlateDecode >>\nstream\n".$z."\nendstream";

        $s = 1000 / $f->unitsPerEm();
        [$x0, $y0, $x1, $y1] = $f->bbox();
        $flags = 32 | ($f->isItalic() ? 64 : 0);
        $descId = ++$n;
        $objs[$descId] = sprintf('<< /Type /FontDescriptor /FontName /%s /Flags %d /FontBBox [%d %d %d %d] /ItalicAngle %d /Ascent %d /Descent %d /CapHeight %d /StemV 80 /FontFile2 %d 0 R >>',
            $name, $flags, $x0 * $s, $y0 * $s, $x1 * $s, $y1 * $s, $f->isItalic() ? -12 : 0, $f->ascent() * $s, $f->descent() * $s, $f->capHeight() * $s, $fileId);

        ksort($used);
        $w = '';
        foreach ($used as $gid => $cp) {
            $w .= $gid.' ['.round($f->glyphAdvance($gid) * $s).'] ';
        }
        $cidId = ++$n;
        $objs[$cidId] = sprintf('<< /Type /Font /Subtype /CIDFontType2 /BaseFont /%s /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /FontDescriptor %d 0 R /CIDToGIDMap /Identity /DW 1000 /W [%s] >>',
            $name, $descId, $w);

        $map = '';
        $chunks = array_chunk($used, 100, true);
        foreach ($chunks as $chunk) {
            $map .= count($chunk)." beginbfchar\n";
            foreach ($chunk as $gid => $cp) {
                $u = mb_convert_encoding(mb_chr($cp, 'UTF-8'), 'UTF-16BE', 'UTF-8');
                $map .= sprintf("<%04X> <%s>\n", $gid, strtoupper(bin2hex($u)));
            }
            $map .= "endbfchar\n";
        }
        $cmap = "/CIDInit /ProcSet findresource begin\n12 dict begin\nbegincmap\n/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def\n/CMapName /Adobe-Identity-UCS def\n/CMapType 2 def\n1 begincodespacerange\n<0000> <FFFF>\nendcodespacerange\n{$map}endcmap\nCMapName currentdict /CMap defineresource pop\nend\nend";
        $uniId = ++$n;
        $objs[$uniId] = '<< /Length '.strlen($cmap)." >>\nstream\n".$cmap."\nendstream";

        $type0 = ++$n;
        $objs[$type0] = sprintf('<< /Type /Font /Subtype /Type0 /BaseFont /%s /Encoding /Identity-H /DescendantFonts [%d 0 R] /ToUnicode %d 0 R >>', $name, $cidId, $uniId);

        return $type0;
    }

    private function out(string $s): void
    {
        if ($this->cur < 0) {
            $this->addPage();
        }
        $this->pages[$this->cur] .= $s."\n";
    }

    private function rgb(array $c): string
    {
        return sprintf('%.3F %.3F %.3F', $c[0] / 255, $c[1] / 255, $c[2] / 255);
    }

    /** Tiếng Việt gõ trên máy Mac hay ở dạng tách dấu (NFD) — gộp lại (NFC) để có đúng 1 ký tự / 1 hình chữ. */
    private static function nfc(string $s): string
    {
        return class_exists(\Normalizer::class) ? (\Normalizer::normalize($s, \Normalizer::FORM_C) ?: $s) : $s;
    }
}
