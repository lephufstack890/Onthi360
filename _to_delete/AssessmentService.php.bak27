<?php

namespace App\Services\Student;

use App\Models\Attempt;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\AssessmentRepositoryInterface;
use App\Repositories\Contracts\AssignmentRepositoryInterface;
use App\Repositories\Contracts\AttemptAnswerRepositoryInterface;
use App\Repositories\Contracts\AttemptRepositoryInterface;
use App\Repositories\Contracts\ClassRoomRepositoryInterface;
use App\Repositories\Contracts\QuestionRepositoryInterface;
use App\Services\AttemptService;
use App\Services\ReviewEligibilityService;

class AssessmentService
{
    public function __construct(
        private AssessmentRepositoryInterface $assessments,
        private AttemptRepositoryInterface $attempts,
        private AttemptAnswerRepositoryInterface $attemptAnswers,
        private QuestionRepositoryInterface $questions,
        private ClassRoomRepositoryInterface $classRooms,
        private AssignmentRepositoryInterface $assignments,
        private AttemptService $attemptService,
        private ReviewEligibilityService $reviewEligibility,
    ) {}

    /**
     * student.assessment.take (STU-05) — không gian làm bài thật (không còn là UI tĩnh):
     * mở/tiếp tục 1 Attempt thật qua App\Services\AttemptService, hiển thị đúng nội dung
     * từng câu (MCQ/điền đáp án/lập trình) + câu trả lời đã lưu nếu đang làm dở (resume).
     * $assignmentId (tuỳ chọn) cho biết học sinh vào từ "Bài được giao" của lớp nào — nếu
     * có, lượt làm bài mới sẽ mang class_room_id + kích hoạt điểm danh tự động (8.2).
     */
    public function buildTakeData(User $user, int $assessmentId, ?int $assignmentId = null): array
    {
        $assessmentModel = $this->assessments->withItemsAndQuestions($assessmentId);
        abort_if($assessmentModel === null, 404);

        $assignment = $assignmentId !== null ? $this->assignments->find($assignmentId) : null;
        if ($assignment !== null) {
            abort_unless((int) $assignment->assessment_id === $assessmentModel->id, 404);
        }

        $attempt = $this->attemptService->startOrResume($user, $assessmentModel, $assignment);

        // Hết giờ trong lúc học sinh không mở trang (đóng tab, mất mạng, rớt wifi giữa
        // chừng...) — tự nộp NGAY khi họ quay lại thay vì hiện lại y hệt trang làm bài như
        // chưa có gì xảy ra (App\Http\Controllers\Student\AssessmentController::take() kiểm
        // tra lại $attempt->status sau lời gọi này để điều hướng sang trang kết quả nếu vừa
        // được tự nộp ở đây).
        $attempt = $this->attemptService->finalizeIfExpired($attempt);

        $existingAnswers = $this->attemptAnswers->forAttempt($attempt->id);

        $questions = $assessmentModel->items->values()->map(function ($item, $idx) use ($existingAnswers) {
            $question = $item->question;
            $existing = $existingAnswers->get($question->id);

            return [
                'no' => $idx + 1,
                'questionId' => $question->id,
                'type' => $question->type->value,
                'points' => $item->effectivePoints(),
                'title' => $question->title,
                'body' => $question->body,
                'options' => $question->grading_config['options'] ?? [],
                'selectedOption' => $existing?->answer['selected_option'] ?? null,
                'textAnswer' => $existing?->answer['text'] ?? null,
                'codeSource' => $existing?->code_source,
                'language' => $existing?->language,
                'status' => $existing !== null ? 'answered' : 'unanswered',
            ];
        })->all();

        return [
            'assessmentModel' => $assessmentModel,
            'attempt' => $attempt,
            'questions' => $questions,
            // Đồng hồ đếm ngược ở client tính từ 2 mốc giờ MÁY CHỦ này (không dùng giờ máy của
            // học sinh) — null nếu đề không giới hạn thời gian (không có duration_minutes lẫn
            // không giao qua assignment có khung giờ). Việc CHẶN THẬT khi hết giờ luôn nằm ở
            // server (AttemptService::isExpired()/saveAnswer()) — đồng hồ này chỉ để hiển thị.
            'deadlineAt' => $this->attemptService->deadlineFor($attempt)?->toIso8601String(),
            'serverNow' => now()->toIso8601String(),
        ];
    }

