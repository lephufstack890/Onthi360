<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\Teacher\DashboardService;
use App\Services\Teacher\TeacherWorkspaceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
        private readonly TeacherWorkspaceService $workspace,
    ) {}

    public function index(Request $request): View
    {
        $user = Auth::user();

        return view('teacher.dashboard', [
            'name' => $user->name,
            ...$this->dashboardService->buildFor($user),
            // SỬA 12/10 — số liệu thật cho giao diện Tổng quan theo source mới (4 thẻ số, buổi dạy tiếp theo, việc cần xử lý).
            'overview' => $this->workspace->overview($user, $this->workspace->workRows($user)),
        ]);
    }
}
