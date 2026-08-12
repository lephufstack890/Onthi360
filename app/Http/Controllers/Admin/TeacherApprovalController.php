<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TeacherApprovalStatus;
use App\Http\Controllers\Controller;
use App\Models\TeacherProfile;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeacherApprovalController extends Controller
{
    /** admin.teacher-approvals.index — 3.3: hàng đợi duyệt giáo viên. */
    public function index(Request $request): View
    {
        $pending = TeacherProfile::where('approval_status', TeacherApprovalStatus::Pending)
            ->with('user')
            ->latest()
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->user->name ?? '',
                'email' => $p->user->email ?? '',
                'submitted' => $p->created_at?->format('d/m/Y'),
                // TODO: chưa có trường "môn/chuyên môn" tách riêng — dùng subjects[0] nếu có.
                'subject' => is_array($p->subjects) && count($p->subjects) > 0 ? $p->subjects[0] : '',
            ])->all();

        return view('admin.teacher-approvals.index', ['pending' => $pending]);
    }

    /** admin.teacher-approvals.show — 3.3 + 16 mục 4 (duyệt/từ chối ghi lý do + audit log). */
    public function show(Request $request, int $teacherApproval): View
    {
        $profile = TeacherProfile::with('user')->findOrFail($teacherApproval);

        return view('admin.teacher-approvals.show', [
            'profile' => $profile,
            // TODO: chưa có bảng tài liệu minh chứng (CMND/bằng cấp) trong schema hiện tại.
            'documents' => [],
        ]);
    }
}
