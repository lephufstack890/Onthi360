<?php

namespace App\Http\Controllers;

use App\Enums\ReviewStatus;
use App\Enums\ReviewTargetType;
use App\Models\ClassRoom;
use App\Models\Material;
use App\Models\RatingSummary;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Đánh giá sao / nhận xét trải nghiệm (mục 9) — dùng chung cho mọi vai trò,
 * áp dụng cho 2 loại đối tượng: material (tài liệu) và class (lớp học).
 */
class ReviewController extends Controller
{
    /** reviews.index (REV-01) — 9.5: TB, số review, phân phối 1-5 sao; <5 review thì chưa xếp hạng. */
    public function index(Request $request): View
    {
        $type = $request->query('type', 'material');
        $id = (int) $request->query('id', 0);
        $targetType = $type === 'class' ? ReviewTargetType::ClassRoom : ReviewTargetType::Material;

        $target = $type === 'class' ? ClassRoom::find($id) : Material::find($id);
        $targetTitle = $type === 'class'
            ? ($target->name ?? 'Lớp học')
            : ($target->title ?? 'Tài liệu');

        $ratingSummary = RatingSummary::where('target_type', $targetType)->where('target_id', $id)->first();
        $distribution = $ratingSummary?->distribution ?? [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        $isRankable = $ratingSummary?->isRankable() ?? false;

        $reviews = Review::where('target_type', $targetType)
            ->where('target_id', $id)
            ->where('status', ReviewStatus::Published)
            ->with('reviewer')
            ->latest('published_at')
            ->limit(30)
            ->get()
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

        return view('reviews.index', [
            'type' => $type,
            'targetId' => $id,
            'targetTitle' => $targetTitle,
            'ratingSummary' => $ratingSummary,
            'distribution' => $distribution,
            'isRankable' => $isRankable,
            'reviews' => $reviews,
        ]);
    }

    /** reviews.form (REV-02) — 9.3: tiêu chí phụ theo loại đối tượng + disclosure bắt buộc. */
    public function form(Request $request): View
    {
        $type = $request->query('type', 'material');
        $id = (int) $request->query('id', 0);

        // TODO: kiểm tra ReviewEligibilityService trước khi cho vào form; nếu không đủ điều
        // kiện, điều hướng sang reviews.ineligible thay vì render form (9.2).
        return view('reviews.form', ['type' => $type, 'targetId' => $id]);
    }

    /** reviews.ineligible (REV-03) — 9.2: nêu rõ lý do còn thiếu. */
    public function ineligible(Request $request): View
    {
        // TODO: nối App\Services\ReviewEligibilityService thật để truyền lý do chính xác theo
        // từng trường hợp (chưa mở tài liệu / chưa tham gia đủ buổi...).
        $reason = $request->query('reason', 'Bạn cần tham gia ít nhất 2 buổi học hoặc hoàn thành một hoạt động trong lớp trước khi đánh giá.');

        return view('reviews.ineligible', ['reason' => $reason]);
    }

    /** reviews.myReviews (REV-04) — 9.4: trạng thái review theo vòng đời. */
    public function myReviews(Request $request): View
    {
        $user = Auth::user();

        $myReviews = Review::where('reviewer_id', $user->id)->latest()->limit(50)->get()->map(function ($r) {
            $target = $r->target_type === ReviewTargetType::ClassRoom
                ? ClassRoom::find($r->target_id)
                : Material::find($r->target_id);
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

        return view('reviews.my-reviews', ['myReviews' => $myReviews]);
    }
}
