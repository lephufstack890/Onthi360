<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Đăng ký alias trong bootstrap/app.php:
 *   ->withMiddleware(function (Middleware $middleware) {
 *       $middleware->alias(['role' => \App\Http\Middleware\EnsureHasRole::class]);
 *   })
 * Dùng ở route: Route::middleware('role:teacher,admin')->group(...)
 *
 * Đây chỉ là lớp bảo vệ UI/route; kiểm tra quyền THẬT trên từng đối tượng vẫn
 * phải nằm ở Policy/Service (16 mục 3 — "không tin client" áp dụng cả cho
 * chính route middleware, vì middleware chỉ biết role chung, không biết ngữ
 * cảnh của từng bản ghi cụ thể như AccessGateService làm).
 */
class EnsureHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user() || ! $request->user()->hasAnyRole(...$roles)) {
            abort(403, 'Bạn không có quyền truy cập trang này.');
        }

        return $next($request);
    }
}
