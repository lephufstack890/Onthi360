<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Public\PracticeHistoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * SỬA 7/10 — trang NHẬT KÝ NỘP BÀI của một bài tập / một đề luyện tập (education-main:
 * SubmissionHistoryPage, đường dẫn /luyen-tap/nhat-ky/{problem|exam}/{id}).
 *
 * Route nằm trong nhóm 'auth': nhật ký chứa bài làm của học sinh nên khách chưa đăng nhập không
 * xem được. Ai được xem lượt nộp của ai do PracticeHistoryService::visibleUserIds() quyết.
 */
class PracticeHistoryController extends Controller
{
    public function __construct(private PracticeHistoryService $history) {}

    public function problem(Request $request, int $question): View
    {
        return view('public.practice.history', $this->history->problemData(Auth::user(), $question));
    }

    public function exam(Request $request, int $assessment): View
    {
        return view('public.practice.history', $this->history->examData(Auth::user(), $assessment));
    }
}
