<?php

namespace App\Services\Public;

use App\Models\AttemptAnswer;
use App\Models\PracticeSampleSubmission;
use App\Models\Question;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * SỬA 7/10 (khách: "admin có thể chỉ định bài mẫu, chỉ admin mới có quyền này; bài nào được chỉ
 * định thì bài đó có bài mẫu và đổ vào tab Bài mẫu") — logic bài mẫu do admin chỉ định.
 *
 * QUYỀN: chỉ admin/super admin (PracticeAssignmentService::isAdmin). Giáo viên KHÔNG được —
 * đúng yêu cầu của khách. Kiểm ở service chứ không tin nút ẩn/hiện trên giao diện.
 */
class PracticeSampleService
{
    /** Cắt bài mẫu cho gọn (ký tự) — đủ cho một bài lập trình, chặn bài dán cả trăm nghìn dòng. */
    private const MAX_CODE_CHARS = 100000;

    public function __construct(private PracticeAssignmentService $assignments) {}

    public function canDesignate(?User $user): bool
    {
        return $this->assignments->isAdmin($user) && PracticeSampleSubmission::isReady();
    }

    /** Bài mẫu đang được chỉ định cho một bài tập, hoặc null. */
    public function forQuestion(int $questionId): ?PracticeSampleSubmission
    {
        if (! PracticeSampleSubmission::isReady()) {
            return null;
        }

        return PracticeSampleSubmission::query()->where('question_id', $questionId)->first();
    }

    /**
     * Chỉ định bài làm của một lượt nộp làm bài mẫu của bài tập.
     *
     * @throws ValidationException
     */
    public function designate(User $by, int $questionId, int $attemptAnswerId): PracticeSampleSubmission
    {
        if (! $this->assignments->isAdmin($by)) {
            throw ValidationException::withMessages(['record' => 'Chỉ quản trị viên mới được chỉ định bài mẫu.']);
        }

        if (! PracticeSampleSubmission::isReady()) {
            throw ValidationException::withMessages(['record' => 'Chưa cập nhật cơ sở dữ liệu (cần chạy migrate).']);
        }

        $question = Question::query()->find($questionId);
        if ($question === null) {
            throw ValidationException::withMessages(['record' => 'Không tìm thấy bài tập.']);
        }

        $answer = AttemptAnswer::query()
            ->with('attempt.user:id,name')
            ->where('question_id', $questionId)
            ->find($attemptAnswerId);

        if ($answer === null) {
            throw ValidationException::withMessages(['record' => 'Không tìm thấy lượt nộp của bài tập này.']);
        }

        $code = $this->responseOf($answer);
        if ($code === null) {
            throw ValidationException::withMessages(['record' => 'Lượt nộp này chưa lưu nội dung bài làm nên không làm bài mẫu được.']);
        }

        return PracticeSampleSubmission::query()->updateOrCreate(
            ['question_id' => $questionId],
            [
                'attempt_answer_id' => $answer->id,
                'submitter_name' => $answer->attempt?->user?->name,
                'language' => $answer->language,
                'code' => mb_substr($code, 0, self::MAX_CODE_CHARS),
                'designated_by' => $by->id,
            ],
        );
    }

    /**
     * Bỏ chỉ định bài mẫu của một bài tập — tab Bài mẫu quay về tệp "Code mẫu" của câu hỏi (nếu có).
     *
     * @throws ValidationException
     */
    public function clear(User $by, int $questionId): void
    {
        if (! $this->assignments->isAdmin($by)) {
            throw ValidationException::withMessages(['record' => 'Chỉ quản trị viên mới được bỏ chỉ định bài mẫu.']);
        }

        if (PracticeSampleSubmission::isReady()) {
            PracticeSampleSubmission::query()->where('question_id', $questionId)->delete();
        }
    }

    /** Nội dung bài làm dạng chữ của một lượt nộp; null nếu rỗng. */
    public function responseOf(AttemptAnswer $answer): ?string
    {
        $text = $answer->code_source !== null
            ? (string) $answer->code_source
            : (is_array($answer->answer) ? json_encode($answer->answer, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : null);

        return $text !== null && trim($text) !== '' ? $text : null;
    }
}
