<?php

namespace App\Services;

use App\Enums\AttemptSource;
use App\Enums\AttemptStatus;
use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\QuestionType;
use App\Enums\VerdictStatus;
use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\Question;
use App\Models\User;
use App\Repositories\Contracts\AttemptAnswerRepositoryInterface;
use App\Repositories\Contracts\AttemptRepositoryInterface;
use App\Repositories\Contracts\AttendanceRepositoryInterface;
use App\Repositories\Contracts\ClassSessionRepositoryInterface;
use Illuminate\Validation\ValidationException;

/**
 * Luồng "học sinh làm bài & nộp bài" thật (trước đây chỉ có UI tĩnh minh họa, xem TODO cũ
 * ở Services\Student\AssessmentService::buildTakeData). Chấm điểm tự động ngay cho MCQ/điền
 * đáp án (so khớp grading_config); câu lập trình chỉ ghi nhận bài nộp ở trạng thái "Queued"
 * — CHƯA có sandbox chấm code thật (JudgeSubmission), việc đó nằm ngoài phạm vi đợt này.
 */
class AttemptService
{
    public function __construct(
        private readonly AttemptRepositoryInterface $attempts,
        private readonly AttemptAnswerRepositoryInterface $attemptAnswers,
        private readonly ClassSessionRepositoryInterface $classSessions,
        private readonly AttendanceRepositoryInterface $attendances,
    ) {}

    /**
     * Mở lượt làm bài mới hoặc tiếp tục lượt đang dở (in_progress) cho 1 đề. Nếu đề gắn
     * với 1 Assignment (giao qua lớp), lượt làm bài mang theo class_room_id + assignment_id
     * và kích hoạt điểm danh tự động (xem autoCheckIn()).
     *
     * @throws ValidationException nếu đã hết lượt làm lại theo resubmission_policy (6.3).
     */
    public function startOrResume(User $user, Assessment $assessment, ?Assignment $assignment = null): Attempt
    {
        $existing = $this->attempts->inProgressForUserAndAssessment($user->id, $assessment->id);

        if ($existing !== null) {
            return $existing;
        }

        $maxAttempts = $assessment->resubmission_policy['max_attempts'] ?? null;

        if ($maxAttempts !== null) {
            $submittedCount = $this->attempts->countSubmittedForUserAndAssessment($user->id, $assessment->id);

            if ($submittedCount >= (int) $maxAttempts) {
                throw ValidationException::withMessages([
                    'attempt' => 'Bạn đã dùng hết số lượt làm lại cho đề này ('.$maxAttempts.' lượt).',
                ]);
            }
        }

        $classRoomId = $assignment?->class_room_id;

        $attempt = $this->attempts->create([
            'user_id' => $user->id,
            'assessment_id' => $assessment->id,
            'assignment_id' => $assignment?->id,
            'class_room_id' => $classRoomId,
            'source' => ($assignment !== null ? AttemptSource::Assignment : AttemptSource::PublicPractice)->value,
            'started_at' => now(),
            'status' => AttemptStatus::InProgress->value,
            'is_provisional' => true,
        ]);

        if ($classRoomId !== null) {
            $this->autoCheckIn($user, $classRoomId);
        }

        return $attempt;
    }

    /**
     * Điểm danh tự động (source=auto) khi học sinh vào làm bài trong lúc buổi học của lớp
     * đang diễn ra — note họp: "Học sinh vào lớp để làm bài thì sẽ được điểm danh luôn".
     * Không ghi đè nếu đã có điểm danh (vd giáo viên đã điểm danh tay trước) — firstOrCreate.
     */
    private function autoCheckIn(User $user, int $classRoomId): void
    {
        $session = $this->classSessions->currentlyInProgressForClassRoomIds([$classRoomId])->first();

        if ($session === null) {
            return;
        }

        $this->attendances->query()->firstOrCreate(
            ['class_session_id' => $session->id, 'student_id' => $user->id],
            ['status' => AttendanceStatus::Present->value, 'source' => AttendanceSource::Auto->value]
        );
    }

