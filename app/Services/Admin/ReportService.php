<?php

namespace App\Services\Admin;

use App\Enums\ActivationCodeStatus;
use App\Enums\CompetitionStatus;
use App\Enums\OrderStatus;
use App\Models\Role;
use App\Repositories\Contracts\ActivationCodeRepositoryInterface;
use App\Repositories\Contracts\CompetitionRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;

/**
 * Báo cáo vận hành cơ bản (2.3: P0) — CHỈ số liệu vận hành (đơn hàng theo
 * trạng thái, mã kích hoạt, kiểm duyệt đánh giá, cuộc thi, người dùng theo
 * vai trò). KHÔNG bao gồm báo cáo thương mại sâu/chiến dịch (doanh thu theo
 * kênh, LTV, phân tích marketing...) — thuộc P1 (2.3), ngoài phạm vi trang
 * Báo cáo của Admin hiện tại.
 */
class ReportService
{
    public function __construct(
        private OrderRepositoryInterface $orders,
        private ActivationCodeRepositoryInterface $activationCodes,
        private ReviewRepositoryInterface $reviews,
        private CompetitionRepositoryInterface $competitions,
        private UserRepositoryInterface $users,
    ) {}

    public function indexData(): array
    {
        return [
            'orderStats' => [
                'pendingApproval' => $this->orders->countByStatuses([OrderStatus::PendingApproval->value]),
                'completed' => $this->orders->countByStatuses([OrderStatus::Completed->value]),
                'rejectedOrCanceled' => $this->orders->countByStatuses([
                    OrderStatus::Rejected->value,
                    OrderStatus::Canceled->value,
                ]),
            ],
            'activationStats' => [
                'unused' => $this->activationCodes->query()->where('status', ActivationCodeStatus::Unused)->count(),
                'activated' => $this->activationCodes->query()->where('status', ActivationCodeStatus::Activated)->count(),
                'revoked' => $this->activationCodes->query()->where('status', ActivationCodeStatus::Revoked)->count(),
            ],
            'reviewStats' => [
                'pendingModeration' => $this->reviews->countPendingModeration(),
                'published' => $this->reviews->countPublished(),
            ],
            'competitionStats' => [
                'ongoing' => $this->competitions->query()->where('status', CompetitionStatus::Ongoing)->count(),
                'pendingPublish' => $this->competitions->query()->where('status', CompetitionStatus::PendingPublish)->count(),
            ],
            'userStats' => [
                'students' => $this->users->countByRoleName(Role::STUDENT),
                'teachers' => $this->users->countByRoleName(Role::TEACHER),
                'parents' => $this->users->countByRoleName(Role::PARENT),
            ],
        ];
    }
}
