<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Public\SubmissionListService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * SỬA 23/10 (khách: "nút Lịch sử làm bài / Lịch sử làm đề ở trang Luyện tập, bấm vào ra danh sách bài nộp") —
 *   practice.submissions.problems : /luyen-tap/lich-su-lam-bai  (bài tập chuyên đề)
 *   practice.submissions.exams    : /luyen-tap/lich-su-lam-de   (đề thi luyện tập)
 * Cần đăng nhập (route). Ai xem được của ai: App\Services\Public\SubmissionListService.
 */
class SubmissionListController extends Controller
{
    public function __construct(private readonly SubmissionListService $service) {}

    public function problems(Request $request): View
    {
        return view('public.practice.submissions', $this->service->problems($request->user(), $this->filters($request)));
    }

    public function exams(Request $request): View
    {
        return view('public.practice.submissions', $this->service->exams($request->user(), $this->filters($request)));
    }

    private function filters(Request $request): array
    {
        return [
            'all' => $request->boolean('all'),
            'status' => (string) $request->query('status', ''),
            'q' => mb_substr((string) $request->query('q', ''), 0, 100),
        ];
    }
}
