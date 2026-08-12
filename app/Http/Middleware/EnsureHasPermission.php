<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Đăng ký alias trong bootstrap/app.php:
 *   $middleware->alias(['permission' => \App\Http\Middleware\EnsureHasPermission::class]);
 * Dùng ở route khi cần chặn theo quyền chi tiết (không chỉ theo role chung):
 *   Route::middleware('permission:users.manage')->group(...)
 *
 * Cùng nguyên tắc với EnsureHasRole: đây chỉ là lớp bảo vệ UI/route, không
 * thay thế kiểm tra quyền theo ngữ cảnh bản ghi cụ thể (AccessGateService...).
 */
class EnsureHasPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        if (! $request->user() || ! $request->user()->hasAnyPermission(...$permissions)) {
            abort(403, 'Bạn không có quyền truy cập trang này.');
        }

        return $next($request);
    }
}
