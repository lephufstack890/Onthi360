<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageOptimizer
{
    /** Chiều rộng tối đa giữ lại. Chỗ hiển thị rộng nhất của trang là ~800px, x2 cho màn Retina. */
    public const MAX_WIDTH = 1600;

    /** Ảnh vuông nhỏ (avatar, huy hiệu) không cần to như ảnh bìa. */
    public const MAX_WIDTH_AVATAR = 512;

    public const JPEG_QUALITY = 78;

    /** Số điểm ảnh tối đa lấy mẫu khi dò nền trong suốt — để không quét cả chục triệu điểm. */
    private const ALPHA_SAMPLES = 120000;

    /**
     * Thay cho $file->store($dir, $disk): nén trước rồi mới cất.
     *
     * Trả về đường dẫn tương đối trên đĩa (giống hệt kiểu cũ) để phần gọi không phải đổi gì.
     */
    public static function store(
        UploadedFile $file,
        string $dir,
        string $disk = 'public',
        int $maxWidth = self::MAX_WIDTH
    ): string {
        $encoded = self::encode($file->getRealPath(), $maxWidth);

        if ($encoded === null) {
            return $file->store($dir, $disk);
        }

        [$bytes, $ext] = $encoded;
        $path = trim($dir, '/').'/'.Str::random(40).'.'.$ext;
        Storage::disk($disk)->put($path, $bytes);

        return $path;
    }

    /**
     * Nén một tệp ĐÃ nằm trên đĩa, ghi đè chính nó và GIỮ NGUYÊN phần mở rộng
     * (đường dẫn đã lưu trong cơ sở dữ liệu nên không được đổi tên).
     *
     * @return array{0:int,1:int}|null [số byte cũ, số byte mới] — null nghĩa là bỏ qua.
     */
    public static function shrinkInPlace(string $absolutePath, int $maxWidth = self::MAX_WIDTH): ?array
    {
        $before = @filesize($absolutePath);
        if ($before === false || $before <= 0) {
            return null;
        }

        $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        $encoded = self::encode($absolutePath, $maxWidth, keepExtension: $ext);

        if ($encoded === null) {
            return null;
        }

        [$bytes] = $encoded;
        if (strlen($bytes) >= $before) {
            return null;
        }

        return @file_put_contents($absolutePath, $bytes) === false
            ? null
            : [$before, strlen($bytes)];
    }

    /**
     * Đọc ảnh -> thu nhỏ -> mã hoá lại.
     *
     * @param  string|null  $keepExtension  ép giữ đúng định dạng này (dùng khi ghi đè tệp cũ).
     * @return array{0:string,1:string}|null  [nội dung tệp, phần mở rộng] — null nghĩa là bỏ qua.
     */
    private static function encode(string $sourcePath, int $maxWidth, ?string $keepExtension = null): ?array
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $raw = @file_get_contents($sourcePath);
        if ($raw === false || $raw === '') {
            return null;
        }

        $info = @getimagesizefromstring($raw);
        if ($info === false) {
            return null;
        }

        // Ảnh động thì để yên: thu nhỏ bằng GD chỉ lấy được khung đầu, mất hoạt cảnh.
        if (($info[2] ?? null) === IMAGETYPE_GIF) {
            return null;
        }

        $src = @imagecreatefromstring($raw);
        if ($src === false) {
            return null;
        }

        try {
            $width = imagesx($src);
            $height = imagesy($src);
            $hasAlpha = self::hasAlpha($src, $width, $height);

            $target = $keepExtension !== null
                ? (in_array($keepExtension, ['jpg', 'jpeg'], true) ? 'jpg' : $keepExtension)
                : ($hasAlpha ? 'png' : 'jpg');

            // Định dạng lạ (webp, avif…) mà phải giữ nguyên tên thì thôi, không đụng vào.
            if (! in_array($target, ['jpg', 'png'], true)) {
                return null;
            }

            $canvas = $width > $maxWidth
                ? self::resize($src, $width, $height, $maxWidth, $hasAlpha)
                : $src;

            ob_start();
            if ($target === 'jpg') {
                $flat = self::flatten($canvas);
                imageinterlace($flat, true);
                imagejpeg($flat, null, self::JPEG_QUALITY);
                if ($flat !== $canvas) {
                    imagedestroy($flat);
                }
            } else {
                imagealphablending($canvas, false);
                imagesavealpha($canvas, true);
                imagepng($canvas, null, 9);
            }
            $bytes = (string) ob_get_clean();

            if ($canvas !== $src) {
                imagedestroy($canvas);
            }

            if ($bytes === '') {
                return null;
            }

            // Nén xong không nhẹ hơn bản gốc: giữ bản gốc, khỏi làm ảnh xấu đi vô ích.
            if ($keepExtension === null && strlen($bytes) >= strlen($raw)) {
                return null;
            }

            return [$bytes, $target];
        } catch (\Throwable $e) {
            Log::warning('ImageOptimizer bỏ qua một ảnh', ['file' => $sourcePath, 'loi' => $e->getMessage()]);

            return null;
        } finally {
            imagedestroy($src);
        }
    }

    /** @param \GdImage $src */
    private static function resize($src, int $width, int $height, int $maxWidth, bool $hasAlpha)
    {
        $newWidth = $maxWidth;
        $newHeight = max(1, (int) round($height * $maxWidth / $width));

        $dst = imagecreatetruecolor($newWidth, $newHeight);

        if ($hasAlpha) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 255, 255, 255, 127));
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $dst;
    }

    /**
     * Nền trong suốt mà lưu thẳng JPEG thì chỗ trong suốt hoá ĐEN. Dán lên nền trắng trước.
     *
     * @param \GdImage $image
     */
    private static function flatten($image)
    {
        if (! self::hasAlpha($image, imagesx($image), imagesy($image))) {
            return $image;
        }

        $flat = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));

        return $flat;
    }

    /**
     * Có điểm nào trong suốt không. Quét thưa để ảnh to không làm treo máy chủ:
     * ảnh 1672x941 (1,5 triệu điểm) chỉ lấy mẫu khoảng 120 nghìn điểm là đủ kết luận.
     *
     * @param \GdImage $image
     */
    private static function hasAlpha($image, int $width, int $height): bool
    {
        if (! imageistruecolor($image)) {
            return imagecolortransparent($image) >= 0;
        }

        $step = max(1, (int) ceil(sqrt(($width * $height) / self::ALPHA_SAMPLES)));

        for ($y = 0; $y < $height; $y += $step) {
            for ($x = 0; $x < $width; $x += $step) {
                if (((imagecolorat($image, $x, $y) >> 24) & 0x7F) > 0) {
                    return true;
                }
            }
        }

        return false;
    }
}
