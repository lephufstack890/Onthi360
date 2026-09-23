<?php

/**
 * SỬA 23/9 (khách: "không gửi được thư") — trước đây dự án dùng thẳng cấu hình mail mặc định
 * của Laravel (1 máy chủ, 1 cổng). Thực tế cổng gửi thư hay bị chặn: mạng công ty, mạng nhà
 * và cả nhà cung cấp VPS đều có thể chặn cổng 465 hoặc 587, mỗi nơi chặn một kiểu — dò tay
 * rất mất thời gian.
 *
 * Nên ở đây khai 2 đường tới CÙNG một hộp thư:
 *   · 'smtp'      — cổng 465, mã hoá SSL ngay từ đầu (smtps);
 *   · 'smtp_alt'  — cổng 587, mã hoá STARTTLS.
 * và 'smtp_auto' đi lần lượt: 465 không thông thì tự chuyển sang 587, không cần sửa .env.
 *
 * CỐ Ý KHÔNG xếp 'log' vào cuối chuỗi: làm vậy thì khi cả 2 cổng đều hỏng, hệ thống vẫn báo
 * "đã gửi" trong khi thư chỉ nằm trong file log — người đăng ký ngồi chờ mã không bao giờ tới.
 * Thà báo lỗi để còn biết đường sửa.
 */
return [

    'default' => env('MAIL_MAILER', 'log'),

    'mailers' => [

        'smtp_auto' => [
            'transport' => 'failover',
            'mailers' => ['smtp', 'smtp_alt'],
            'retry_after' => 60,
        ],

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => 15,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        // Cùng hộp thư, chỉ khác cổng/kiểu mã hoá — dùng khi cổng chính bị chặn.
        'smtp_alt' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_ALT_SCHEME', 'smtp'),
            'host' => env('MAIL_ALT_HOST', env('MAIL_HOST', '127.0.0.1')),
            'port' => env('MAIL_ALT_PORT', 587),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => 15,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

    ],

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Example'),
    ],

    'markdown' => [
        'theme' => env('MAIL_MARKDOWN_THEME', 'default'),
        'paths' => [resource_path('views/vendor/mail')],
    ],

];
