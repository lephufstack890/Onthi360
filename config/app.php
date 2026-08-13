<?php

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;

/**
 * File này (và cả thư mục config/) trước đó KHÔNG tồn tại trong project — Laravel âm
 * thầm dùng bộ config mặc định đóng gói sẵn trong vendor/laravel/framework/config/app.php,
 * trong đó 'timezone' bị GHI CỨNG LÀ CHUỖI 'UTC' (không đọc qua env()). Vì vậy dù .env đã
 * có APP_TIMEZONE hay không, giờ hệ thống (now(), Carbon, so sánh buổi học đã/chưa kết
 * thúc, trạng thái Assignment...) LUÔN chạy theo UTC — lệch 7 tiếng so với giờ Việt Nam,
 * khiến buổi học đã kết thúc theo giờ VN vẫn hiện "chưa kết thúc" ở phía server.
 * Fix: thêm lại config/app.php thật, có đọc APP_TIMEZONE từ .env (xem 'timezone' bên dưới).
 */
return [

    'name' => env('APP_NAME', 'Laravel'),

    'env' => env('APP_ENV', 'production'),

    'debug' => (bool) env('APP_DEBUG', false),

    'url' => env('APP_URL', 'http://localhost'),

    'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),

    'asset_url' => env('ASSET_URL'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    | Nền tảng phục vụ người dùng Việt Nam — đặt Asia/Ho_Chi_Minh làm mặc định (đọc qua
    | env() để vẫn đổi được bằng APP_TIMEZONE trong .env nếu cần), thay vì để 'UTC' cứng
    | như bản vá mặc định của framework.
    */
    'timezone' => env('APP_TIMEZONE', 'Asia/Ho_Chi_Minh'),

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

    'providers' => ServiceProvider::defaultProviders()->merge([
        // Package Service Providers...
    ])->merge([
        // Application Service Providers... (đăng ký thật ở bootstrap/providers.php,
        // Laravel 11+ không còn dùng khóa 'providers' này để load provider của app nữa)
    ])->merge([
        // Added Service Providers (Do not remove this line)...
    ])->toArray(),

    'aliases' => Facade::defaultAliases()->merge([
        // 'Example' => App\Facades\Example::class,
    ])->toArray(),

];
