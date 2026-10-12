<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chặn một chức năng khi admin đã THU HỒI riêng quyền đó của người dùng (Admin > Người dùng > Chi tiết > Phân quyền,
 * bảng permission_user, effect = revoke). Không có dòng thu hồi thì không làm gì — hành vi cũ giữ nguyên.
 * Dùng ở route: ->middleware(EnsurePermissionNotRevoked::for('teacher.question-create')).
 * (Không đăng ký alias trong routes/web.php để route:cache vẫn chạy đúng.)
 */
class EnsurePermissionNotRevoked
{
    public static function for(string $slug): string
    {
        return self::class.':'.$slug;
    }

    public function handle(Request $request, Closure $next, string $slug): Response
    {
        $user = $request->user();

        if ($user && $user->isPermissionRevoked($slug)) {
            abort(403, 'Quyền này của bạn đã bị quản trị viên thu hồi.');
        }

        return $next($request);
    }
}
