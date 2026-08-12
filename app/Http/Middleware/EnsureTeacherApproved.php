<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chặn giáo viên CHƯA được duyệt (3.3) khỏi các hành động "vận hành dạy học"
 * thật (vd: tạo lớp) — tự đăng ký (3.1) chỉ tạo hồ sơ ở trạng thái "Chờ duyệt",
 * không có nghĩa là được phép dạy ngay. Route middleware `role:teacher` chỉ
 * kiểm tra VAI TRÒ, không kiểm tra trạng thái duyệt, nên cần middleware riêng
 * này gắn thêm vào đúng những route "vận hành" (không gắn vào toàn bộ nhóm
 * teacher.*, vì xem lịch/xem lớp đã có v.v. vẫn nên cho phép truy cập).
 *
 * Đăng ký alias trong bootstrap/app.php:
 *   $middleware->alias(['teacher.approved' => \App\Http\Middleware\EnsureTeacherApproved::class]);
 * Dùng ở route: Route::middleware('teacher.approved')->group(...)
 */
class EnsureTeacherApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isTeacherApproved()) {
            return redirect()
                ->route('teacher.dashboard')
                ->with('warning', 'Hồ sơ giáo viên của bạn đang chờ Admin duyệt (3.3) — chưa thể tạo lớp học mới. Vui lòng chờ kết quả duyệt.');
        }

        return $next($request);
    }
}
