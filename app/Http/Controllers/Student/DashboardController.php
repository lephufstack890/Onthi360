<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Student\StudentWorkspaceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private StudentWorkspaceService $workspace,
    ) {}

    /** SỬA 12/10 — Tổng quan dựng lại theo source mới (StudentOverview.jsx): dữ liệu từ StudentWorkspaceService::overview(). */
    public function index(Request $request): View
    {
        return view('student.dashboard', $this->workspace->overview($request->user()));
    }
}
