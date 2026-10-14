<?php

namespace App\Support;

/**
 * SỬA 14/10 (khách: "đứng ở trang nào bấm vào đọc thì nút back quay lại đúng trang đó") — nhớ TRANG ĐÃ MỞ
 * màn đọc tài liệu để nút Quay lại / Đóng trả về đúng chỗ đó (Kho tài liệu, Tài liệu của tôi, chi tiết lớp,
 * trang quản lý tài liệu…), thay vì luôn về một trang cố định theo vai trò.
 *
 * Vì sao phải nhớ vào session chứ không lấy thẳng "trang trước": trong màn đọc người dùng còn đổi chương (mỗi
 * chương là 1 URL), bấm Làm bài rồi quay lại… — lúc đó "trang trước" là chính màn đọc / màn làm bài. Nên chỉ GHI
 * lại khi trang trước là một trang bình thường cùng website; các trang thuộc luồng đọc/làm bài thì bỏ qua, giữ
 * nguyên chỗ đã nhớ. Nhớ riêng theo từng tài liệu.
 */
class ReaderBackUrl
{
    /** Đoạn đường dẫn của các trang thuộc luồng đọc / làm bài — không coi là "trang đã mở màn đọc". */
    private const SKIP = [
        '#/(student|teacher)/materials/\d+#',   // màn đọc 1 chương
        '#/tai-lieu-cua-toi/\d+/doc#',           // màn đọc liền mạch
        '#/admin/doc-tai-lieu/#',                // màn đọc của admin
        '#/practice-by-question/#', '#/attempts/#', '#/lam-bai#', '#/tai-nguyen/#',
        '#/(login|dang-nhap|register|dang-ky)#',
    ];

    public static function resolve(?int $productId, string $fallback): string
    {
        $key = 'reader_back.'.($productId ?? 0);

        try {
            $request = request();
            $previous = (string) $request->headers->get('referer', '');

            if ($previous !== '' && self::isUsable($previous, $request->getHost())) {
                session()->put($key, $previous);

                return $previous;
            }

            $stored = session()->get($key);

            return is_string($stored) && $stored !== '' ? $stored : $fallback;
        } catch (\Throwable $e) {
            return $fallback;
        }
    }

    private static function isUsable(string $url, string $host): bool
    {
        // Chỉ nhận trang CÙNG website (chặn đưa người dùng sang trang lạ qua header Referer). So theo tên miền,
        // không so http/https vì sau Cloudflare máy chủ có thể thấy http.
        $parts = parse_url($url);
        if (! is_array($parts) || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            || strcasecmp($parts['host'] ?? '', $host) !== 0) {
            return false;
        }

        $path = (string) ($parts['path'] ?? '/');
        foreach (self::SKIP as $pattern) {
            if (preg_match($pattern, $path)) {
                return false;
            }
        }

        return true;
    }
}
