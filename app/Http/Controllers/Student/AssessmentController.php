<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Attempt;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AssessmentController extends Controller
{
    /** student.assessment.take (STU-05) — không gian làm bài hỗn hợp. */
    public function take(Request $request, int $assessment): View
    {
        $user = Auth::user();
        $assessmentModel = Assessment::with('items.question')->findOrFail($assessment);

        // Lấy attempt đang làm dở gần nhất (nếu có); KHÔNG tự tạo attempt mới ở đây —
        // TODO: nối App\Services\AttemptService thật để mở/tiếp tục lượt làm bài theo đúng
        // resubmission_policy (6.3) trước khi cho phép truy cập trang này.
        $attempt = $user->attempts()
            ->where('assessment_id', $assessmentModel->id)
            ->where('status', 'in_progress')
            ->latest('started_at')
            ->first();

        $answeredQuestionIds = $attempt ? $attempt->answers()->pluck('question_id')->all() : [];

        $questions = $assessmentModel->items->values()->map(function ($item, $idx) use ($answeredQuestionIds) {
            return [
                'no' => $idx + 1,
                'questionId' => $item->question_id,
                'status' => in_array($item->question_id, $answeredQuestionIds, true) ? 'answered' : 'unanswered',
            ];
        })->all();

        return view('student.assessment.take', [
            'assessmentModel' => $assessmentModel,
            'attempt' => $attempt,
            'questions' => $questions,
        ]);
    }

    /** student.assessment.oj (STU-06/07) — làm câu lập trình đơn lẻ. */
    public function oj(Request $request, int $question): View
    {
        $user = Auth::user();
        $questionModel = Question::findOrFail($question);

        $submissions = \App\Models\AttemptAnswer::where('question_id', $questionModel->id)
            ->whereHas('attempt', fn ($q) => $q->where('user_id', $user->id))
            ->latest('graded_at')
            ->limit(10)
            ->get()
            ->map(fn ($answer) => [
                'time' => $answer->graded_at?->diffForHumans() ?? $answer->updated_at?->diffForHumans(),
                'verdict' => $answer->verdict?->value ?? 'pending',
                'tone' => $answer->verdict?->isFinal()
                    ? ($answer->verdict?->value === 'accepted' ? 'success' : 'danger')
                    : 'info',
            ])->all();

        return view('student.assessment.oj', [
            'questionModel' => $questionModel,
            'submissions' => $submissions,
        ]);
    }

    /** student.assessment.result (STU-08/09) — kết quả bài làm. */
    public function result(Request $request, int $attempt): View
    {
        $user = Auth::user();
        $attemptModel = Attempt::with('answers.question', 'assessment')->findOrFail($attempt);

        abort_unless($attemptModel->user_id === $user->id || $user->hasAnyRole(\App\Models\Role::ADMIN, \App\Models\Role::SUPER_ADMIN), 403);

        $isFinal = ! $attemptModel->is_provisional;
        $score = $attemptModel->total_score;
        $total = $attemptModel->assessment->total_points ?? null;

        $breakdown = $attemptModel->answers->map(function ($answer, $idx) {
            $verdictLabel = match ($answer->verdict?->value) {
                'accepted' => 'Đúng',
                'wrong_answer' => 'Sai',
                'pending', 'queued', 'judging' => 'Đang chấm',
                default => $answer->verdict?->value ?? '—',
            };
            $tone = match (true) {
                $answer->verdict?->value === 'accepted' => 'success',
                in_array($answer->verdict?->value, ['pending', 'queued', 'judging'], true) => 'info',
                $answer->verdict === null => 'neutral',
                default => 'danger',
            };

            return [
                'no' => $idx + 1,
                'type' => $answer->question?->type?->value ?? '',
                'verdict' => $verdictLabel,
                'points' => $answer->score !== null ? (string) $answer->score : '—',
                'tone' => $tone,
            ];
        })->all();

        // TODO: nối App\Services\ReviewEligibilityService thật cho CTA đánh giá cuối trang (9.x).
        $eligibleForReview = false;

        return view('student.assessment.result', [
            'attemptModel' => $attemptModel,
            'isFinal' => $isFinal,
            'score' => $score,
            'total' => $total,
            'breakdown' => $breakdown,
            'eligibleForReview' => $eligibleForReview,
        ]);
    }
}