    /**
     * Lưu (hoặc cập nhật) câu trả lời cho 1 câu trong lượt làm bài. MCQ/điền đáp án được
     * chấm ngay tại đây; câu lập trình chỉ lưu code_source/language, verdict=Queued.
     *
     * @throws ValidationException nếu lượt làm bài đã nộp/kết thúc.
     */
    public function saveAnswer(Attempt $attempt, Question $question, array $rawInput): AttemptAnswer
    {
        if ($attempt->status !== AttemptStatus::InProgress) {
            throw ValidationException::withMessages(['attempt' => 'Lượt làm bài này đã kết thúc, không thể sửa câu trả lời.']);
        }

        $codeSource = null;
        $language = null;
        $answer = [];

        if ($question->type === QuestionType::Coding) {
            $codeSource = $rawInput['code_source'] ?? null;
            $language = $rawInput['language'] ?? null;
            $score = null;
            $verdict = VerdictStatus::Queued;
        } elseif ($question->type === QuestionType::Mcq) {
            $answer = ['selected_option' => $rawInput['selected_option'] ?? null];
            [$score, $verdict] = $this->gradeMcq($question, $answer);
        } else {
            $answer = ['text' => $rawInput['text'] ?? null];
            [$score, $verdict] = $this->gradeFillBlank($question, $answer);
        }

        $existing = $this->attemptAnswers->query()
            ->where('attempt_id', $attempt->id)
            ->where('question_id', $question->id)
            ->first();

        return $this->attemptAnswers->upsertAnswer($attempt->id, $question->id, [
            'answer' => $answer,
            'code_source' => $codeSource,
            'language' => $language,
            'verdict' => $verdict->value,
            'score' => $score,
            'graded_at' => $verdict->isFinal() ? now() : null,
            'submission_count' => ($existing?->submission_count ?? 0) + 1,
        ]);
    }

    /**
     * Nộp bài: khoá lượt làm bài, cộng điểm các câu đã chấm xong (MCQ/điền đáp án), câu
     * lập trình còn "Queued" thì tổng điểm vẫn tạm tính (is_provisional) cho tới khi có
     * chấm thật (recalculateProvisionalFlag() đã có sẵn ở App\Models\Attempt).
     *
     * @throws ValidationException nếu lượt làm bài đã nộp trước đó.
     */
    public function submit(Attempt $attempt): Attempt
    {
        if ($attempt->status !== AttemptStatus::InProgress) {
            throw ValidationException::withMessages(['attempt' => 'Lượt làm bài này đã được nộp trước đó.']);
        }

        $attempt->load('answers');

        $totalScore = (int) $attempt->answers->whereNotNull('score')->sum('score');

        $attempt->recalculateProvisionalFlag();
        $attempt->total_score = $totalScore;
        $attempt->submitted_at = now();
        $attempt->status = ($attempt->is_provisional ? AttemptStatus::Grading : AttemptStatus::Graded)->value;
        $attempt->save();

        return $attempt;
    }

    /** @return array{0: ?int, 1: VerdictStatus} */
    private function gradeMcq(Question $question, array $answer): array
    {
        $config = $question->grading_config ?? [];
        $selected = $answer['selected_option'];
        $correctOptions = array_map('intval', $config['correct_options'] ?? []);

        $isCorrect = $selected !== null && $selected !== ''
            && in_array((int) $selected, $correctOptions, true);

        return [$isCorrect ? $question->points : 0, $isCorrect ? VerdictStatus::Accepted : VerdictStatus::WrongAnswer];
    }

    /** @return array{0: ?int, 1: VerdictStatus} */
    private function gradeFillBlank(Question $question, array $answer): array
    {
        $config = $question->grading_config ?? [];
        $text = mb_strtolower(trim((string) ($answer['text'] ?? '')));
        $accepted = array_map(fn ($a) => mb_strtolower(trim((string) $a)), $config['accepted_answers'] ?? []);

        $isCorrect = $text !== '' && in_array($text, $accepted, true);

        return [$isCorrect ? $question->points : 0, $isCorrect ? VerdictStatus::Accepted : VerdictStatus::WrongAnswer];
    }
}