    /**
     * student.assessment.take.save (POST "Lưu nháp") — lưu câu trả lời hiện có, KHÔNG nộp
     * bài. $answersInput dạng ['<questionId>' => ['selected_option'=>..|'text'=>..|
     * 'code_source'=>..,'language'=>..]], chỉ những câu thuộc đúng đề của attempt này mới
     * được lưu (không tin question_id client gửi lên — 16 mục 3).
     *
     * @throws \Illuminate\Validation\ValidationException nếu attempt đã kết thúc.
     */
    public function saveDraftAnswers(User $user, int $attemptId, array $answersInput): Attempt
    {
        $attempt = $this->ownedAttemptOrFail($user, $attemptId);
        $assessmentModel = $this->assessments->withItemsAndQuestions($attempt->assessment_id);

        foreach ($assessmentModel->items as $item) {
            $raw = $answersInput[$item->question_id] ?? null;
            if ($raw === null) {
                continue;
            }
            $this->attemptService->saveAnswer($attempt, $item->question, $raw);
        }

        return $attempt;
    }

    /**
     * student.assessment.take.submit (POST "Nộp bài") — lưu mọi câu trả lời gửi kèm rồi
     * khoá lượt làm bài qua AttemptService::submit().
     *
     * @throws \Illuminate\Validation\ValidationException nếu attempt đã nộp trước đó.
     */
    public function submitAttempt(User $user, int $attemptId, array $answersInput): Attempt
    {
        $this->saveDraftAnswers($user, $attemptId, $answersInput);

        $attempt = $this->ownedAttemptOrFail($user, $attemptId);

        return $this->attemptService->submit($attempt);
    }

    private function ownedAttemptOrFail(User $user, int $attemptId): Attempt
    {
        $attempt = $this->attempts->find($attemptId);
        abort_if($attempt === null, 404);
        abort_unless($attempt->user_id === $user->id, 403);

        return $attempt;
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

        $reviewCta = $this->reviewCtaTarget($user, $attemptModel);

        return [
            'attemptModel' => $attemptModel,
            'isFinal' => $isFinal,
            'score' => $score,
            'total' => $total,
            'breakdown' => $breakdown,
            'eligibleForReview' => $reviewCta !== null,
            // type/targetId đúng đối tượng CỦA CHÍNH lượt làm bài này (lớp hoặc học liệu) —
            // trước đây view hardcode thẳng route('reviews.form', ['type'=>'material','id'=>1])
            // nên nút "Đánh giá" luôn trỏ nhầm sang học liệu #1 bất kể học sinh vừa làm đề gì.
            'reviewType' => $reviewCta['type'] ?? null,
            'reviewTargetId' => $reviewCta['targetId'] ?? null,
        ];
    }

    /**
     * 9.x: CTA đánh giá cuối trang kết quả — attempt gắn với lớp (class_room_id) thì xét
     * điều kiện đánh giá LỚP; ngược lại (tự luyện/đề độc lập) xét điều kiện đánh giá HỌC LIỆU
     * qua sản phẩm chứa đề (Assessment -> Material -> Product). Trả về type/targetId ĐÚNG như
     * App\Services\Review\ReviewService mong đợi (type=class -> id=ClassRoom.id, type=material
     * -> id=Material.id, KHÔNG phải Product.id) để nút đánh giá ở trang kết quả trỏ đúng đối
     * tượng học sinh vừa học/làm, thay vì hardcode.
     *
     * @return array{type: string, targetId: int}|null null nếu chưa đủ điều kiện đánh giá.
     */
    private function reviewCtaTarget(User $user, Attempt $attemptModel): ?array
    {
        if ($attemptModel->class_room_id !== null) {
            $classRoom = $this->classRooms->find($attemptModel->class_room_id);

            if ($classRoom === null || ! $this->reviewEligibility->eligibleForClassReview($user, $classRoom)->allowed) {
                return null;
            }

            return ['type' => 'class', 'targetId' => $classRoom->id];
        }

        $material = $attemptModel->assessment?->materials?->first();
        $product = $material?->product;

        if ($material === null || $product === null || ! $this->reviewEligibility->eligibleForMaterialReview($user, $product)->allowed) {
            return null;
        }

        return ['type' => 'material', 'targetId' => $material->id];
    }
}
