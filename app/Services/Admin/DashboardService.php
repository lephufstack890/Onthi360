<?php

namespace App\Services\Admin;

use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Repositories\Contracts\DraftQuestionRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use App\Repositories\Contracts\TeacherProfileRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Enums\OrderStatus;

/**
 * Gom số liệu vận hành cho admin.dashboard (ADM-01, 2.1, 16 mục 9).
 */
class DashboardService
{
    public function __construct(
        private TeacherProfileRepositoryInterface $teacherProfiles,
        private OrderRepositoryInterface $orders,
        private ReviewRepositoryInterface $reviews,
        private UserRepositoryInterface $users,
        private DraftQuestionRepositoryInterface $draftQuestions,
        private AuditLogRepositoryInterface $auditLogs,
    ) {}

    /** @return array{stats: array<int, array<string, mixed>>, activity: array<int, array<string, mixed>>} */
    public function dashboardData(): array
    {
        $stats = [
            ['label' => 'Giáo viên chờ duyệt', 'value' => $this->teacherProfiles->countPending(), 'tone' => 'warning', 'href' => route('admin.teacher-approvals.index')],
            ['label' => 'Đơn hàng chờ duyệt', 'value' => $this->orders->countByStatuses([OrderStatus::PendingApproval]), 'tone' => 'warning', 'href' => route('admin.orders.index')],
            ['label' => 'Review chờ kiểm duyệt', 'value' => $this->reviews->countPendingModeration(), 'tone' => 'warning', 'href' => route('admin.reviews.index')],
            // TODO: "quyền dạy sắp hết hạn" cần công thức ngưỡng ngày thật — tạm để 0 cho tới khi thống nhất ngưỡng.
            ['label' => 'Quyền dạy sắp hết hạn (7 ngày)', 'value' => 0, 'tone' => 'danger', 'href' => route('admin.access-rights.index')],
            ['label' => 'Tổng người dùng', 'value' => number_format($this->users->count()), 'tone' => 'neutral', 'href' => route('admin.users.index')],
            ['label' => 'Câu hỏi chờ rà soát (OCR)', 'value' => $this->draftQuestions->countPendingReview(), 'tone' => 'warning', 'href' => route('admin.content.index', ['tab' => 'drafts'])],
        ];

        $activity = $this->auditLogs->latestWithActor(10)->map(fn ($log) => [
            'time' => $log->created_at?->diffForHumans(),
            'text' => $log->action.($log->reason ? ' — '.$log->reason : ''),
            'actor' => $log->actor->email ?? 'system',
        ])->all();

        return ['stats' => $stats, 'activity' => $activity];
    }
}
