<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\Teacher\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboardService) {}

    /** teacher.dashboard (TEA-01). */
    public function index(Request $request): View
    {
        $user = Auth::user();

        return view('teacher.dashboard', [
            'name' => $user->name,
            ...$this->dashboardService->buildFor($user),
        ]);
    }
}
