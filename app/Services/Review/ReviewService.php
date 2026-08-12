<?php

namespace App\Services\Review;

use App\Enums\ReviewStatus;
use App\Enums\ReviewTargetType;
use App\Models\User;
use App\Repositories\Contracts\ClassRoomRepositoryInterface;
use App\Repositories\Contracts\MaterialRepositoryInterface;
use App\Repositories\Contracts\RatingSummaryRepositoryInterface;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use App\Services\ReviewEligibilityService;
use App\Support\AccessDecision;

/**
 * Đánh giá sao / nhận xét trải nghiệm (mục 9) — dùng chung cho material và
 * class_room. Tách khỏi ReviewController để controller chỉ resolve
 * request/user và render view (giữ đúng biến/route hiện có).
 */
class ReviewService
{
    public function __construct(
        private ClassRoomRepositoryInterface $classRooms,
        private MaterialRepositoryInterface $materials,
        private RatingSummaryRepositoryInterface $ratingSummaries,
        private ReviewRepositoryInterface $reviews,
        private ReviewEligibilityService $reviewEligibility,
    ) {}

    /** reviews.index (REV-01) — 9.5: TB, số review, phân phối 1-5 sao; <5 review thì chưa xếp hạng. */
    public function buildIndex(string $type, int $id): array
    {
        $targetType = $type === 'class' ? ReviewTargetType::ClassRoom : ReviewTargetType::Material;

        $target = $type === 'class' ? $this->classRooms->find($id) : $this->materials->find($id);
        $targetTitle = $type === 'class'
            ? ($target->name ?? 'Lớp học')
            : ($target->title ?? 'Tài liệu');

        $ratingSummary = $this->ratingSummaries->findForTarget($targetType, $id);
        $distribution = $ratingSummary?->distribution ?? [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        $isRankable = $ratingSummary?->isRankable() ?? false;

        $reviews = $this->reviews->publishedForTarget($targetType, $id, 30)
            ->map(fn ($r) => [
                'author' => match ($r->reviewer_role->value ?? '') {
                    'parent' => 'Phụ huynh đã xác thực',
                    'teacher' => 'Giáo viên đã xác thực',
                    default => 'Học viên đã xác thực',
                },
                'rating' => (int) round($r->overall_rating),
                'time' => $r->published_at?->diffForHumans(),
                'content' => $r->comment,
            ])->all();

        return [
            'type' => $type,
            'targetId' => $id,
            'targetTitle' => $targetTitle,
            'ratingSummary' => $ratingSummary,
            'distribution' => $distribution,
            'isRankable' => $isRankable,
            'reviews' => $reviews,
        ];
    }

    /**
     * reviews.form (REV-02) — 9.2: chỉ vào form khi đủ điều kiện. Trả AccessDecision
     * (không phải bool) để controller biết lý do thật khi điều hướng sang reviews.ineligible.
     */
    public function checkEligibility(User $user, string $type, int $id): AccessDecision
    {
        if ($type === 'class') {
            $classRoom = $this->classRooms->find($id);

            if (! $classRoom) {
                return AccessDecision::deny('target_not_found', 'Không tìm thấy lớp học để đánh giá.');
            }

            return $this->reviewEligibility->eligibleForClassReview($user, $classRoom);
        }

        $material = $this->materials->findWithProduct($id);

        if (! $material || ! $material->product) {
            return AccessDecision::deny('target_not_found', 'Không tìm thấy học liệu để đánh giá.');
        }

        return $this->reviewEligibility->eligibleForMaterialReview($user, $material->product);
    }

    /**
     * reviews.myReviews (REV-04) — 9.4: trạng thái review theo vòng đời.
     *
     * Gộp lookup đối tượng polymorphic thành 2 truy vấn whereIn (1 cho
     * class_room, 1 cho material) thay cho 1 query lookup cho mỗi review
     * (N+1 trong bản cũ).
     */
    public function buildMyReviews(User $user): array
    {
        $myReviews = $this->reviews->byReviewer($user->id, 50);

        $classRoomIds = $myReviews->where('target_type', ReviewTargetType::ClassRoom)->pluck('target_id')->unique()->values()->all();
        $materialIds = $myReviews->where('target_type', ReviewTargetType::Material)->pluck('target_id')->unique()->values()->all();

        $classRoomsById = $classRoomIds ? $this->classRooms->query()->whereIn('id', $classRoomIds)->get()->keyBy('id') : collect();
        $materialsById = $materialIds ? $this->materials->query()->whereIn('id', $materialIds)->get()->keyBy('id') : collect();

        $result = $myReviews->map(function ($r) use ($classRoomsById, $materialsById) {
            $target = $r->target_type === ReviewTargetType::ClassRoom
                ? $classRoomsById->get($r->target_id)
                : $materialsById->get($r->target_id);
            $targetLabel = $r->target_type === ReviewTargetType::ClassRoom
                ? ('Lớp '.($target->name ?? ''))
                : ($target->title ?? 'Tài liệu');

            $statusLabel = match ($r->status) {
                ReviewStatus::Draft => 'Bản nháp',
                ReviewStatus::Submitted => 'Đã gửi',
                ReviewStatus::InModeration => 'Đang kiểm duyệt',
                ReviewStatus::Published => 'Đã công bố',
                ReviewStatus::NeedsRevision => 'Cần chỉnh sửa',
                ReviewStatus::Rejected => 'Từ chối',
                ReviewStatus::Hidden => 'Đã ẩn',
            };
            $tone = match ($r->status) {
                ReviewStatus::Published => 'success',
                ReviewStatus::InModeration, ReviewStatus::Submitted => 'warning',
                ReviewStatus::NeedsRevision, ReviewStatus::Rejected => 'danger',
                default => 'neutral',
            };

            return [
                'target' => $targetLabel,
                'rating' => (int) round($r->overall_rating),
                'status' => $statusLabel,
                'tone' => $tone,
                'time' => $r->created_at?->diffForHumans(),
            ];
        })->all();

        return ['myReviews' => $result];
    }
}
