<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Điểm vào chung "/dashboard" — điều hướng theo vai trò hiện tại của
 * user. Ưu tiên cứng admin > teacher > parent > student cho tới khi có
 * role switcher thật (4.3) để user tự chọn không gian khi có nhiều vai trò.
 */
class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        if ($user->hasAnyRole(Role::ADMIN, Role::SUPER_ADMIN)) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->hasRole(Role::TEACHER)) {
            return redirect()->route('teacher.dashboard');
        }

        if ($user->hasRole(Role::PARENT)) {
            return redirect()->route('parent.dashboard');
        }

        return app(\App\Http\Controllers\Student\DashboardController::class)->index($request);
    }
}
