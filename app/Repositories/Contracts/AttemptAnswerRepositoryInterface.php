<?php

namespace App\Repositories\Contracts;

use App\Models\AttemptAnswer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface AttemptAnswerRepositoryInterface extends BaseRepositoryInterface
{

    public function forQuestionAndUser(int $questionId, int $userId, int $limit = 10): Collection;

    public function questionIdsForAttempt(int $attemptId): array;

    /** Câu trả lời đã lưu của 1 lượt làm bài, keyed theo question_id — dùng để hiển thị lại
     * (resume) khi học sinh quay lại trang làm bài giữa chừng. */
    public function forAttempt(int $attemptId): Collection;

    /** Tạo mới hoặc cập nhật câu trả lời cho 1 câu trong 1 lượt làm bài (unique theo cặp
     * attempt_id + question_id). */
    public function upsertAnswer(int $attemptId, int $questionId, array $attributes): AttemptAnswer;
}
