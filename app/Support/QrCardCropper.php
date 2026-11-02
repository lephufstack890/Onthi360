<?php

namespace App\Support;

/**
 * SỬA 3/11 (khách: "ảnh QR còn nhỏ quá, bỏ khung xung quanh, chỉ lấy mỗi thẻ ở giữa — mã QR, VietQR/napas, tên + số tài
 * khoản") — tự cắt ảnh QR ngân hàng admin tải lên (ảnh chụp từ app ngân hàng: nền màu + logo ngân hàng + thẻ trắng ở giữa)
 * lấy đúng THẺ TRẮNG chứa mã QR. Không cắt được (ảnh lạ, máy chủ thiếu GD) thì trả null → dùng nguyên ảnh gốc.
 *
 * Cách làm (trên bản thu nhỏ để chạy nhanh):
 *  1. Tìm MÃ QR: chia ảnh thành ô nhỏ, đánh dấu ô có nhiều điểm tối; cụm ô tối liền nhau lớn nhất, gần vuông = mã QR.
 *  2. Từ mã QR nới dần 4 phía chừng nào hàng / cột còn chủ yếu là màu TRẮNG TRUNG TÍNH (thẻ) — nền xung quanh có màu
 *     (vàng, xanh…) nên dừng đúng mép thẻ. Phía dưới đi qua được logo VietQR / napas, tên và số tài khoản.
 *  3. Cắt ảnh GỐC (độ phân giải đầy đủ) theo khung đó, chừa 1 chút lề, xuất PNG.
 */
