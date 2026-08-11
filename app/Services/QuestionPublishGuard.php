<?php

namespace App\Services;

use App\Enums\ContentStatus;
use App\Models\Question;
use App\Support\AccessDecision;

/**
 * Cài đúng 6.2 "Điều kiện phát hành" + 6.4 "Quy tắc chặn phát hành" cho câu hỏi
 * nhập tay hoặc sinh ra từ OCR — cùng một cổng kiểm tra, không tách riêng logic
 * cho 2 nguồn nhập liệu (tránh 2 nơi có thể lệch luật theo thời gian).
 */
class QuestionPublishGuard
{
    public function canPublish(Question $question): AccessDecision
    {
        if (blank($question->title) || blank($question->body)) {
            return AccessDecision::deny('missing_content', 'Thiếu toàn văn đề bài.');
        }

        if (! $question->hasMinimumGradingConfig()) {
            return AccessDecision::deny(
                'missing_grading_config',
                match ($question->type) {
                    \App\Enums\QuestionType::Coding => 'Thiếu test, giới hạn thời gian/bộ nhớ hoặc cấu hình OJ hợp lệ.',
                    \App\Enums\QuestionType::Mcq, \App\Enums\QuestionType::FillBlank => 'Thiếu đáp án hoặc điểm.',
                },
            );
        }

        if ($this->hasBeenAttempted($question) && $question->isDirty()) {
            return AccessDecision::deny(
                'requires_new_version',
                'Câu hỏi đã có người làm — sửa nội dung phải tạo phiên bản mới, không sửa âm thầm.',
            );
        }

        return AccessDecision::allow();
    }

    public function hasBeenAttempted(Question $question): bool
    {
        return \App\Models\AttemptAnswer::where('question_id', $question->id)->exists();
    }

    /** Tạo bản version mới thay vì overwrite khi câu đã có người làm (6.2). */
    public function createNewVersion(Question $question, array $changes): Question
    {
        $new = $question->replicate(['created_at', 'updated_at']);
        $new->fill($changes);
        $new->version = $question->version + 1;
        $new->parent_version_id = $question->id;
        $new->status = ContentStatus::Draft;
        $new->save();

        return $new;
    }
}
