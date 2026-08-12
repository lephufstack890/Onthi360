<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ReviewService;
use Illuminate\Http\Request;
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
}
