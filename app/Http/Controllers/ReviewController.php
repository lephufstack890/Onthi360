<?php

namespace App\Http\Controllers;

use App\Services\Review\ReviewService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Đánh giá sao / nhận xét trải nghiệm (mục 9) — dùng chung cho mọi vai trò,
 * áp dụng cho 2 loại đối tượng: material (tài liệu) và class (lớp học).
 */
class ReviewController extends Controller
{
    public function __construct(private ReviewService $reviewService) {}

    /** reviews.index (REV-01) — 9.5: TB, số review, phân phối 1-5 sao; <5 review thì chưa xếp hạng. */
    public function index(Request $request): View
    {
        $type = $request->query('type', 'material');
        $id = (int) $request->query('id', 0);

        return view('reviews.index', $this->reviewService->buildIndex($type, $id));
    }

    /** reviews.form (REV-02) — 9.2: chỉ vào form khi đủ điều kiện, nếu không điều hướng sang reviews.ineligible với lý do thật. */
    public function form(Request $request): View|RedirectResponse
    {
        $type = $request->query('type', 'material');
        $id = (int) $request->query('id', 0);

        $decision = $this->reviewService->checkEligibility(Auth::user(), $type, $id);

        if (! $decision->allowed) {
            return redirect()->route('reviews.ineligible', ['reason' => $decision->message]);
        }

        return view('reviews.form', ['type' => $type, 'targetId' => $id]);
    }

    /** reviews.ineligible (REV-03) — 9.2: nêu rõ lý do còn thiếu (do reviews.form điều hướng sang kèm lý do thật). */
    public function ineligible(Request $request): View
    {
        $reason = $request->query('reason', 'Bạn cần tham gia ít nhất 2 buổi học hoặc hoàn thành một hoạt động trong lớp trước khi đánh giá.');

        return view('reviews.ineligible', ['reason' => $reason]);
    }

    /** reviews.myReviews (REV-04) — 9.4: trạng thái review theo vòng đời. */
    public function myReviews(Request $request): View
    {
        $user = Auth::user();

        return view('reviews.my-reviews', $this->reviewService->buildMyReviews($user));
    }
}
