<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Student\AssessmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AssessmentController extends Controller
{
    public function __construct(
        private AssessmentService $assessmentService,
    ) {}

    public function take(Request $request, int $assessment): View
    {
        $user = $request->user();
        $assignmentId = $request->filled('assignment') ? (int) $request->query('assignment') : null;

        try {
            return view('student.assessment.take', $this->assessmentService->buildTakeData($user, $assessment, $assignmentId));
        } catch (ValidationException $e) {
            abort(422, implode(' ', $e->errors()['attempt'] ?? ['Không thể mở lượt làm bài.']));
        }
    }

    /** student.assessment.take.save — "Lưu nháp", không nộp bài. */
    public function saveAnswers(Request $request, int $attempt): RedirectResponse
    {
        $user = $request->user();
        $answers = $request->input('answers', []);

        try {
            $attemptModel = $this->assessmentService->saveDraftAnswers($user, $attempt, $answers);
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
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
