<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContentStatus;
use App\Enums\OrderStatus;
use App\Enums\ReviewStatus;
use App\Enums\TeacherApprovalStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DraftQuestion;
use App\Models\Order;
use App\Models\Review;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** admin.dashboard (ADM-01) — số liệu vận hành thật (2.1, 16 mục 9). */
    public function index(Request $request): View
    {
        $stats = [
            ['label' => 'Giáo viên chờ duyệt', 'value' => TeacherProfile::where('approval_status', TeacherApprovalStatus::Pending)->count(), 'tone' => 'warning', 'href' => route('admin.teacher-approvals.index')],
            ['label' => 'Đơn hàng chờ duyệt', 'value' => Order::where('status', OrderStatus::PendingApproval)->count(), 'tone' => 'warning', 'href' => route('admin.orders.index')],
            ['label' => 'Review chờ kiểm duyệt', 'value' => Review::whereIn('status', [ReviewStatus::Submitted, ReviewStatus::InModeration])->count(), 'tone' => 'warning', 'href' => route('admin.reviews.index')],
            // TODO: "quyền dạy sắp hết hạn" cần công thức ngưỡng ngày thật — tạm để 0 cho tới khi thống nhất ngưỡng.
            ['label' => 'Quyền dạy sắp hết hạn (7 ngày)', 'value' => 0, 'tone' => 'danger', 'href' => route('admin.access-rights.index')],
            ['label' => 'Tổng người dùng', 'value' => number_format(User::count()), 'tone' => 'neutral', 'href' => route('admin.users.index')],
            ['label' => 'Câu hỏi chờ rà soát (OCR)', 'value' => DraftQuestion::where('review_status', 'pending')->count(), 'tone' => 'warning', 'href' => route('admin.content.index', ['tab' => 'drafts'])],
        ];

        $activity = AuditLog::with('actor')->latest()->limit(10)->get()->map(fn ($log) => [
            'time' => $log->created_at?->diffForHumans(),
            'text' => $log->action.($log->reason ? ' — '.$log->reason : ''),
            'actor' => $log->actor->email ?? 'system',
        ])->all();

        return view('admin.dashboard', ['stats' => $stats, 'activity' => $activity]);
    }
}
