<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Student\StudentWorkspaceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CourseController extends Controller
{
    public function __construct(
        private StudentWorkspaceService $workspace,
    ) {}

    /** SỬA 12/10 — "Lớp học của tôi" dựng lại theo StudentClassesPanel.jsx (source mới). */
    public function index(Request $request): View
    {
        return view('student.courses.index', ['classes' => $this->workspace->classes($request->user())]);
    }
}
