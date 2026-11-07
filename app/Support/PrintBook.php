<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 7/11 — "Bản in cả cuốn" của tài liệu: 1 tệp PDF cả quyển sách, CHỈ lưu nội bộ để khi có khách đặt bản in thì in ra rồi ship.
 * Lưu ở ổ "local" (storage/app/private — không có đường dẫn công khai), chỉ khu quản trị xem trước / in / tải được
 * (Admin\ProductPrintBookController). Không liên quan tới phần đọc tài liệu của học sinh.
 */
final class PrintBook
{
    public const DISK = 'local';

    /** Trần cỡ tệp (MB) — vẫn bị giới hạn thêm bởi upload_max_filesize / post_max_size của PHP (xem UploadLimit). */
    public const MAX_MB = 500;

    /** Đã chạy migration 001440 chưa (chưa thì khối "Bản in cả cuốn" hiện lời nhắc chạy migrate). */
    public static function ready(): bool
    {
        static $ok = null;

        return $ok ??= Schema::hasTable('products') && Schema::hasColumn('products', 'print_book_path');
    }

    public static function maxKilobytes(): int
    {
        return UploadLimit::maxKilobytes(self::MAX_MB * 1024);
    }

    public static function maxLabel(): string
    {
        return UploadLimit::label(self::MAX_MB * 1024);
    }

    public static function has(Product $product): bool
    {
        return self::ready() && filled($product->print_book_path);
    }

    /** "12,4 MB" */
    public static function sizeLabel(?int $bytes): string
    {
        if (! $bytes) {
            return '—';
        }

        return $bytes >= 1048576
            ? rtrim(rtrim(number_format($bytes / 1048576, 1, ',', ''), '0'), ',').' MB'
            : max(1, (int) round($bytes / 1024)).' KB';
    }
}
