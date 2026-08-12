<?php

namespace App\Services\Student;

use App\Models\Attempt;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\AssessmentRepositoryInterface;
use App\Repositories\Contracts\AttemptAnswerRepositoryInterface;
use App\Repositories\Contracts\AttemptRepositoryInterface;
use App\Repositories\Contracts\ClassRoomRepositoryInterface;
use App\Repositories\Contracts\QuestionRepositoryInterface;
use App\Services\ReviewEligibilityService;

class AssessmentService
{
    public function __construct(
        private AssessmentRepositoryInterface $assessments,
        private AttemptRepositoryInterface $attempts,
        private AttemptAnswerRepositoryInterface $attemptAnswers,
        private QuestionRepositoryInterface $questions,
        private ClassRoomRepositoryInterface $classRooms,
        private ReviewEligibilityService $reviewEligibility,
    ) {}

    /** student.assessment.take (STU-05) — không gian làm bài hỗn hợp. */
    public function buildTakeData(User $user, int $assessmentId): array
    {
        $assessmentModel = $this->assessments->withItemsAndQuestions($assessmentId);
        abort_if($assessmentModel === null, 404);

        // Lấy attempt đang làm dở gần nhất (nếu có); KHÔNG tự tạo attempt mới ở đây —
        // TODO: nối App\Services\AttemptService thật để mở/tiếp tục lượt làm bài theo đúng
        // resubmission_policy (6.3) trước khi cho phép truy cập trang này.
        $attempt = $this->attempts->inProgressForUserAndAssessment($user->id, $assessmentModel->id);

        $answeredQuestionIds = $attempt ? $this->attemptAnswers->questionIdsForAttempt($attempt->id) : [];

        $questions = $assessmentModel->items->values()->map(function ($item, $idx) use ($answeredQuestionIds) {
            return [
                'no' => $idx + 1,
                'questionId' => $item->question_id,
                'status' => in_array($item->question_id, $answeredQuestionIds, true) ? 'answered' : 'unanswered',
            ];
        })->all();

        return [
            'assessmentModel' => $assessmentModel,
            'attempt' => $attempt,
            'questions' => $questions,
        ];
    }

    /** student.assessment.oj (STU-06/07) — làm câu lập trình đơn lẻ. */
    public function buildOjData(User $user, int $questionId): array
    {
        $questionModel = $this->questions->findOrFail($questionId);

        $submissions = $this->attemptAnswers->forQuestionAndUser($questionModel->id, $user->id, 10)
            ->map(fn ($answer) => [
                'time' => $answer->graded_at?->diffForHumans() ?? $answer->updated_at?->diffForHumans(),
                'verdict' => $answer->verdict?->value ?? 'pending',
                'tone' => $answer->verdict?->isFinal()
                    ? ($answer->verdict?->value === 'accepted' ? 'success' : 'danger')
                    : 'info',
            ])->all();

        return [
            'questionModel' => $questionModel,
            'submissions' => $submissions,
        ];
    }

    /** student.assessment.result (STU-08/09) — kết quả bài làm. */
    public function buildResultData(User $user, int $attemptId): array
    {
        $attemptModel = $this->attempts->withAnswersAndAssessment($attemptId);
        abort_if($attemptModel === null, 404);

        abort_unless(
            $attemptModel->user_id === $user->id || $user->hasAnyRole(Role::ADMIN, Role::SUPER_ADMIN),
            403
        );

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

        $eligibleForReview = $this->eligibleForReview($user, $attemptModel);

        return [
            'attemptModel' => $attemptModel,
            'isFinal' => $isFinal,
            'score' => $score,
            'total' => $total,
            'breakdown' => $breakdown,
            'eligibleForReview' => $eligibleForReview,
        ];
    }

    /**
     * 9.x: CTA đánh giá cuối trang kết quả — attempt gắn với lớp (class_room_id) thì xét
     * điều kiện đánh giá LỚP; ngược lại (tự luyện/đề độc lập) xét điều kiện đánh giá HỌC LIỆU
     * qua sản phẩm chứa đề (Assessment -> Material -> Product).
     */
    private function eligibleForReview(User $user, Attempt $attemptModel): bool
    {
        if ($attemptModel->class_room_id !== null) {
            $classRoom = $this->classRooms->find($attemptModel->class_room_id);

            if ($classRoom === null) {
                return false;
            }

            return $this->reviewEligibility->eligibleForClassReview($user, $classRoom)->allowed;
        }

        $product = $attemptModel->assessment?->materials?->first()?->product;

        if ($product === null) {
            return false;
        }

        return $this->reviewEligibility->eligibleForMaterialReview($user, $product)->allowed;
    }
}
