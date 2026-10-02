<?php

namespace App\Support;

/**
 * SỬA 2/10 (khách: "preview pdf tải lên thất bại. là sao???") — GIỚI HẠN KÍCH THƯỚC TỆP THẬT
 * của máy chủ đang chạy.
 *
 * VÌ SAO CẦN: PHP chặn tệp quá cỡ NGAY Ở TẦNG WEB SERVER, trước khi Laravel nhìn thấy gì. Lúc
 * đó $_FILES mang mã lỗi UPLOAD_ERR_INI_SIZE, luật 'file' của Laravel trượt và trả về đúng một
 * câu: ":attribute tải lên thất bại." (lang/vi/validation.php) — không nói cỡ bao nhiêu thì
 * được, nên người dùng chỉ biết là hỏng chứ không biết vì sao.
 *
 * Trước đây form ghi cứng "tối đa 20MB" và luật validate cũng ghi max:20480, trong khi
 * upload_max_filesize của PHP thường chỉ 2M. Form hứa 20MB mà máy chủ cắt ở 2M → mọi tệp
 * 2–20MB đều hỏng với một câu khó hiểu. Lớp này lấy ĐÚNG con số máy chủ đang đặt để:
 *   · ô nhập ghi đúng cỡ tối đa thật;
 *   · luật validate không hứa quá khả năng;
 *   · trình duyệt chặn sớm, báo ngay lúc chọn tệp.
 *
 * Muốn nhận tệp lớn hơn thì sửa php.ini (upload_max_filesize VÀ post_max_size) rồi khởi động
 * lại PHP — không có cách nào lách từ phía mã nguồn.
 */
class UploadLimit
{
    /** Trần mặc định của ứng dụng, tính bằng KB (20MB) — chỉ dùng khi máy chủ còn rộng hơn. */
    public const DEFAULT_CEILING_KB = 20480;

    /**
     * Số KB lớn nhất thực sự nhận được = nhỏ nhất trong (upload_max_filesize, post_max_size,
     * trần của ứng dụng).
     *
     * post_max_size tính cho CẢ biểu mẫu (tệp PDF + ảnh bìa + mọi ô chữ) chứ không riêng một
     * tệp, nên lấy nó làm trần một tệp là hơi rộng — nhưng rộng về phía an toàn thì hơn là
     * hứa quá: con số cuối cùng vẫn không bao giờ lớn hơn thứ máy chủ chịu nhận.
     */
    public static function maxKilobytes(int $ceilingKb = self::DEFAULT_CEILING_KB): int
    {
        $limits = [$ceilingKb];

        foreach (['upload_max_filesize', 'post_max_size'] as $setting) {
            $kb = self::toKilobytes(ini_get($setting));

            // null = không đặt / đặt 0 = không giới hạn -> bỏ qua, để trần ứng dụng quyết.
            if ($kb !== null) {
                $limits[] = $kb;
            }
        }

        return max(1, (int) min($limits));
    }

    /** Cỡ tối đa viết cho người đọc: "2MB", "512KB". */
    public static function label(int $ceilingKb = self::DEFAULT_CEILING_KB): string
    {
        $kb = self::maxKilobytes($ceilingKb);

        return $kb >= 1024
            ? rtrim(rtrim(number_format($kb / 1024, 1, ',', ''), '0'), ',').'MB'
            : $kb.'KB';
    }

    /** Dùng cho phép kiểm phía trình duyệt (input type=file không hiểu "2M"). */
    public static function maxBytes(int $ceilingKb = self::DEFAULT_CEILING_KB): int
    {
        return self::maxKilobytes($ceilingKb) * 1024;
    }

    /** "8M" -> 8192 KB, "512K" -> 512, "1G" -> 1048576; "0"/"-1"/rỗng -> null (không giới hạn). */
    private static function toKilobytes(string|false|null $raw): ?int
    {
        $raw = trim((string) $raw);

        if ($raw === '' || $raw === '0' || str_starts_with($raw, '-')) {
            return null;
        }

        $unit = strtolower(substr($raw, -1));
        $value = (float) $raw;

        return match ($unit) {
            'g' => (int) round($value * 1024 * 1024),
            'm' => (int) round($value * 1024),
            'k' => (int) round($value),
            default => (int) round($value / 1024),   // không có đơn vị = byte
        };
    }
}
