<?php

namespace App\Providers;

use App\Models\Role;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * SỬA 18/9 — chốt chặn thứ hai cho chuyện http/https (chặn chính là trustProxies() ở
         * bootstrap/app.php). Lý do cần CẢ HAI: trustProxies chỉ ăn khi proxy thật sự gửi
         * X-Forwarded-Proto; có cấu hình nginx/CDN không gửi header đó, khi ấy Laravel vẫn sinh
         * URL http giữa trang https và mọi fetch() lại bị trình duyệt chặn như cũ. Dòng dưới
         * đảm bảo không phụ thuộc vào việc proxy có gửi header hay không.
         *
         * CỐ Ý bám theo APP_URL chứ không bật cứng: máy lập trình chạy http://localhost, ép
         * https ở đó sẽ hỏng toàn bộ link nội bộ.
         */
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Nối tầng Permission (roles -> permissions) vào Gate chuẩn của Laravel, để
        // $user->can('slug') / @can('slug') dùng được ở mọi Controller/Blade mà
        // không cần đăng ký Policy riêng cho từng permission string. Trả `null` khi
        // không khớp permission nào (không trả `false`) để các Policy đã có (VD.
        // ClassRoomPolicy, ProductPolicy...) vẫn được Laravel gọi tới bình thường —
        // Gate::before chỉ "tắt sớm" cho các ability match đúng permission slug.
        Gate::before(function (User $user, string $ability) {
            return $user->hasPermission($ability) ? true : null;
        });

        // Chuông thông báo toàn cục (partials.notifications-bell, include ở layouts.app
        // cho MỌI vai trò) — bơm dữ liệu thật một chỗ duy nhất thay vì lặp lại ở từng
        // Controller/route. "Xem tất cả" trỏ tới trang thông báo riêng của từng vai trò
        // nếu vai trò đó đã có trang (hiện: giáo viên, học sinh); vai trò khác chưa có
        // trang riêng thì ẩn link "Xem tất cả", chỉ hiện dropdown 5 thông báo gần nhất.
        View::composer('partials.notifications-bell', function ($view) {
            $user = auth()->user();

            if ($user === null) {
                $view->with(['bellItems' => [], 'bellUnreadCount' => 0, 'bellViewAllRoute' => null]);

                return;
            }

            $data = app(NotificationService::class)->bellData($user);

            $viewAllRoute = match (true) {
                $user->hasRole(Role::TEACHER) => route('teacher.notifications.index'),
                $user->hasRole(Role::STUDENT) => route('student.notifications'),
                default => null,
            };

            $view->with([
                'bellItems' => $data['items'],
                'bellUnreadCount' => $data['unreadCount'],
                'bellViewAllRoute' => $viewAllRoute,
            ]);
        });
    }
}
