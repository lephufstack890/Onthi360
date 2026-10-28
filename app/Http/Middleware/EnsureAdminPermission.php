<?php

namespace App\Http\Middleware;

use App\Models\Role;
use App\Support\AdminPermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SỬA 28/10 (khách: "Editor không có quyền vào Kho bài tập mà vẫn vào được là sai; phân quyền cho từng tính năng ở sidebar
 * admin: ai được vào, thêm, sửa, xoá") — chặn THẬT từng route khu quản trị theo danh mục App\Support\AdminPermissions
 * (tên route → mục + thao tác Xem / Thêm / Sửa / Xoá / Duyệt). Đặt sau middleware role:admin,super_admin,editor.
 *
 *  · Không có quyền, mở trang (GET): Tổng quan → chuyển tới trang đầu tiên được vào; trang khác → 403 kèm lời nhắn rõ.
 *  · Không có quyền, thao tác (POST/PUT/DELETE…): 403 (gọi JSON thì trả JSON có message).
 *  · Route quản trị chưa khai trong danh mục: chỉ Admin / Super Admin (an toàn theo mặc định).
 */
class EnsureAdminPermission
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $route = $request->route();
        $name = $route?->getName();

        if ($user === null || $user->hasRole(Role::SUPER_ADMIN) || $name === null) {
            return $next($request);
        }

        if (AdminPermissions::allowsRoute($user, $name, $request->method())) {
            return $next($request);
        }

        // Trang chủ khu quản trị (sau khi đăng nhập) mà không được xem Tổng quan → đưa tới mục đầu tiên được phép.
        if ($request->isMethod('GET') && $name === 'admin.dashboard') {
            return redirect()->route(AdminPermissions::firstAllowedRoute($user));
        }

        $rule = AdminPermissions::forRoute($name, $request->method());
        $message = is_array($rule)
            ? 'Bạn chưa được cấp quyền "'.(AdminPermissions::ACTIONS[$rule[1]] ?? $rule[1]).'" ở mục "'.(AdminPermissions::MODULES[$rule[0]]['label'] ?? $rule[0]).'". Liên hệ quản trị viên để được cấp quyền.'
            : 'Bạn không có quyền truy cập trang này.';

        if ($request->expectsJson()) {
            return response()->json(['ok' => false, 'message' => $message], 403);
        }

        abort(403, $message);
    }
}
