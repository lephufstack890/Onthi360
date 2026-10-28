<?php

namespace App\Http\Controllers;

use App\Services\DashboardRoutingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            // SỬA 28/10 — biên tập viên vào mục đầu tiên được cấp quyền (không còn mặc định Kho bài tập).
            DashboardRoutingService::EDITOR => redirect()->route(\App\Support\AdminPermissions::firstAllowedRoute($user)),
            DashboardRoutingService::TEACHER => redirect()->route('teacher.dashboard'),
            DashboardRoutingService::PARENT => redirect()->route('parent.dashboard'),
            default => app(\App\Http\Controllers\Student\DashboardController::class)->index($request),
        };
    }
}
