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

    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'pending');

        return view('admin.reviews.index', $this->reviewService->indexData($tab));
    }

    public function show(Request $request, int $review): View
    {
        return view('admin.reviews.show', $this->reviewService->showData($review));
    }

    public function publish(Request $request, Review $review): RedirectResponse
    {
        $this->reviewService->publish($review);

        return redirect()->route('admin.reviews.show', $review->id)->with('status', 'review-published');
    }

    public function reject(Request $request, Review $review): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->reviewService->reject($review, $data['reason']);

        return redirect()->route('admin.reviews.show', $review->id)->with('status', 'review-rejected');
    }

    public function requestRevision(Request $request, Review $review): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->reviewService->requestRevision($review, $data['reason']);

        return redirect()->route('admin.reviews.show', $review->id)->with('status', 'review-needs-revision');
    }

    public function hide(Request $request, Review $review): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->reviewService->hide($review, $data['reason']);

        return redirect()->route('admin.reviews.show', $review->id)->with('status', 'review-hidden');
    }

    public function reply(Request $request, Review $review): RedirectResponse
    {
        $data = $request->validate(['reply' => ['required', 'string', 'max:2000']]);
        $this->reviewService->reply(Auth::user(), $review, $data['reply']);

        return redirect()->route('admin.reviews.show', $review->id)->with('status', 'review-replied');
    }
}
