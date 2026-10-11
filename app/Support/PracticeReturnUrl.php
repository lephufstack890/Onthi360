<?php

namespace App\Support;

/**
 * SỬA 11/10 (khách: "trang luyện tập public, tôi sang trang khác làm bài xong bấm thoát nó lại về trang 1,
 * tôi muốn thoát thì ở lại trang hiện tại") — trang Luyện tập gửi kèm địa chỉ đang xem (số trang, bộ lọc)
 * khi bấm "Làm bài"; màn làm bài lưu lại và "Thoát" quay về đúng địa chỉ đó.
 *
 * Chỉ nhận đường dẫn NỘI BỘ của trang Luyện tập công khai (/luyen-tap...) — chặn open-redirect: không nhận
 * '//host', 'http://...', dấu '\' hay ký tự điều khiển.
 */
final class PracticeReturnUrl
{
    public static function clean(mixed $url): ?string
    {
        if (! is_string($url) || $url === '' || strlen($url) > 1500) {
            return null;
        }

        if (preg_match('/[\x00-\x1F\x7F\\\\]/', $url) === 1) {
            return null;
        }

        $base = route('practice.index', absolute: false); // '/luyen-tap'

        if ($url !== $base && ! str_starts_with($url, $base.'?') && ! str_starts_with($url, $base.'#')) {
            return null;
        }

        return $url;
    }
}
