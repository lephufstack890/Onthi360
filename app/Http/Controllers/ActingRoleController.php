<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * SỬA 15/10 (khách: "admin có nút chuyển qua giáo viên thì giao diện thành giáo viên, chuyển qua lại được; CHỈ admin
 * mới có quyền này") — bật / tắt chế độ "Xem như Giáo viên".
 *
 * Không đổi gì trong CSDL (không gán thêm vai trò): chỉ ghi 1 khoá vào session của chính người đang đăng nhập. Việc
 * "coi như giáo viên" nằm ở User::actingRole()/hasRole(), và luôn kiểm tra lại là admin thật trong CSDL ở MỖI lần
 * dùng — nên người không phải admin không thể tự bật, kể cả khi gửi thẳng request tới 2 route này. Đăng xuất là
 * session bị xoá, lần đăng nhập sau tự về giao diện Quản trị.
 */
class ActingRoleController extends Controller
{
    /** Bật chế độ xem như Giáo viên. Chỉ admin / super admin thật. */
    public function startTeacher(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user !== null && $user->isRealAdmin(), 403, 'Chỉ quản trị viên mới chuyển được sang giao diện giáo viên.');

        $request->session()->put(User::ACTING_ROLE_SESSION_KEY, Role::TEACHER);
        Log::info('acting-role.start', ['user_id' => $user->id, 'role' => Role::TEACHER, 'ip' => $request->ip()]);

        return redirect()->route('teacher.dashboard')
            ->with('warning', 'Đang dùng giao diện Giáo viên bằng tài khoản quản trị. Bấm "Về Quản trị" ở thanh trên cùng để quay lại.');
    }

    /** Tắt chế độ xem như — luôn cho phép (chỉ xoá khoá session của chính mình). */
    public function stop(Request $request): RedirectResponse
    {
        $request->session()->forget(User::ACTING_ROLE_SESSION_KEY);

        /** @var User|null $user */
        $user = $request->user();
        if ($user !== null && $user->isRealAdmin()) {
            Log::info('acting-role.stop', ['user_id' => $user->id, 'ip' => $request->ip()]);

            return redirect()->route('admin.dashboard')->with('status', 'acting-stopped');
        }

        return redirect()->route('dashboard');
    }
}
