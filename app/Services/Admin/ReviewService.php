<?php

namespace App\Services\Admin;

use App\Enums\ReviewStatus;
use App\Enums\ReviewTargetType;
use App\Models\ClassRoom;
use App\Models\Material;
use App\Models\Review;
use App\Models\User;
use App\Repositories\Contracts\AccessRightRepositoryInterface;
use App\Repositories\Contracts\AttendanceRepositoryInterface;
use App\Repositories\Contracts\ClassRoomRepositoryInterface;
use App\Repositories\Contracts\MaterialRepositoryInterface;
use App\Repositories\Contracts\ReviewReportRepositoryInterface;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use App\Services\ReviewEligibilityService;
use Illuminate\Validation\ValidationException;

/**
 * Gom truy vấn/nhãn cho admin.reviews.* (ADM-06, 9.4: kiểm duyệt review).
 */
class ReviewService
{
    public function __construct(
        private ReviewRepositoryInterface $reviews,
        private ReviewReportRepositoryInterface $reviewReports,
        private MaterialRepositoryInterface $materials,
        private ClassRoomRepositoryInterface $classRooms,
        private AccessRightRepositoryInterface $accessRights,
        private AttendanceRepositoryInterface $attendance,
        private ReviewEligibilityService $eligibilityService,
    ) {}

    /** @return array{tab: string, tabs: array, reviews: array} */
    public function indexData(string $tab): array
    {
        $counts = [
            'pending' => $this->reviews->countPendingModeration(),
            // TODO: chưa có bảng review_reports liên kết đếm trực tiếp ở đây — dùng ReviewReport nếu cần chi tiết.
            'reported' => $this->reviewReports->countForPublishedReviews(),
            'published' => $this->reviews->countPublished(),
        ];

        $tabs = [
            ['label' => 'Chờ kiểm duyệt', 'href' => route('admin.reviews.index'), 'active' => $tab === 'pending', 'count' => $counts['pending']],
            ['label' => 'Đã báo cáo', 'href' => route('admin.reviews.index', ['tab' => 'reported']), 'active' => $tab === 'reported', 'count' => $counts['reported']],
            ['label' => 'Đã công bố', 'href' => route('admin.reviews.index', ['tab' => 'published']), 'active' => $tab === 'published', 'count' => $counts['published']],
        ];

        $query = $this->reviews->query()->with('reviewer');
        if ($tab === 'reported') {
            $query->whereHas('reports');
        } elseif ($tab === 'published') {
            $query->where('status', ReviewStatus::Published);
        } else {
            $query->whereIn('status', [ReviewStatus::Submitted, ReviewStatus::InModeration]);
        }

        $reviewModels = $query->latest()->limit(50)->get();

        // Batch tra cứu target theo loại, thay cho ClassRoom::find()/Material::find() theo
        // từng dòng trong ->map() — tránh N+1: chỉ 2 câu whereIn thay vì tối đa 50 câu.
        $materialIds = $reviewModels->where('target_type', ReviewTargetType::Material)->pluck('target_id')->unique()->all();
        $classRoomIds = $reviewModels->where('target_type', ReviewTargetType::ClassRoom)->pluck('target_id')->unique()->all();

        $materialsById = $materialIds === [] ? collect() : $this->materials->query()->whereIn('id', $materialIds)->get()->keyBy('id');
        $classRoomsById = $classRoomIds === [] ? collect() : $this->classRooms->query()->whereIn('id', $classRoomIds)->get()->keyBy('id');

        $reviews = $reviewModels->map(function (Review $r) use ($materialsById, $classRoomsById) {
            $target = $r->target_type === ReviewTargetType::ClassRoom
                ? $classRoomsById->get($r->target_id)
                : $materialsById->get($r->target_id);
            $targetLabel = $r->target_type === ReviewTargetType::ClassRoom
                ? ('Lớp '.($target->name ?? ''))
                : ($target->title ?? '');

            return [
                'id' => $r->id,
                'target' => $targetLabel,
                'author' => match ($r->reviewer_role->value ?? '') {
                    'parent' => 'Phụ huynh đã xác thực',
                    'teacher' => 'Giáo viên đã xác thực',
                    default => 'Học viên đã xác thực',
                },
                'rating' => (int) round($r->overall_rating),
                'excerpt' => $r->comment ? mb_substr($r->comment, 0, 80) : '',
                'status' => match ($r->status) {
                    ReviewStatus::Submitted, ReviewStatus::InModeration => 'Đang kiểm duyệt',
                    ReviewStatus::Published => 'Đã công bố',
                    ReviewStatus::NeedsRevision => 'Cần chỉnh sửa',
                    ReviewStatus::Rejected => 'Từ chối',
                    ReviewStatus::Hidden => 'Đã ẩn',
                    default => 'Bản nháp',
                },
                'tone' => match ($r->status) {
                    ReviewStatus::Published => 'success',
                    ReviewStatus::Submitted, ReviewStatus::InModeration => 'warning',
                    ReviewStatus::NeedsRevision, ReviewStatus::Rejected => 'danger',
                    default => 'neutral',
                },
            ];
        })->all();

        return ['tab' => $tab, 'tabs' => $tabs, 'reviews' => $reviews];
    }

