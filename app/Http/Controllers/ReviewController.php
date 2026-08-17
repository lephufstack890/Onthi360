<?php

namespace App\Http\Controllers;

use App\Services\Review\ReviewService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function __construct(private ReviewService $reviewService) {}

    public function index(Request $request): View
    {
        $type = $request->query('type', 'material');
        $id = (int) $request->query('id', 0);

        return view('reviews.index', $this->reviewService->buildIndex($type, $id));
    }

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

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'in:material,class,teacher,competition'],
            'target_id' => ['required', 'integer', 'min:1'],
            'overall_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'criteria_scores' => ['nullable', 'array'],
            'criteria_scores.*' => ['nullable', 'integer', 'min:0', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'disclosure_ack' => ['accepted'],
        ]);

        try {
            $this->reviewService->store(Auth::user(), $data['type'], (int) $data['target_id'], $data);
        } catch (ValidationException $e) {
            return redirect()
                ->route('reviews.form', ['type' => $data['type'], 'id' => $data['target_id']])
                ->withErrors($e->errors())
                ->withInput();
        }

        return redirect()->route('reviews.myReviews')->with('status', 'review-submitted');
    }

    public function ineligible(Request $request): View
    {
        $reason = $request->query('reason', 'Bạn cần tham gia ít nhất 2 buổi học hoặc hoàn thành một hoạt động trong lớp trước khi đánh giá.');

        return view('reviews.ineligible', ['reason' => $reason]);
    }

    public function myReviews(Request $request): View
    {
        $user = Auth::user();

        return view('reviews.my-reviews', $this->reviewService->buildMyReviews($user));
    }
}
