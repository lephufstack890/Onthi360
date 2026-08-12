<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\TeacherApprovalService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeacherApprovalController extends Controller
{
    public function __construct(private TeacherApprovalService $teacherApprovalService) {}

    /** admin.teacher-approvals.index — 3.3: hàng đợi duyệt giáo viên. */
    public function index(Request $request): View
    {
        return view('admin.teacher-approvals.index', $this->teacherApprovalService->pendingQueue());
    }

    /** admin.teacher-approvals.show — 3.3 + 16 mục 4 (duyệt/từ chối ghi lý do + audit log). */
    public function show(Request $request, int $teacherApproval): View
    {
        return view('admin.teacher-approvals.show', $this->teacherApprovalService->showData($teacherApproval));
    }
}
