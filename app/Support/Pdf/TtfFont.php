<?php

namespace App\Support\Pdf;

/**
 * SỬA 4/11 — đọc phông TrueType (.ttf) đủ để SimplePdf nhúng và đo chữ: bảng cmap (Unicode → số hình chữ), độ rộng từng hình
 * chữ (hmtx), thông số chung (head / hhea / OS/2 / post / name). Không chỉnh sửa phông, nhúng nguyên tệp.
 */
final class TtfFont
{
    private string $data;
    /** @var array<string, array{0:int, 1:int}> tag => [offset, length] */
    private array $tables = [];
    private int $unitsPerEm = 1000;
    /** @var array{0:int,1:int,2:int,3:int} */
    private array $bbox = [0, 0, 0, 0];
    private int $ascent = 0;
    private int $descent = 0;
    private int $capHeight = 0;
    private bool $italic = false;
    private string $psName = 'Font';
    /** @var list<int> */
    private array $advances = [];
    /** @var array<int, int> codepoint => glyph id */
    private array $cmap = [];

    public function __construct(string $path)
    {
        $data = @file_get_contents($path);
        if ($data === false || strlen($data) < 12) {
            throw new \RuntimeException('Không đọc được phông chữ: '.$path);
        }
        $this->data = $data;
        $num = $this->u16(4);
        for ($i = 0; $i < $num; $i++) {
            $p = 12 + $i * 16;
            $this->tables[substr($data, $p, 4)] = [$this->u32($p + 8), $this->u32($p + 12)];
        }

        $head = $this->tables['head'][0];
        $this->unitsPerEm = $this->u16($head + 18);
        $this->bbox = [$this->i16($head + 36), $this->i16($head + 38), $this->i16($head + 40), $this->i16($head + 42)];
        $this->italic = (bool) ($this->u16($head + 44) & 2);

        $hhea = $this->tables['hhea'][0];
        $this->ascent = $this->i16($hhea + 4);
        $this->descent = $this->i16($hhea + 6);
        $numH = $this->u16($hhea + 34);

        $this->capHeight = $this->ascent;
        if (isset($this->tables['OS/2'])) {
            [$os2, $len] = $this->tables['OS/2'];
            if ($this->u16($os2) >= 2 && $len >= 90) {
                $this->capHeight = $this->i16($os2 + 88);
            }
        }

        $numGlyphs = $this->u16($this->tables['maxp'][0] + 4);
        $hmtx = $this->tables['hmtx'][0];
        $last = 0;
        for ($g = 0; $g < $numGlyphs; $g++) {
            if ($g < $numH) {
                $last = $this->u16($hmtx + $g * 4);
            }
            $this->advances[$g] = $last;
        }

        $this->readCmap();
        $this->readName();
    }

    public function data(): string { return $this->data; }

    public function unitsPerEm(): int { return $this->unitsPerEm; }

    /** @return array{0:int,1:int,2:int,3:int} */
    public function bbox(): array { return $this->bbox; }

    public function ascent(): int { return $this->ascent; }

    public function descent(): int { return $this->descent; }

    public function capHeight(): int { return $this->capHeight; }

    public function isItalic(): bool { return $this->italic; }

    public function postscriptName(): string { return $this->psName; }

    public function glyphId(int $cp): int { return $this->cmap[$cp] ?? 0; }

    public function glyphAdvance(int $gid): int { return $this->advances[$gid] ?? 0; }

    /** Bề rộng chuỗi theo đơn vị 1/1000 cỡ chữ. */
    public function stringWidth(string $s): float
    {
        $w = 0;
        foreach (self::codepoints($s) as $cp) {
            $w += $this->advances[$this->glyphId($cp)] ?? 0;
        }

        return $w * 1000 / $this->unitsPerEm;
    }

    /** @return list<int> */
    public static function codepoints(string $s): array
    {
        if ($s === '') {
            return [];
        }
        $u = mb_convert_encoding($s, 'UTF-32BE', 'UTF-8');

        return array_values(unpack('N*', $u));
    }

    private function readCmap(): void
    {
        $base = $this->tables['cmap'][0];
        $n = $this->u16($base + 2);
        $best = null;
        $bestScore = -1;
        for ($i = 0; $i < $n; $i++) {
            $p = $base + 4 + $i * 8;
            $pid = $this->u16($p);
            $eid = $this->u16($p + 2);
            $off = $base + $this->u32($p + 4);
            $fmt = $this->u16($off);
            $score = match (true) {
                $pid === 3 && $eid === 10 && $fmt === 12 => 3,
                $pid === 0 && $fmt === 12 => 2,
                $pid === 3 && $eid === 1 && $fmt === 4 => 1,
                $pid === 0 && $fmt === 4 => 0,
                default => -1,
            };
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $off;
            }
        }
        if ($best === null) {
            return;
        }
        $fmt = $this->u16($best);
        if ($fmt === 12) {
            $groups = $this->u32($best + 12);
            for ($i = 0; $i < $groups; $i++) {
                $p = $best + 16 + $i * 12;
                $start = $this->u32($p);
                $end = $this->u32($p + 4);
                $gid = $this->u32($p + 8);
                if ($end - $start > 0x10000) {
                    continue;
                }
                for ($c = $start; $c <= $end; $c++) {
                    $this->cmap[$c] = $gid + ($c - $start);
                }
            }

            return;
        }
        // format 4
        $segX2 = $this->u16($best + 6);
        $seg = $segX2 / 2;
        $endP = $best + 14;
        $startP = $endP + $segX2 + 2;
        $deltaP = $startP + $segX2;
        $rangeP = $deltaP + $segX2;
        for ($i = 0; $i < $seg; $i++) {
            $end = $this->u16($endP + $i * 2);
            $start = $this->u16($startP + $i * 2);
            $delta = $this->i16($deltaP + $i * 2);
            $ro = $this->u16($rangeP + $i * 2);
            for ($c = $start; $c <= $end && $c !== 0xFFFF; $c++) {
                if ($ro === 0) {
                    $g = ($c + $delta) & 0xFFFF;
                } else {
                    $gp = $rangeP + $i * 2 + $ro + ($c - $start) * 2;
                    $g = $this->u16($gp);
                    if ($g !== 0) {
                        $g = ($g + $delta) & 0xFFFF;
                    }
                }
                if ($g !== 0) {
                    $this->cmap[$c] = $g;
                }
            }
        }
    }

    private function readName(): void
    {
        if (! isset($this->tables['name'])) {
            return;
        }
        $base = $this->tables['name'][0];
        $count = $this->u16($base + 2);
        $strings = $base + $this->u16($base + 4);
        for ($i = 0; $i < $count; $i++) {
            $p = $base + 6 + $i * 12;
            if ($this->u16($p + 6) !== 6) { // nameID 6 = PostScript name
                continue;
            }
            $pid = $this->u16($p);
            $len = $this->u16($p + 8);
            $raw = substr($this->data, $strings + $this->u16($p + 10), $len);
            $name = $pid === 3 || $pid === 0 ? mb_convert_encoding($raw, 'UTF-8', 'UTF-16BE') : $raw;
            $name = preg_replace('/[^A-Za-z0-9\-]/', '', $name);
            if ($name !== '') {
                $this->psName = $name;

                return;
            }
        }
    }

    private function u16(int $p): int { return unpack('n', $this->data, $p)[1]; }

    private function i16(int $p): int { $v = $this->u16($p); return $v >= 0x8000 ? $v - 0x10000 : $v; }

    private function u32(int $p): int { return unpack('N', $this->data, $p)[1]; }
}
