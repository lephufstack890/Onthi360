<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Services\Parent\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboardService) {}

    /** parent.dashboard (PAR-01) — chỉ hiển thị con đã liên kết + xác minh (10.3). */
    public function index(Request $request): View
    {
        $user = Auth::user();

        return view('parent.dashboard', $this->dashboardService->buildDashboard($user));
    }
}
