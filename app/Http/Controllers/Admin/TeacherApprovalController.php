<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TeacherProfile;
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

    /** admin.teacher-approvals.approve — không yêu cầu lý do. */
    public function approve(Request $request, TeacherProfile $teacherApproval)
    {
        $this->teacherApprovalService->approve($teacherApproval, $request->user());

        return redirect()->route('admin.teacher-approvals.show', $teacherApproval->id)->with('status', 'approved');
    }

    /** admin.teacher-approvals.reject — 16 mục 4: phải ghi lý do. */
    public function reject(Request $request, TeacherProfile $teacherApproval)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $this->teacherApprovalService->reject($teacherApproval, $request->user(), $data['reason']);

        return redirect()->route('admin.teacher-approvals.show', $teacherApproval->id)->with('status', 'rejected');
    }

    /** admin.teacher-approvals.suspend — 3.3: tạm dừng giáo viên đã duyệt, phải ghi lý do. */
    public function suspend(Request $request, TeacherProfile $teacherApproval)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $this->teacherApprovalService->suspend($teacherApproval, $request->user(), $data['reason']);

        return redirect()->route('admin.teacher-approvals.show', $teacherApproval->id)->with('status', 'suspended');
    }

    /** admin.teacher-approvals.reinstate — Tạm dừng/Từ chối -> Đã được duyệt lại. */
    public function reinstate(Request $request, TeacherProfile $teacherApproval)
    {
        $this->teacherApprovalService->reinstate($teacherApproval, $request->user());

        return redirect()->route('admin.teacher-approvals.show', $teacherApproval->id)->with('status', 'reinstated');
    }
}