    /** @return array{reviewModel: Review, targetLabel: string, evidence: array} */
    public function showData(int $reviewId): array
    {
        $reviewModel = $this->reviews->query()->with('reviewer')->findOrFail($reviewId);

        if ($reviewModel->target_type === ReviewTargetType::ClassRoom) {
            $classRoom = $this->classRooms->find($reviewModel->target_id);
            $targetLabel = 'Lớp '.($classRoom->name ?? '');
            $evidence = $this->classRoomEvidence($reviewModel, $classRoom);
        } else {
            $material = $this->materials->findWithProduct($reviewModel->target_id);
            $targetLabel = $material->title ?? '';
            $evidence = $this->materialEvidence($reviewModel, $material);
        }

        return ['reviewModel' => $reviewModel, 'targetLabel' => $targetLabel, 'evidence' => $evidence];
    }

    /**
     * Bằng chứng đủ điều kiện trải nghiệm cho review học liệu — nối
     * App\Services\ReviewEligibilityService::eligibleForMaterialReview() thật (thay $evidence = []
     * cũ) + 1 dữ kiện cụ thể (đã có quyền học liệu hay chưa) lấy qua AccessRightRepositoryInterface,
     * cùng nguồn dữ liệu mà chính eligibility service dùng bên trong.
     */
    private function materialEvidence(Review $review, ?Material $material): array
    {
        $reviewer = $review->reviewer;
        if ($reviewer === null || $material === null || $material->product === null) {
            return [];
        }

        $decision = $this->eligibilityService->eligibleForMaterialReview($reviewer, $material->product);
        $hasEntitlement = $this->accessRights->forUserWithProduct($reviewer->id)
            ->contains(fn ($r) => $r->product_id === $material->product->id);

        return [
            $hasEntitlement ? 'Đã có quyền học liệu này.' : 'Chưa có quyền học liệu này.',
            $decision->allowed
                ? 'Đủ điều kiện trải nghiệm để đánh giá (9.2).'
                : ($decision->message ?? 'Chưa đủ điều kiện trải nghiệm.'),
        ];
    }

    /**
     * Bằng chứng đủ điều kiện trải nghiệm cho review lớp học — nối
     * App\Services\ReviewEligibilityService::eligibleForClassReview() thật + số buổi có mặt
     * (điểm danh) qua AttendanceRepositoryInterface. Lưu ý: với review của phụ huynh,
     * eligibleForClassReview() xét điều kiện của CON (không phải của phụ huynh) — số buổi có
     * mặt hiển thị ở đây vẫn tính theo chính reviewer vì admin cần xem nhanh 1 con số, quyết
     * định đủ/thiếu điều kiện thật vẫn theo AccessDecision trả về từ service.
     */
    private function classRoomEvidence(Review $review, ?ClassRoom $classRoom): array
    {
        $reviewer = $review->reviewer;
        if ($reviewer === null || $classRoom === null) {
            return [];
        }

        $decision = $this->eligibilityService->eligibleForClassReview($reviewer, $classRoom);
        $attendance = $this->attendance->countPresentForStudentInClassRoom($reviewer->id, $classRoom->id);

        return [
            "Số buổi có mặt (điểm danh): {$attendance}.",
            $decision->allowed
                ? 'Đủ điều kiện trải nghiệm để đánh giá (9.2).'
                : ($decision->message ?? 'Chưa đủ điều kiện trải nghiệm.'),
        ];
    }

    /** admin.reviews.publish — 9.4: "Đã công bố" — sao/nhận xét bắt đầu hiển thị công khai. */
    public function publish(Review $review): Review
    {
        $review->update([
            'status' => ReviewStatus::Published,
            'published_at' => now(),
            'moderation_reason' => null,
        ]);

        return $review;
    }

    /** admin.reviews.reject — PHẢI có lý do, người viết được báo lý do (9.4, 10.4). */
    public function reject(Review $review, string $reason): Review
    {
        Review::$auditReason = $reason;
        $review->update(['status' => ReviewStatus::Rejected, 'moderation_reason' => $reason]);
        Review::$auditReason = null;

        return $review;
    }

    /** admin.reviews.request-revision — "Cần chỉnh sửa", PHẢI có lý do (9.4, 10.4). */
    public function requestRevision(Review $review, string $reason): Review
    {
        Review::$auditReason = $reason;
        $review->update(['status' => ReviewStatus::NeedsRevision, 'moderation_reason' => $reason]);
        Review::$auditReason = null;

        return $review;
    }

    /** admin.reviews.hide — "Ẩn sau khi công bố", PHẢI có lý do (9.4, 10.4). Không xóa dữ liệu. */
    public function hide(Review $review, string $reason): Review
    {
        Review::$auditReason = $reason;
        $review->update(['status' => ReviewStatus::Hidden, 'moderation_reason' => $reason]);
        Review::$auditReason = null;

        return $review;
    }

    /**
     * admin.reviews.reply — 9.4: "chỉ Admin có thể đăng phản hồi chính thức sau khi review công
     * bố. Phản hồi không che hoặc làm lại điểm sao" — lưu ở cột riêng (admin_reply), không đụng
     * vào comment/overall_rating gốc của người viết.
     */
    public function reply(User $admin, Review $review, string $reply): Review
    {
        if ($review->status !== ReviewStatus::Published) {
            throw ValidationException::withMessages(['status' => 'Chỉ đăng phản hồi được cho review đã công bố (9.4).']);
        }

        $review->update([
            'admin_reply' => $reply,
            'admin_reply_by' => $admin->id,
            'admin_reply_at' => now(),
        ]);

        return $review;
    }
}
