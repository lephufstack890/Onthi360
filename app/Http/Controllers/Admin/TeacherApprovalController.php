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

    public function index(Request $request): View
    {
        return view('admin.teacher-approvals.index', $this->teacherApprovalService->pendingQueue());
    }

    public function show(Request $request, int $teacherApproval): View
    {
        return view('admin.teacher-approvals.show', $this->teacherApprovalService->showData($teacherApproval));
    }

    public function approve(Request $request, TeacherProfile $teacherApproval)
    {
        $this->teacherApprovalService->approve($teacherApproval, $request->user());

        return redirect()->route('admin.teacher-approvals.show', $teacherApproval->id)->with('status', 'approved');
    }

    public function reject(Request $request, TeacherProfile $teacherApproval)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $this->teacherApprovalService->reject($teacherApproval, $request->user(), $data['reason']);

        return redirect()->route('admin.teacher-approvals.show', $teacherApproval->id)->with('status', 'rejected');
    }

    public function suspend(Request $request, TeacherProfile $teacherApproval)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $this->teacherApprovalService->suspend($teacherApproval, $request->user(), $data['reason']);

        return redirect()->route('admin.teacher-approvals.show', $teacherApproval->id)->with('status', 'suspended');
    }

    public function reinstate(Request $request, TeacherProfile $teacherApproval)
    {
        $this->teacherApprovalService->reinstate($teacherApproval, $request->user());

        return redirect()->route('admin.teacher-approvals.show', $teacherApproval->id)->with('status', 'reinstated');
    }
}
