<?php

namespace App\Services\Review;

use App\Enums\ReviewerRole;
use App\Enums\ReviewStatus;
use App\Enums\ReviewTargetType;
use App\Models\Review;
use App\Models\User;
use App\Repositories\Contracts\ClassRoomRepositoryInterface;
use App\Repositories\Contracts\CompetitionRepositoryInterface;
use App\Repositories\Contracts\MaterialRepositoryInterface;
use App\Repositories\Contracts\RatingSummaryRepositoryInterface;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Models\RatingSummary;
use App\Services\ReviewEligibilityService;
use App\Services\SystemSettingService;
use App\Support\AccessDecision;
use Illuminate\Validation\ValidationException;

/**
 * Đánh giá sao / nhận xét trải nghiệm (mục 9) — dùng chung cho material, class_room,
 * teacher và competition (note họp 13/8, mục 2: "Giáo viên, tài liệu, cuộc thi cần có đánh
 * giá sao của người dùng"). Tách khỏi ReviewController để controller chỉ resolve
 * request/user và render view (giữ đúng biến/route hiện có).
 */
class ReviewService
{
    public function __construct(
        private ClassRoomRepositoryInterface $classRooms,
        private MaterialRepositoryInterface $materials,
        private UserRepositoryInterface $users,
        private CompetitionRepositoryInterface $competitions,
        private RatingSummaryRepositoryInterface $ratingSummaries,
        private ReviewRepositoryInterface $reviews,
        private ReviewEligibilityService $reviewEligibility,
        private SystemSettingService $systemSettings,
    ) {}

    /** "material"/"class"/"teacher"/"competition" (query string) -> Enum lưu trong DB. */
    private function targetTypeFor(string $type): ReviewTargetType
    {
        return match ($type) {
            'class' => ReviewTargetType::ClassRoom,
            'teacher' => ReviewTargetType::Teacher,
            'competition' => ReviewTargetType::Competition,
            default => ReviewTargetType::Material,
        };
    }

    /** Chiều ngược lại của targetTypeFor() — dùng khi build link reviews.form?type=...&id=... từ 1 Review đã có (buildMyReviews()). */
    private function queryTypeFor(ReviewTargetType $targetType): string
    {
        return match ($targetType) {
            ReviewTargetType::ClassRoom => 'class',
            ReviewTargetType::Teacher => 'teacher',
            ReviewTargetType::Competition => 'competition',
            ReviewTargetType::Material => 'material',
        };
    }

    /** Vai trò người viết SUY RA từ quan hệ thật với đối tượng — không tin type truyền lên. */
    private function resolveReviewerRole(User $user): ReviewerRole
    {
        if ($user->isTeacherApproved()) {
            return ReviewerRole::Teacher;
        }

        if ($user->childLinks()->where('status', 'verified')->exists()) {
            return ReviewerRole::Parent;
        }

        return ReviewerRole::Student;
    }

    private function findTarget(string $type, int $id): mixed
    {
        return match ($type) {
            'class' => $this->classRooms->find($id),
            'teacher' => $this->users->find($id),
            'competition' => $this->competitions->find($id),
            default => $this->materials->find($id),
        };
    }

    private function targetTitle(string $type, mixed $target): string
    {
        return match ($type) {
            'class' => $target->name ?? 'Lớp học',
            'teacher' => $target->name ?? 'Giáo viên',
            'competition' => $target->title ?? 'Cuộc thi',
            default => $target->title ?? 'Tài liệu',
        };
    }

