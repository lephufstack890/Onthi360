<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Services\Admin\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function __construct(private ReviewService $reviewService) {}

    /** admin.reviews.index (ADM-06) — 9.4: kiểm duyệt review. */
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'pending');

        return view('admin.reviews.index', $this->reviewService->indexData($tab));
    }

    /** admin.reviews.show — 9.4: bằng chứng đủ điều kiện trải nghiệm + quyết định kiểm duyệt. */
    public function show(Request $request, int $review): View
    {
        return view('admin.reviews.show', $this->reviewService->showData($review));
    }

    /** admin.reviews.publish. */
    public function publish(Request $request, Review $review): RedirectResponse
    {
        $this->reviewService->publish($review);

        return redirect()->route('admin.reviews.show', $review->id)->with('status', 'review-published');
    }

    /** admin.reviews.reject — PHẢI có lý do (9.4, 10.4). */
    public function reject(Request $request, Review $review): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->reviewService->reject($review, $data['reason']);

        return redirect()->route('admin.reviews.show', $review->id)->with('status', 'review-rejected');
    }

    /** admin.reviews.request-revision — PHẢI có lý do (9.4, 10.4). */
    public function requestRevision(Request $request, Review $review): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->reviewService->requestRevision($review, $data['reason']);

        return redirect()->route('admin.reviews.show', $review->id)->with('status', 'review-needs-revision');
    }

    /** admin.reviews.hide — PHẢI có lý do (9.4, 10.4). */
    public function hide(Request $request, Review $review): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->reviewService->hide($review, $data['reason']);

        return redirect()->route('admin.reviews.show', $review->id)->with('status', 'review-hidden');
    }

    /** admin.reviews.reply — chỉ Admin, chỉ khi đã công bố (9.4). */
    public function reply(Request $request, Review $review): RedirectResponse
    {
        $data = $request->validate(['reply' => ['required', 'string', 'max:2000']]);
        $this->reviewService->reply(Auth::user(), $review, $data['reply']);

        return redirect()->route('admin.reviews.show', $review->id)->with('status', 'review-replied');
    }
}
