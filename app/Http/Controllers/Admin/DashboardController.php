<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboardService) {}

    /** admin.dashboard (ADM-01) — số liệu vận hành thật (2.1, 16 mục 9). */
    public function index(Request $request): View
    {
        return view('admin.dashboard', $this->dashboardService->dashboardData());
    }
}
