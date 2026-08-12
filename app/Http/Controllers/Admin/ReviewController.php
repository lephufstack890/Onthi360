<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\ClassRoom;
use App\Models\Material;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReviewController extends Controller
{
    /** admin.reviews.index (ADM-06) — 9.4: kiểm duyệt review. */
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'pending');

        $counts = [
            'pending' => Review::whereIn('status', [ReviewStatus::Submitted, ReviewStatus::InModeration])->count(),
            // TODO: chưa có bảng review_reports liên kết đếm trực tiếp ở đây — dùng ReviewReport nếu cần chi tiết.
            'reported' => \App\Models\ReviewReport::whereHas('review', fn ($q) => $q->where('status', ReviewStatus::Published))->count(),
            'published' => Review::where('status', ReviewStatus::Published)->count(),
        ];

        $tabs = [
            ['label' => 'Chờ kiểm duyệt', 'href' => route('admin.reviews.index'), 'active' => $tab === 'pending', 'count' => $counts['pending']],
            ['label' => 'Đã báo cáo', 'href' => route('admin.reviews.index', ['tab' => 'reported']), 'active' => $tab === 'reported', 'count' => $counts['reported']],
            ['label' => 'Đã công bố', 'href' => route('admin.reviews.index', ['tab' => 'published']), 'active' => $tab === 'published', 'count' => $counts['published']],
        ];

        $query = Review::with('reviewer');
        if ($tab === 'reported') {
            $query->whereHas('reports');
        } elseif ($tab === 'published') {
            $query->where('status', ReviewStatus::Published);
        } else {
            $query->whereIn('status', [ReviewStatus::Submitted, ReviewStatus::InModeration]);
        }

        $reviews = $query->latest()->limit(50)->get()->map(function ($r) {
            $target = $r->target_type->value === 'class_room' ? ClassRoom::find($r->target_id) : Material::find($r->target_id);
            $targetLabel = $r->target_type->value === 'class_room' ? ('Lớp '.($target->name ?? '')) : ($target->title ?? '');

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

        return view('admin.reviews.index', ['tab' => $tab, 'tabs' => $tabs, 'reviews' => $reviews]);
    }

    /** admin.reviews.show — 9.4: bằng chứng đủ điều kiện trải nghiệm + quyết định kiểm duyệt. */
    public function show(Request $request, int $review): View
    {
        $reviewModel = Review::with('reviewer')->findOrFail($review);

        $target = $reviewModel->target_type->value === 'class_room'
            ? ClassRoom::find($reviewModel->target_id)
            : Material::find($reviewModel->target_id);
        $targetLabel = $reviewModel->target_type->value === 'class_room' ? ('Lớp '.($target->name ?? '')) : ($target->title ?? '');

        // TODO: nối bằng chứng thật (điểm danh/entitlement) theo ReviewEligibilityService khi có.
        $evidence = [];

        return view('admin.reviews.show', ['reviewModel' => $reviewModel, 'targetLabel' => $targetLabel, 'evidence' => $evidence]);
    }
}
