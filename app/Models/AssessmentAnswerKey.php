<?php

namespace App\Models;

use App\Enums\AnswerSheetQuestionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Đáp án đúng của 1 câu trong đề PDF (Assessment::content_mode = pdf_answer_sheet) — KHÔNG
 * liên quan tới AssessmentItem/Question (đó là mô hình câu hỏi rời, dùng cho content_mode =
 * structured). Mỗi dòng ở đây là 1 câu trong phiếu trả lời, đánh số theo đúng thứ tự in
 * trên đề PDF (Câu 1, Câu 2...), không theo id.
 */
class AssessmentAnswerKey extends Model
{
    protected $fillable = ['assessment_id', 'question_no', 'question_type', 'correct_answer', 'points'];

    protected $casts = [
        'question_type' => AnswerSheetQuestionType::class,
        'correct_answer' => 'array',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /**
     * So đáp án học sinh nộp với đáp án đúng — hình dạng $submitted phải khớp đúng dạng
     * lưu trong correct_answer theo question_type (xem App\Enums\AnswerSheetQuestionType):
     * single_choice: chuỗi 1 ký tự | true_false_group: mảng ['a'=>bool,...] | short_answer:
     * chuỗi số. Với short_answer so sánh bằng (float) để "12" và "12.0" cùng coi là đúng.
     */
    public function isCorrect(mixed $submitted): bool
    {
        if ($submitted === null) {
            return false;
        }

        return match ($this->question_type) {
            AnswerSheetQuestionType::SingleChoice => is_string($submitted)
                && strtoupper(trim($submitted)) === strtoupper(trim((string) $this->correct_answer)),
            AnswerSheetQuestionType::TrueFalseGroup => is_array($submitted)
                && $this->trueFalseGroupMatches($submitted),
            AnswerSheetQuestionType::ShortAnswer => is_numeric($submitted)
                && is_numeric($this->correct_answer)
                && (float) $submitted === (float) $this->correct_answer,
        };
    }

    private function trueFalseGroupMatches(array $submitted): bool
    {
        $expected = (array) $this->correct_answer;

        if (array_keys($submitted) !== array_keys($expected)) {
            return false;
        }

        foreach ($expected as $key => $value) {
            if (($submitted[$key] ?? null) !== $value) {
                return false;
            }
        }

        return true;
    }
}
