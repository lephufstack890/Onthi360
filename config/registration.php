<?php

/**
 * SỬA 23/9 (khách: "khách sợ spam") — các chốt chặn spam của luồng ĐĂNG KÝ. Để ở config để
 * đổi được bằng .env mà không phải sửa mã; sửa xong chạy:
 *
 *     php artisan config:clear && php artisan config:cache
 */
return [

    /**
     * SỬA 23/9 (khách: "tạm thời chưa cần nhập OTP, check IP trước") — công tắc BẬT/TẮT bước
     * nhập mã xác minh, KHÔNG phụ thuộc vào việc cấu hình gửi thư:
     *   · null (để trống)  -> tự động: có cấu hình gửi thư thật thì bật, chưa có thì tắt;
     *   · false            -> TẮT hẳn dù đã cấu hình gửi thư (đang dùng, chờ khách khai DNS);
     *   · true             -> BẮT BUỘC nhập mã.
     * Toàn bộ mã của bước xác minh được GIỮ NGUYÊN, chỉ cần đổi .env là bật lại.
     */
    'email_verification' => env('REGISTER_EMAIL_VERIFICATION'),

    /**
     * Số lần BẮT ĐẦU đăng ký (bước 1) tối đa của một địa chỉ IP trong 1 giờ. Người dùng thật
     * hiếm khi quá 2-3 lần; máy gửi spam thì hàng chục.
     */
    'max_starts_per_ip_per_hour' => (int) env('REGISTER_MAX_STARTS_PER_IP', 5),

    /**
     * Số MÃ tối đa gửi tới cùng một email trong 1 giờ (tính cả bước 1 và nút "Gửi lại mã").
     * Chặn việc lấy hệ thống làm công cụ dội thư vào hộp thư người khác.
     */
    'max_codes_per_email_per_hour' => (int) env('REGISTER_MAX_CODES_PER_EMAIL', 3),

    /** Số TÀI KHOẢN tối đa mà một địa chỉ IP tạo được trong 1 ngày. */
    'max_registrations_per_ip_per_day' => (int) env('REGISTER_MAX_PER_IP_PER_DAY', 10),

    /**
     * Người thật cần ít nhất chừng này giây để đọc và điền form đăng ký. Bot điền tức thì.
     * Đặt 0 để tắt.
     */
    'min_form_seconds' => (int) env('REGISTER_MIN_FORM_SECONDS', 4),

    /**
     * Tên miền email dùng một lần — đăng ký bằng mấy địa chỉ này thì xác minh email mất tác
     * dụng vì ai cũng tạo được trong 5 giây. Thêm tên miền mới qua .env:
     *
     *     REGISTER_BLOCKED_EMAIL_DOMAINS="abc.com,xyz.net"
     */
    'blocked_email_domains' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('REGISTER_BLOCKED_EMAIL_DOMAINS', '')),
    ))) ?: [],

    'default_blocked_email_domains' => [
        '10minutemail.com', '10minutemail.net', 'tempmail.com', 'temp-mail.org', 'tempmail.dev',
        'guerrillamail.com', 'guerrillamail.net', 'sharklasers.com', 'grr.la',
        'mailinator.com', 'mailinator.net', 'maildrop.cc', 'dispostable.com',
        'yopmail.com', 'yopmail.net', 'trashmail.com', 'throwawaymail.com',
        'getnada.com', 'nada.email', 'emailondeck.com', 'fakeinbox.com',
        'mohmal.com', 'moakt.com', 'tmpmail.org', 'mintemail.com', 'spam4.me',
        'mytemp.email', 'tempr.email', 'inboxkitten.com', 'harakirimail.com',
    ],

    /**
     * Từ ngày này trở đi, tài khoản CHƯA xác minh email thì không đăng nhập được (dạng
     * 'Y-m-d', vd '2026-09-23'). Để trống = không chặn ai — tài khoản cũ tạo trước khi bật
     * xác minh vẫn đăng nhập bình thường.
     */
    'require_verified_login_from' => env('AUTH_REQUIRE_VERIFIED_FROM'),

];
