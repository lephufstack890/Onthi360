<?php

namespace App\Http\Controllers;

use App\Services\DashboardRoutingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Điểm vào chung "/dashboard" — điều hướng theo vai trò hiện tại của
 * user. Ưu tiên cứng admin > teacher > parent > student cho tới khi có
 * role switcher thật (4.3) để user tự chọn không gian khi có nhiều vai trò.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardRoutingService $dashboardRoutingService,
    ) {
    }

    public function index(Request $request)
    {
        $user = Auth::user();

        return match ($this->dashboardRoutingService->primaryDashboardFor($user)) {
            DashboardRoutingService::ADMIN => redirect()->route('admin.dashboard'),
            DashboardRoutingService::TEACHER => redirect()->route('teacher.dashboard'),
            DashboardRoutingService::PARENT => redirect()->route('parent.dashboard'),
            default => app(\App\Http\Controllers\Student\DashboardController::class)->index($request),
        };
    }
}
