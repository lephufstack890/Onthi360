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
            // SỬA 15/10 — admin đang "xem như Giáo viên" mở trang quản trị (ví dụ bấm lại 1 link cũ): đưa về khu giáo
            // viên kèm lời nhắc, thay vì trang 403 khó hiểu. Vẫn KHÔNG cho vào — muốn vào thì bấm "Về Quản trị".
            $user = $request->user();
            // SỬA 6/11 (khách: "Đề thi bên giáo viên UI và logic giống 100% admin") — giáo viên thường dùng các màn Đề thi của
            // quản trị, CHỈ trên đề của chính mình (xem App\Support\TeacherContentScope). Không thuộc diện đó thì như cũ.
            if (($scoped = \App\Support\TeacherContentScope::handle($request, $next, $roles)) !== null) {
                return $scoped;
            }
            if ($user && $request->isMethod('GET') && $user->actingRole() !== null && array_intersect($roles, ['admin', 'super_admin', 'editor']) !== []) {
                return redirect()->route('teacher.dashboard')
                    ->with('warning', 'Bạn đang ở giao diện Giáo viên. Bấm "Về Quản trị" ở thanh trên cùng để mở trang quản trị.');
            }

            abort(403, 'Bạn không có quyền truy cập trang này.');
        }

        return $next($request);
    }
}
