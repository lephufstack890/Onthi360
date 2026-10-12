<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Services\Parent\ParentWorkspaceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** parent.reviews.index — "Đánh giá lớp" dựng theo source mới; ?child= chọn đối tượng theo dõi (chỉ con đã xác minh, 10.3). */
class ClassReviewController extends Controller
{
    public function __construct(private ParentWorkspaceService $workspace) {}

    public function index(Request $request): View
    {
        return view('parent.reviews.index', $this->workspace->snapshot($request->user(), $request->query('child')));
    }
}
