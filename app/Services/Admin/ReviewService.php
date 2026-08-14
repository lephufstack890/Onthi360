<?php

namespace App\Services\Admin;

use App\Enums\ReviewStatus;
use App\Enums\ReviewTargetType;
use App\Models\ClassRoom;
use App\Models\Material;
use App\Models\RatingSummary;
use App\Models\Review;
use App\Models\User;
use App\Repositories\Contracts\AccessRightRepositoryInterface;
use App\Repositories\Contracts\AttendanceRepositoryInterface;
use App\Repositories\Contracts\ClassRoomRepositoryInterface;
use App\Repositories\Contracts\MaterialRepositoryInterface;
use App\Repositories\Contracts\RatingSummaryRepositoryInterface;
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
        private RatingSummaryRepositoryInterface $ratingSummaries,
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

        // Batch tra cứu target theo loại, thay cho lookup theo từng dòng trong ->map() — tránh
        // N+1: 1 câu whereIn cho mỗi loại đối tượng thay vì tối đa 50 câu.
        $materialIds = $reviewModels->where('target_type', ReviewTargetType::Material)->pluck('target_id')->unique()->all();
        $classRoomIds = $reviewModels->where('target_type', ReviewTargetType::ClassRoom)->pluck('target_id')->unique()->all();
        $teacherIds = $reviewModels->where('target_type', ReviewTargetType::Teacher)->pluck('target_id')->unique()->all();
        $competitionIds = $reviewModels->where('target_type', ReviewTargetType::Competition)->pluck('target_id')->unique()->all();

        $materialsById = $materialIds === [] ? collect() : $this->materials->query()->whereIn('id', $materialIds)->get()->keyBy('id');
        $classRoomsById = $classRoomIds === [] ? collect() : $this->classRooms->query()->whereIn('id', $classRoomIds)->get()->keyBy('id');
        $teachersById = $teacherIds === [] ? collect() : User::whereIn('id', $teacherIds)->get()->keyBy('id');
        $competitionsById = $competitionIds === [] ? collect() : \App\Models\Competition::whereIn('id', $competitionIds)->get()->keyBy('id');

        $reviews = $reviewModels->map(function (Review $r) use ($materialsById, $classRoomsById, $teachersById, $competitionsById) {
            $targetLabel = match ($r->target_type) {
                ReviewTargetType::ClassRoom => 'Lớp '.($classRoomsById->get($r->target_id)->name ?? ''),
                ReviewTargetType::Teacher => 'Giáo viên '.($teachersById->get($r->target_id)->name ?? ''),
                ReviewTargetType::Competition => 'Cuộc thi '.($competitionsById->get($r->target_id)->title ?? ''),
                default => $materialsById->get($r->target_id)->title ?? '',
            };

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
        } elseif ($reviewModel->target_type === ReviewTargetType::Teacher) {
            $teacher = User::find($reviewModel->target_id);
            $targetLabel = 'Giáo viên '.($teacher->name ?? '');
            $evidence = $this->teacherEvidence($reviewModel, $teacher);
        } elseif ($reviewModel->target_type === ReviewTargetType::Competition) {
            $competition = \App\Models\Competition::find($reviewModel->target_id);
            $targetLabel = 'Cuộc thi '.($competition->title ?? '');
            $evidence = $this->competitionEvidence($reviewModel, $competition);
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

    /** Bằng chứng đủ điều kiện cho review GIÁO VIÊN (note họp 13/8, mục 2). */
    private function teacherEvidence(Review $review, ?User $teacher): array
    {
        $reviewer = $review->reviewer;
        if ($reviewer === null || $teacher === null) {
            return [];
        }

        $decision = $this->eligibilityService->eligibleForTeacherReview($reviewer, $teacher);

        return [
            $decision->allowed
                ? 'Đủ điều kiện trải nghiệm để đánh giá (9.2).'
                : ($decision->message ?? 'Chưa đủ điều kiện trải nghiệm.'),
        ];
    }

    /** Bằng chứng đủ điều kiện cho review CUỘC THI (note họp 13/8, mục 2). */
    private function competitionEvidence(Review $review, ?\App\Models\Competition $competition): array
    {
        $reviewer = $review->reviewer;
        if ($reviewer === null || $competition === null) {
            return [];
        }

        $decision = $this->eligibilityService->eligibleForCompetitionReview($reviewer, $competition);

        return [
            $decision->allowed
                ? 'Đã tham gia cuộc thi này (9.2).'
                : ($decision->message ?? 'Chưa từng tham gia cuộc thi này.'),
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

        $this->recomputeRatingSummary($review->target_type, $review->target_id);

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

    /**
     * admin.reviews.hide — "Ẩn sau khi công bố", PHẢI có lý do (9.4, 10.4). Không xóa dữ
     * liệu — nhưng review ẩn không còn tính vào rating công khai, nên tính lại summary.
     */
    public function hide(Review $review, string $reason): Review
    {
        Review::$auditReason = $reason;
        $review->update(['status' => ReviewStatus::Hidden, 'moderation_reason' => $reason]);
        Review::$auditReason = null;

        $this->recomputeRatingSummary($review->target_type, $review->target_id);

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

    /**
     * Tính lại avg_rating/review_count/distribution cho 1 đối tượng dựa trên TOÀN BỘ review
     * đang "Đã công bố" của đối tượng đó (9.5) — gọi lại mỗi khi 1 review được công bố hoặc bị
     * ẩn (2 hành động duy nhất làm thay đổi tập hợp review công khai).
     */
    private function recomputeRatingSummary(ReviewTargetType $targetType, int $targetId): void
    {
        $published = $this->reviews->query()
            ->where('target_type', $targetType->value)
            ->where('target_id', $targetId)
            ->where('status', ReviewStatus::Published->value)
            ->get(['overall_rating']);

        $count = $published->count();
        $distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];

        foreach ($published as $r) {
            $bucket = max(1, min(5, (int) round($r->overall_rating)));
            $distribution[$bucket]++;
        }

        $attributes = [
            'target_type' => $targetType->value,
            'target_id' => $targetId,
            'avg_rating' => $count > 0 ? round($published->avg('overall_rating'), 2) : 0,
            'review_count' => $count,
            'distribution' => $distribution,
            'updated_at_summary' => now(),
        ];

        $existing = $this->ratingSummaries->findForTarget($targetType, $targetId);

        if ($existing !== null) {
            $this->ratingSummaries->update($existing, $attributes);
        } else {
            $this->ratingSummaries->create($attributes);
        }
    }
}
