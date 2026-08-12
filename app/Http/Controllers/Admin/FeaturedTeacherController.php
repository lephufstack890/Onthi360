<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TeacherApprovalStatus;
use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\TeacherProfile;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeaturedTeacherController extends Controller
{
    /**
     * admin.featured-teachers.index — PUB-10 (trang vinh danh, không phải danh bạ cá nhân, 12.2).
     * TODO: chưa có trường "featured" trong schema (teacher_profiles) — cần migration thêm cột
     * is_featured (hoặc bảng riêng) trước khi nút "Vinh danh/Bỏ vinh danh" có tác dụng thật.
     */
    public function index(Request $request): View
    {
        $tabs = [
            ['label' => 'Cuộc thi', 'href' => route('admin.competitions.index'), 'active' => false, 'count' => Competition::count()],
            ['label' => 'Giáo viên tiêu biểu', 'href' => route('admin.featured-teachers.index'), 'active' => true, 'count' => TeacherProfile::where('approval_status', TeacherApprovalStatus::Approved)->count()],
        ];

        $teachers = TeacherProfile::where('approval_status', TeacherApprovalStatus::Approved)
            ->with('user')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->user_id,
                'name' => $p->user->name ?? '',
                'subject' => is_array($p->subjects) && count($p->subjects) > 0 ? $p->subjects[0] : '',
                'featured' => false,
            ])->all();

        return view('admin.featured-teachers.index', ['tabs' => $tabs, 'teachers' => $teachers]);
    }
}
