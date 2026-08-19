<?php

namespace App\Enums;

/**
 * Dạng câu trong phiếu trả lời của đề PDF (App\Models\AssessmentAnswerKey::question_type) —
 * đúng 3 dạng khách chốt (16/8), khớp định dạng thi THPT quốc gia 2025:
 * trắc nghiệm 1 đáp án, đúng/sai từng ý nhỏ, trả lời ngắn bằng số. KHÔNG liên quan tới
 * App\Enums\QuestionType (đó là dạng câu hỏi RỜI trong kho — Coding/Mcq/FillBlank).
 */
enum AnswerSheetQuestionType: string
{
    // correct_answer lưu 1 ký tự: "A"|"B"|"C"|"D"...
    case SingleChoice = 'single_choice';

    // correct_answer lưu object từng ý nhỏ: {"a": true, "b": false, "c": true, "d": false}
    case TrueFalseGroup = 'true_false_group';

    // correct_answer lưu chuỗi số (giữ string để không mất số 0 ở đầu/số thập phân): "12.5"
    case ShortAnswer = 'short_answer';

    public function label(): string
    {
        return match ($this) {
            self::SingleChoice => 'Trắc nghiệm 1 đáp án (A/B/C/D)',
            self::TrueFalseGroup => 'Đúng/Sai từng ý',
            self::ShortAnswer => 'Trả lời ngắn (số)',
        };
    }
}
