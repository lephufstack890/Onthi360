<?php

namespace App\Services\Contest;

use App\Support\Pdf\SimplePdf;

/**
 * SỬA 4/11 (khách: "in bảng điểm xoay tùm lum, muốn PDF ngang") — dựng FILE PDF "Bảng tổng hợp điểm và xếp giải" ngay trên máy chủ,
 * đúc theo mẫu in đang có (tiêu đề 3 dòng, dòng Số học sinh | Thang điểm + ngày, bảng Hạng · Họ tên · Bài 1…n · Tổng điểm · Giải ·
 * Kiểm tra thao tác, ghi chú, chân trang "… / Trang x / y"). Trang PDF có khổ ĐÚNG (A4 ngang hoặc dọc) nên mở / in ở đâu cũng không
 * bị xoay. Nội dung bảng (hạng, giải, ghi chú…) do hộp "In bảng điểm" tính sẵn rồi gửi lên — ở đây chỉ dàn trang, không tính lại.
 */
class ContestScoreSheetPdf
{
    private const GRID = [102, 102, 102];

    /** @param array<string, mixed> $m */
    public function render(array $m): string
    {
        $land = ($m['orient'] ?? 'landscape') !== 'portrait';
        $pdf = new SimplePdf($land);
        $fonts = resource_path('fonts');
        $pdf->addFont('R', $fonts.'/LiberationSerif-Regular.ttf');
        $pdf->addFont('B', $fonts.'/LiberationSerif-Bold.ttf');
        $pdf->addFont('I', $fonts.'/LiberationSerif-Italic.ttf');

        // Cỡ chữ (pt) & lề (mm) — khổ dọc hẹp hơn nên thu nhỏ chữ bảng.
        $sz = $land
            ? ['h1' => 19, 'h2' => 13, 'h3' => 14, 'meta' => 11.5, 'th' => 10.5, 'td' => 12, 'small' => 9.5, 'notes' => 10.5, 'foot' => 9, 'pad' => 1.6]
            : ['h1' => 16, 'h2' => 12, 'h3' => 12.5, 'meta' => 10.5, 'th' => 8.5, 'td' => 10.5, 'small' => 8.5, 'notes' => 9.5, 'foot' => 8.5, 'pad' => 1.2];
        $ml = $land ? 12 : 10;
        $mr = $ml;
        $mt = $land ? 13 : 12;
        $mb = 16;
        $cw = $pdf->width() - $ml - $mr;
        $bottom = $pdf->height() - $mb;
        $pt = 25.4 / 72; // mm / pt

        $pdf->addPage();
        $y = $mt;

        // ── Logo (góc trên trái) + tiêu đề căn giữa ──
        $logo = resource_path('pdf/onthi360-logo.jpg');
        if (! empty($m['logo']) && is_file($logo) && ($ratio = SimplePdf::jpegRatio($logo))) {
            $lh = $land ? 13 : 11;
            $pdf->jpeg($logo, $ml, $mt - 2, $lh / $ratio, $lh);
        }
        $center = function (string $text, string $font, float $size) use ($pdf, $ml, $cw, &$y, $pt) {
            foreach ($pdf->wrap($text, $cw * 0.7, $font, $size) as $line) {
                $y += $size * $pt;
                $pdf->setFont($font, $size);
                $pdf->text($ml + ($cw - $pdf->textWidth($line)) / 2, $y, $line);
                $y += $size * $pt * 0.25;
            }
        };
        $center((string) ($m['heading'] ?? ''), 'B', $sz['h1']);
        if (filled($m['subheading'] ?? null)) {
            $y += 1.2;
            $center((string) $m['subheading'], 'B', $sz['h2']);
        }
        if (filled($m['unit'] ?? null)) {
            $y += 1.2;
            $center((string) $m['unit'], 'B', $sz['h3']);
        }
        $y += 2.8;
        $pdf->line($ml + ($cw - 31) / 2, $y, $ml + ($cw + 31) / 2, $y, 1);
        $y += 6;

        // ── Dòng "Số học sinh… | Thang điểm" + ngày ──
        $y += $sz['meta'] * $pt;
        $pdf->setFont('R', $sz['meta']);
        $pdf->text($ml, $y, (string) ($m['metaLeft'] ?? ''));
        if (filled($m['dateLine'] ?? null)) {
            $pdf->setFont('I', $sz['meta']);
            $pdf->text($ml + $cw - $pdf->textWidth((string) $m['dateLine']), $y, (string) $m['dateLine']);
        }
        $y += 2.4;

        // ── Bảng ──
        $head = array_values((array) ($m['head'] ?? []));
        $kinds = array_values((array) ($m['kinds'] ?? []));
        $wSum = array_sum(array_map('floatval', (array) ($m['widths'] ?? []))) ?: 1;
        $cols = [];
        foreach ($head as $i => $label) {
            $cols[] = ['label' => (string) $label, 'kind' => (string) ($kinds[$i] ?? 'num'), 'w' => $cw * (float) ($m['widths'][$i] ?? 1) / $wSum];
        }
        $pad = $sz['pad'];
        $lineH = fn (float $size) => $size * $pt * 1.22;

        $drawHead = function () use ($pdf, $cols, $ml, &$y, $sz, $pad, $lineH) {
            $lines = [];
            $hMax = 0;
            foreach ($cols as $i => $c) {
                $lines[$i] = $pdf->wrap($c['label'], $c['w'] - 2 * $pad, 'B', $sz['th']);
                $hMax = max($hMax, count($lines[$i]) * $lineH($sz['th']) + 2 * $pad);
            }
            $x = $ml;
            foreach ($cols as $i => $c) {
                $pdf->fillRect($x, $y, $c['w'], $hMax, [239, 239, 239]);
                $pdf->rect($x, $y, $c['w'], $hMax, 0.6, self::GRID);
                $ty = $y + ($hMax - count($lines[$i]) * $lineH($sz['th'])) / 2;
                $pdf->setFont('B', $sz['th']);
                foreach ($lines[$i] as $ln) {
                    $ty += $lineH($sz['th']);
                    $pdf->text($x + ($c['w'] - $pdf->textWidth($ln)) / 2, $ty - $lineH($sz['th']) * 0.22, $ln);
                }
                $x += $c['w'];
            }
            $pdf->line($ml, $y + $hMax, $x, $y + $hMax, 1.2);
            $y += $hMax;
        };

        $tableTop = $y;
        $frame = function (float $top, float $bot) use ($pdf, $ml, $cw) {
            $pdf->rect($ml, $top, $cw, $bot - $top, 1);
        };
        $drawHead();

        foreach ((array) ($m['rows'] ?? []) as $row) {
            $cells = array_values((array) ($row['c'] ?? []));
            $reason = trim((string) ($row['r'] ?? ''));
            $blocks = [];
            $hMax = 0;
            foreach ($cols as $i => $c) {
                $text = (string) ($cells[$i] ?? '');
                $font = in_array($c['kind'], ['total', 'award'], true) ? 'B' : 'R';
                $size = $c['kind'] === 'activity' ? $sz['small'] : $sz['td'];
                $parts = [[$font, $size, $pdf->wrap($text, $c['w'] - 2 * $pad, $font, $size)]];
                if ($c['kind'] === 'award' && $reason !== '') {
                    $parts[] = ['I', $sz['small'], $pdf->wrap('('.$reason.')', $c['w'] - 2 * $pad, 'I', $sz['small'])];
                }
                $h = 2 * $pad;
                foreach ($parts as [$f, $s, $ls]) {
                    $h += count($ls) * $lineH($s);
                }
                $blocks[$i] = $parts;
                $hMax = max($hMax, $h);
            }
            $hMax = max($hMax, $sz['td'] * $pt * 1.22 + 2 * $pad + 2);

            if ($y + $hMax > $bottom) {
                $frame($tableTop, $y);
                $pdf->addPage();
                $y = $mt;
                $tableTop = $y;
                $drawHead();
            }

            $x = $ml;
            foreach ($cols as $i => $c) {
                if ($c['kind'] === 'total') {
                    $pdf->fillRect($x, $y, $c['w'], $hMax, [243, 243, 243]);
                }
                $pdf->rect($x, $y, $c['w'], $hMax, 0.6, self::GRID);
                $content = 0;
                foreach ($blocks[$i] as [$f, $s, $ls]) {
                    $content += count($ls) * $lineH($s);
                }
                $ty = $y + ($hMax - $content) / 2;
                foreach ($blocks[$i] as [$f, $s, $ls]) {
                    $pdf->setFont($f, $s);
                    foreach ($ls as $ln) {
                        $ty += $lineH($s);
                        $left = in_array($c['kind'], ['name', 'activity'], true);
                        $tx = $left ? $x + $pad + ($c['kind'] === 'name' ? 1.5 : 0) : $x + ($c['w'] - $pdf->textWidth($ln)) / 2;
                        $pdf->text($tx, $ty - $lineH($s) * 0.22, $ln);
                    }
                }
                $x += $c['w'];
            }
            $y += $hMax;
        }
        $frame($tableTop, $y);

        // ── Ghi chú ──
        $y += 3;
        $nSize = $sz['notes'];
        $nLh = $nSize * $pt * 1.5;
        foreach (array_values((array) ($m['notes'] ?? [])) as $k => $note) {
            $prefix = $k === 0 ? 'Ghi chú: ' : '';
            $prefixW = $prefix !== '' ? $pdf->textWidth($prefix, 'B', $nSize) : 0;
            // Dòng đầu chừa chỗ cho chữ "Ghi chú:" in đậm.
            $first = true;
            $rest = trim((string) preg_replace('/\s+/u', ' ', class_exists(\Normalizer::class) ? (\Normalizer::normalize((string) $note, \Normalizer::FORM_C) ?: (string) $note) : (string) $note));
            while ($rest !== '') {
                $avail = $cw - ($first ? $prefixW : 0);
                $lines = $pdf->wrap($rest, $avail, 'R', $nSize);
                $line = $lines[0] ?? '';
                if ($y + $nLh > $bottom) {
                    $pdf->addPage();
                    $y = $mt;
                }
                $y += $nLh;
                if ($first && $prefix !== '') {
                    $pdf->setFont('B', $nSize);
                    $pdf->text($ml, $y, $prefix);
                }
                $pdf->setFont('R', $nSize);
                $pdf->text($ml + ($first ? $prefixW : 0), $y, $line);
                $first = false;
                $rest = ltrim(mb_substr($rest, mb_strlen($line)));
                if ($line === '') {
                    break;
                }
            }
            $y += 0.6;
        }

        // ── Chân trang mọi trang: "<chân trang>" … "Trang x / y" ──
        $total = $pdf->pageCount();
        $footer = (string) ($m['footer'] ?? '');
        for ($p = 0; $p < $total; $p++) {
            $pdf->setPage($p);
            $fy = $pdf->height() - $mb + 3;
            $pdf->line($ml, $fy, $ml + $cw, $fy, 0.6, [153, 153, 153]);
            $pdf->setFont('I', $sz['foot']);
            $pdf->text($ml, $fy + 4.2, $footer, [51, 51, 51]);
            $label = 'Trang '.($p + 1).' / '.$total;
            $pdf->text($ml + $cw - $pdf->textWidth($label), $fy + 4.2, $label, [51, 51, 51]);
        }

        return $pdf->output();
    }
}
