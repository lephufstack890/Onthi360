<?php

namespace App\Http\Controllers\Student;

use App\Enums\AttemptStatus;
use App\Http\Controllers\Controller;
use App\Services\Student\AssessmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AssessmentController extends Controller
{
    public function __construct(
        private AssessmentService $assessmentService,
    ) {}

    public function take(Request $request, int $assessment): View|RedirectResponse
    {
        $user = $request->user();
        $assignmentId = $request->filled('assignment') ? (int) $request->query('assignment') : null;

        try {
            $data = $this->assessmentService->buildTakeData($user, $assessment, $assignmentId);
        } catch (ValidationException $e) {
            abort(422, implode(' ', $e->errors()['attempt'] ?? ['Không thể mở lượt làm bài.']));
        }

        // buildTakeData() tự nộp attempt nếu vừa phát hiện đã hết giờ (AttemptService::
        // finalizeIfExpired() — vd học sinh đóng tab lúc hết giờ rồi quay lại sau) — khi đó
        // không còn gì để "làm bài" nữa, đưa thẳng sang trang kết quả thay vì hiện lại trang
        // làm bài (input đã khoá) rồi bắt học sinh tự bấm "Nộp bài" một lần vô nghĩa nữa.
        if ($data['attempt']->status !== AttemptStatus::InProgress) {
            return redirect()->route('student.assessment.result', $data['attempt']->id);
        }

        return view('student.assessment.take', $data);
    }

    /**
     * student.assessment.take.save — "Lưu nháp". Hỗ trợ 2 kiểu gọi: form POST thường (điều
     * hướng lại trang làm bài, KHÔNG cần JS — vẫn giữ để không phá hành vi cũ) và fetch() với
     * header Accept: application/json (autosave thời gian thực từ resources/views/student/
     * assessment/take.blade.php — mỗi lần học sinh trả lời xong 1 câu, KHÔNG đợi bấm nút).
     */
    public function saveAnswers(Request $request, int $attempt): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $answers = $request->input('answers', []);

        try {
            $attemptModel = $this->assessmentService->saveDraftAnswers($user, $attempt, $answers);
        } catch (ValidationException $e) {
            // Cả 2 lý do có thể ném ra ở đây (AttemptService::saveAnswer(): đã nộp trước đó,
            // HOẶC vừa hết giờ nên vừa được tự động nộp NGAY trong lời gọi này) đều đồng nghĩa
            // 1 điều với client: lượt làm bài không còn "đang làm" nữa — sang thẳng kết quả.
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'expired' => true,
                    'resultUrl' => route('student.assessment.result', $attempt),
                ]);
            }

            return redirect()->back()->withErrors($e->errors());
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        $params = ['assessment' => $attemptModel->assessment_id];
        if ($attemptModel->assignment_id !== null) {
            $params['assignment'] = $attemptModel->assignment_id;
        }

        return redirect()->route('student.assessment.take', $params)->with('status', 'draft-saved');
    }

    /** student.assessment.take.submit — nộp bài, khoá lượt làm bài rồi sang trang kết quả. */
    public function submit(Request $request, int $attempt): RedirectResponse
    {
        $user = $request->user();
        $answers = $request->input('answers', []);

        try {
            $attemptModel = $this->assessmentService->submitAttempt($user, $attempt, $answers);
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()->route('student.assessment.result', $attemptModel->id);
    }

    /** student.assessment.oj (STU-06/07) — làm câu lập trình đơn lẻ. */
    public function oj(Request $request, int $question): View
    {
        $user = $request->user();

        return view('student.assessment.oj', $this->assessmentService->buildOjData($user, $question));
    }

    /** student.assessment.result (STU-08/09) — kết quả bài làm. */
    public function result(Request $request, int $attempt): View
    {
        $user = $request->user();

        return view('student.assessment.result', $this->assessmentService->buildResultData($user, $attempt));
    }
}