final class QrCardCropper
{
    public static function crop(string $bytes): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }
        $src = @imagecreatefromstring($bytes);
        if ($src === false) {
            return null;
        }
        try {
            $box = self::detect($src);
            if ($box === null) {
                return null;
            }
            [$x, $y, $w, $h] = $box;
            $out = imagecreatetruecolor($w, $h);
            imagealphablending($out, false);
            imagesavealpha($out, true);
            imagecopy($out, $src, 0, 0, $x, $y, $w, $h);
            ob_start();
            imagepng($out, null, 6);
            $png = (string) ob_get_clean();
            imagedestroy($out);

            return $png !== '' ? $png : null;
        } finally {
            imagedestroy($src);
        }
    }

    /** @return array{0:int,1:int,2:int,3:int}|null khung cắt [x, y, rộng, cao] trên ảnh gốc */
    public static function detect(\GdImage $src): ?array
    {
        $W = imagesx($src);
        $H = imagesy($src);
        if ($W < 60 || $H < 60) {
            return null;
        }
        $scale = min(1.0, 480 / max($W, $H));
        $w = max(1, (int) round($W * $scale));
        $h = max(1, (int) round($H * $scale));
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        imagecopyresampled($img, $src, 0, 0, 0, 0, $w, $h, $W, $H);

        // Phân loại điểm: 2 = tối (mô-đun QR, chữ), 1 = trắng trung tính (thẻ), 3 = NỀN (sáng nhưng có màu: vàng, xanh…),
        // 0 = khác (viền chữ, logo).
        $cls = [];
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorat($img, $x, $y);
                $r = ($c >> 16) & 255;
                $g = ($c >> 8) & 255;
                $b = $c & 255;
                $lum = 0.299 * $r + 0.587 * $g + 0.114 * $b;
                $mn = min($r, $g, $b);
                $mx = max($r, $g, $b);
                $cls[$y * $w + $x] = match (true) {
                    $lum < 110 => 2,
                    $mn >= 232 && $mx - $mn <= 18 => 1,
                    $mn >= 185 && $mx - $mn > 18 => 3,
                    default => 0,
                };
            }
        }
        imagedestroy($img);

        $qr = self::findQr($cls, $w, $h);
        if ($qr === null) {
            return null;
        }
        [$qx1, $qy1, $qx2, $qy2] = $qr;

        // Một hàng / cột còn "trong thẻ" khi điểm NỀN chiếm ít (chữ, logo trên thẻ không làm dừng sớm).
        $rowOk = function (int $y, int $x1, int $x2) use ($cls, $w): float {
            $n = 0;
            for ($x = $x1; $x <= $x2; $x++) {
                $n += $cls[$y * $w + $x] === 3 ? 0 : 1;
            }

            return $n / max(1, $x2 - $x1 + 1);
        };
        $colOk = function (int $x, int $y1, int $y2) use ($cls, $w): float {
            $n = 0;
            for ($y = $y1; $y <= $y2; $y++) {
                $n += $cls[$y * $w + $x] === 3 ? 0 : 1;
            }

            return $n / max(1, $y2 - $y1 + 1);
        };
        $need = 0.7;
        $x1 = $qx1;
        while ($x1 > 0 && $colOk($x1 - 1, $qy1, $qy2) >= $need) {
            $x1--;
        }
        $x2 = $qx2;
        while ($x2 < $w - 1 && $colOk($x2 + 1, $qy1, $qy2) >= $need) {
            $x2++;
        }
        $y1 = $qy1;
        while ($y1 > 0 && $rowOk($y1 - 1, $x1, $x2) >= $need) {
            $y1--;
        }
        $y2 = $qy2;
        while ($y2 < $h - 1 && $rowOk($y2 + 1, $x1, $x2) >= $need) {
            $y2++;
        }

        // Thẻ phải lớn hơn mã QR một chút; nếu không mở rộng được gì (ảnh lạ) thì chừa lề quanh mã QR.
        $qw = $qx2 - $qx1 + 1;
        $pad = (int) round($qw * 0.012);
        $x1 = max(0, $x1 - $pad);
        $y1 = max(0, $y1 - $pad);
        $x2 = min($w - 1, $x2 + $pad);
        $y2 = min($h - 1, $y2 + $pad);

        // Không cắt nếu khung gần bằng cả ảnh (ảnh đã chỉ có thẻ) — giữ nguyên ảnh gốc.
        if (($x2 - $x1 + 1) * ($y2 - $y1 + 1) > 0.9 * $w * $h) {
            return null;
        }

        $inv = 1 / $scale;
        $X = (int) floor($x1 * $inv);
        $Y = (int) floor($y1 * $inv);
        $X2 = (int) min($W, ceil(($x2 + 1) * $inv));
        $Y2 = (int) min($H, ceil(($y2 + 1) * $inv));

        return [$X, $Y, max(1, $X2 - $X), max(1, $Y2 - $Y)];
    }

    /**
     * Mã QR = cụm ô "tối dày" liền nhau lớn nhất, gần vuông.
     *
     * @param  array<int,int>  $cls
     * @return array{0:int,1:int,2:int,3:int}|null [x1, y1, x2, y2] trên bản thu nhỏ
     */
    private static function findQr(array $cls, int $w, int $h): ?array
    {
        $cs = max(3, (int) round(min($w, $h) / 60));
        $gw = intdiv($w, $cs);
        $gh = intdiv($h, $cs);
        $dense = [];
        for ($gy = 0; $gy < $gh; $gy++) {
            for ($gx = 0; $gx < $gw; $gx++) {
                $d = 0;
                for ($y = $gy * $cs; $y < ($gy + 1) * $cs; $y++) {
                    for ($x = $gx * $cs; $x < ($gx + 1) * $cs; $x++) {
                        $d += $cls[$y * $w + $x] === 2 ? 1 : 0;
                    }
                }
                $dense[$gy * $gw + $gx] = $d / ($cs * $cs) >= 0.18;
            }
        }

        $seen = [];
        $best = null;
        $bestN = 0;
        for ($i = 0; $i < $gw * $gh; $i++) {
            if (! $dense[$i] || isset($seen[$i])) {
                continue;
            }
            $stack = [$i];
            $seen[$i] = true;
            $n = 0;
            $bx1 = $gw;
            $by1 = $gh;
            $bx2 = 0;
            $by2 = 0;
            while ($stack !== []) {
                $k = array_pop($stack);
                $n++;
                $kx = $k % $gw;
                $ky = intdiv($k, $gw);
                $bx1 = min($bx1, $kx);
                $by1 = min($by1, $ky);
                $bx2 = max($bx2, $kx);
                $by2 = max($by2, $ky);
                // nối cả ô cách 1 ô (mô-đun trắng to bên trong mã QR không làm đứt cụm)
                for ($dy = -2; $dy <= 2; $dy++) {
                    for ($dx = -2; $dx <= 2; $dx++) {
                        $nx = $kx + $dx;
                        $ny = $ky + $dy;
                        if ($nx < 0 || $ny < 0 || $nx >= $gw || $ny >= $gh) {
                            continue;
                        }
                        $j = $ny * $gw + $nx;
                        if ($dense[$j] && ! isset($seen[$j])) {
                            $seen[$j] = true;
                            $stack[] = $j;
                        }
                    }
                }
            }
            $bw = $bx2 - $bx1 + 1;
            $bh = $by2 - $by1 + 1;
            $ratio = $bw / max(1, $bh);
            // gần vuông, đủ lớn, đặc (mã QR phủ kín khung của nó)
            if ($n > $bestN && $ratio >= 0.7 && $ratio <= 1.43 && $bw >= 8 && $n >= 0.4 * $bw * $bh) {
                $best = [$bx1, $by1, $bx2, $by2];
                $bestN = $n;
            }
        }
        if ($best === null) {
            return null;
        }

        return [$best[0] * $cs, $best[1] * $cs, min($w - 1, ($best[2] + 1) * $cs - 1), min($h - 1, ($best[3] + 1) * $cs - 1)];
    }
}
