<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Public\PracticeHistoryService;
use App\Services\Public\PracticeSampleService;
use Illuminate\Http\JsonResponse;
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
    public function __construct(
        private PracticeHistoryService $history,
        private PracticeSampleService $samples,
    ) {}

    public function problem(Request $request, int $question): View
    {
        return view('public.practice.history', $this->history->problemData(Auth::user(), $question));
    }

    public function exam(Request $request, int $assessment): View
    {
        return view('public.practice.history', $this->history->examData(Auth::user(), $assessment));
    }

    /**
     * SỬA 7/10 — ADMIN chỉ định một lượt nộp làm bài mẫu của bài tập (nút "Chỉ định bài mẫu" ở trang
     * Nhật ký). Quyền kiểm ở service; ở đây chỉ chặn sớm cho rõ lý do.
     */
    public function designateSample(Request $request, int $question): JsonResponse
    {
        abort_unless($this->samples->canDesignate(Auth::user()), 403, 'Chỉ quản trị viên mới được chỉ định bài mẫu.');

        $data = $request->validate([
            'record' => ['required', 'string', 'regex:/^A\d{1,18}$/'],
        ], [
            'record.required' => 'Chưa chọn lượt nộp.',
            'record.regex' => 'Lượt nộp không hợp lệ.',
        ]);

        $sample = $this->samples->designate(Auth::user(), $question, (int) substr($data['record'], 1));

        return response()->json([
            'ok' => true,
            'sampleId' => 'A'.$sample->attempt_answer_id,
            'submitter' => $sample->submitter_name,
        ]);
    }

    /** Bỏ chỉ định bài mẫu — tab Bài mẫu quay về tệp "Code mẫu" của câu hỏi (nếu có). */
    public function clearSample(Request $request, int $question): JsonResponse
    {
        abort_unless($this->samples->canDesignate(Auth::user()), 403, 'Chỉ quản trị viên mới được bỏ chỉ định bài mẫu.');

        $this->samples->clear(Auth::user(), $question);

        return response()->json(['ok' => true]);
    }
}