    /** reviews.index (REV-01) — 9.5: TB, số review, phân phối 1-5 sao; <5 review thì chưa xếp hạng. */
    public function buildIndex(string $type, int $id): array
    {
        $targetType = $this->targetTypeFor($type);
        $target = $this->findTarget($type, $id);
        $targetTitle = $this->targetTitle($type, $target);

        $ratingSummary = $this->ratingSummaries->findForTarget($targetType, $id);
        $distribution = $ratingSummary?->distribution ?? [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        $minReviewsToRank = $this->systemSettings->getInt('rating.min_reviews_to_rank', RatingSummary::MIN_REVIEWS_TO_RANK);
        $isRankable = $ratingSummary?->isRankable($minReviewsToRank) ?? false;

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

        if ($type === 'teacher') {
            $teacher = $this->users->find($id);

            if (! $teacher) {
                return AccessDecision::deny('target_not_found', 'Không tìm thấy giáo viên để đánh giá.');
            }

            return $this->reviewEligibility->eligibleForTeacherReview($user, $teacher);
        }

        if ($type === 'competition') {
            $competition = $this->competitions->find($id);

            if (! $competition) {
                return AccessDecision::deny('target_not_found', 'Không tìm thấy cuộc thi để đánh giá.');
            }

            return $this->reviewEligibility->eligibleForCompetitionReview($user, $competition);
        }

        $material = $this->materials->findWithProduct($id);

        if (! $material || ! $material->product) {
            return AccessDecision::deny('target_not_found', 'Không tìm thấy học liệu để đánh giá.');
        }

        return $this->reviewEligibility->eligibleForMaterialReview($user, $material->product);
    }

    /**
     * Review CỦA CHÍNH $user cho đúng đối tượng $type/$id (target_version=1), nếu có — dùng
     * cả ở reviews.form (GET, để hiển thị "Sửa đánh giá" + điền sẵn dữ liệu cũ thay vì form
     * trắng) lẫn reviews.store (POST, để quyết định update-đè hay tạo mới). Trước đây logic
     * này chỉ tồn tại RIÊNG trong store() — form() không biết review cũ đã tồn tại nên học
     * sinh mở lại form để "sửa" chỉ thấy 1 form trắng, mất hết đánh giá/nhận xét đã viết.
     */
    public function findExistingReview(User $user, string $type, int $id): ?Review
    {
        $targetType = $this->targetTypeFor($type);

        return $this->reviews->query()
            ->where('reviewer_id', $user->id)
            ->where('target_type', $targetType->value)
            ->where('target_id', $id)
            ->where('target_version', 1)
            ->first();
    }

    /**
     * reviews.store (REV-02, gửi form) — kiểm tra lại điều kiện NGAY TẠI THỜI ĐIỂM lưu, không
     * tin form đã hiển thị lúc trước (16 mục 3). Review mới luôn ở trạng thái "Đã gửi" — chỉ
     * Admin chuyển sang "Đã công bố" mới bắt đầu tính vào rating công khai (9.4, xem
     * App\Services\Admin\ReviewService::publish()). Trong 7 ngày kể từ lần gửi gần nhất, gửi
     * lại cho đúng đối tượng sẽ SỬA đè lên review cũ (không tạo bản trùng — ràng buộc unique
     * DB theo reviewer+target+version, 9.2).
     *
     * @throws ValidationException nếu không đủ điều kiện, thiếu xác nhận công khai, hoặc đã
     *                              quá hạn sửa review cũ cho đúng đối tượng này.
     */
    public function store(User $user, string $type, int $id, array $data): Review
    {
        $decision = $this->checkEligibility($user, $type, $id);

        if (! $decision->allowed) {
            throw ValidationException::withMessages(['eligibility' => $decision->message ?? 'Bạn chưa đủ điều kiện đánh giá đối tượng này.']);
        }

        $targetType = $this->targetTypeFor($type);
        $reviewerRole = $this->resolveReviewerRole($user);

        $existing = $this->findExistingReview($user, $type, $id);

        if ($existing !== null && ! $existing->isEditable() && $existing->status !== ReviewStatus::Draft) {
            throw ValidationException::withMessages(['eligibility' => 'Bạn đã đánh giá đối tượng này và đã quá 7 ngày để sửa lại.']);
        }

        $attributes = [
            'reviewer_id' => $user->id,
            'reviewer_role' => $reviewerRole->value,
            'target_type' => $targetType->value,
            'target_id' => $id,
            'target_version' => 1,
            'overall_rating' => (int) $data['overall_rating'],
            'criteria_scores' => array_filter($data['criteria_scores'] ?? [], fn ($v) => $v !== null && (int) $v > 0),
            'comment' => $data['comment'] ?? null,
            'disclosure_ack' => true,
            'status' => ReviewStatus::Submitted->value,
            'moderation_reason' => null,
            'published_at' => null,
            'editable_until' => now()->addDays(7),
        ];

        if ($existing !== null) {
            return $this->reviews->update($existing, $attributes);
        }

        return $this->reviews->create($attributes);
    }

    /**
     * reviews.myReviews (REV-04) — 9.4: trạng thái review theo vòng đời.
     *
     * Gộp lookup đối tượng polymorphic thành các truy vấn whereIn theo từng loại (thay cho 1
     * query lookup cho mỗi review — N+1 trong bản cũ).
     */
    public function buildMyReviews(User $user): array
    {
        $myReviews = $this->reviews->byReviewer($user->id, 50);

        $classRoomIds = $myReviews->where('target_type', ReviewTargetType::ClassRoom)->pluck('target_id')->unique()->values()->all();
        $materialIds = $myReviews->where('target_type', ReviewTargetType::Material)->pluck('target_id')->unique()->values()->all();
        $teacherIds = $myReviews->where('target_type', ReviewTargetType::Teacher)->pluck('target_id')->unique()->values()->all();
        $competitionIds = $myReviews->where('target_type', ReviewTargetType::Competition)->pluck('target_id')->unique()->values()->all();

        $classRoomsById = $classRoomIds ? $this->classRooms->query()->whereIn('id', $classRoomIds)->get()->keyBy('id') : collect();
        $materialsById = $materialIds ? $this->materials->query()->whereIn('id', $materialIds)->get()->keyBy('id') : collect();
        $teachersById = $teacherIds ? $this->users->query()->whereIn('id', $teacherIds)->get()->keyBy('id') : collect();
        $competitionsById = $competitionIds ? $this->competitions->query()->whereIn('id', $competitionIds)->get()->keyBy('id') : collect();

        $result = $myReviews->map(function ($r) use ($classRoomsById, $materialsById, $teachersById, $competitionsById) {
            $targetLabel = match ($r->target_type) {
                ReviewTargetType::ClassRoom => 'Lớp '.($classRoomsById->get($r->target_id)->name ?? ''),
                ReviewTargetType::Teacher => 'Giáo viên '.($teachersById->get($r->target_id)->name ?? ''),
                ReviewTargetType::Competition => 'Cuộc thi '.($competitionsById->get($r->target_id)->title ?? ''),
                default => $materialsById->get($r->target_id)->title ?? 'Tài liệu',
            };

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
                'type' => $this->queryTypeFor($r->target_type),
                'targetId' => $r->target_id,
                'target' => $targetLabel,
                'rating' => (int) round($r->overall_rating),
                'status' => $statusLabel,
                'tone' => $tone,
                'time' => $r->created_at?->diffForHumans(),
                // Sửa được trong 7 ngày đầu (9.2) — dùng để hiện/ẩn nút "Sửa" ở reviews.myReviews
                // (trước đây trang này không có cách nào để quay lại form sửa 1 review đã gửi).
                'isEditable' => $r->isEditable(),
            ];
        })->all();

        return ['myReviews' => $result];
    }
}
