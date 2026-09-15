<?php

namespace App\Services\Admin;

use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Repositories\Contracts\ContactMessageRepositoryInterface;
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
        // SỬA 15/9 — yêu cầu hỗ trợ cũng là một hàng chờ vận hành, trước đây bảng điều khiển
        // không đếm nên quản trị chỉ biết có phiếu mới khi tình cờ mở mục đó ra.
        private ContactMessageRepositoryInterface $contactMessages,
    ) {}

    /**
     * Các hàng chờ đang tồn đọng — MỘT NGUỒN DUY NHẤT.
     *
     * SỬA 15/9 — tách ra khỏi dashboardData() vì khối "Không gian học tập" ở trang chủ cũng
     * cần đúng bộ số này cho vai trò quản trị. Viết lại truy vấn ở đó thì sớm muộn hai màn
     * hiện hai con số khác nhau cho cùng một hàng chờ, và người ta sẽ tin màn nào?
     *
     * @return array{teachers:int, orders:int, reviews:int, support:int}
     */
    public function pendingCounts(): array
    {
        return [
            'teachers' => $this->teacherProfiles->countPending(),
            'orders' => $this->orders->countByStatuses([OrderStatus::PendingApproval]),
            'reviews' => $this->reviews->countPendingModeration(),
            'support' => $this->contactMessages->countNew(),
        ];
    }

    /** @return array{stats: array<int, array<string, mixed>>, activity: array<int, array<string, mixed>>} */
    public function dashboardData(): array
    {
        $pending = $this->pendingCounts();

        $stats = [
            ['label' => 'Giáo viên chờ duyệt', 'value' => $pending['teachers'], 'tone' => 'warning', 'href' => route('admin.teacher-approvals.index')],
            ['label' => 'Đơn hàng chờ duyệt', 'value' => $pending['orders'], 'tone' => 'warning', 'href' => route('admin.orders.index')],
            ['label' => 'Yêu cầu hỗ trợ mới', 'value' => $pending['support'], 'tone' => 'warning', 'href' => route('admin.contact-messages.index')],
            ['label' => 'Review chờ kiểm duyệt', 'value' => $pending['reviews'], 'tone' => 'warning', 'href' => route('admin.reviews.index')],
            // TODO: "quyền dạy sắp hết hạn" cần công thức ngưỡng ngày thật — tạm để 0 cho tới khi thống nhất ngưỡng.
            ['label' => 'Quyền dạy sắp hết hạn (7 ngày)', 'value' => 0, 'tone' => 'danger', 'href' => route('admin.access-rights.index')],
            ['label' => 'Tổng người dùng', 'value' => number_format($this->users->count()), 'tone' => 'neutral', 'href' => route('admin.users.index')],
            // ['label' => 'Câu hỏi chờ rà soát (OCR)', 'value' => $this->draftQuestions->countPendingReview(), 'tone' => 'warning', 'href' => route('admin.content.index', ['tab' => 'drafts'])],
        ];

        $activity = $this->auditLogs->latestWithActor(10)->map(fn ($log) => [
            'time' => $log->created_at?->diffForHumans(),
            'text' => $log->action.($log->reason ? ' — '.$log->reason : ''),
            'actor' => $log->actor->email ?? 'system',
        ])->all();

        return ['stats' => $stats, 'activity' => $activity];
    }
}
