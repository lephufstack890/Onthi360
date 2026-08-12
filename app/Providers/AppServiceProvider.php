<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
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
        // Nối tầng Permission (roles -> permissions) vào Gate chuẩn của Laravel, để
        // $user->can('slug') / @can('slug') dùng được ở mọi Controller/Blade mà
        // không cần đăng ký Policy riêng cho từng permission string. Trả `null` khi
        // không khớp permission nào (không trả `false`) để các Policy đã có (VD.
        // ClassRoomPolicy, ProductPolicy...) vẫn được Laravel gọi tới bình thường —
        // Gate::before chỉ "tắt sớm" cho các ability match đúng permission slug.
        Gate::before(function (User $user, string $ability) {
            return $user->hasPermission($ability) ? true : null;
        });
    }
}
