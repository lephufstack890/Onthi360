<?php

namespace App\Enums;

/**
 * Dạng câu trong phiếu trả lời của đề PDF (App\Models\AssessmentAnswerKey::question_type).
 *
 * 3 dạng đầu là chốt 16/8 theo định dạng thi THPT quốc gia 2025 (trắc nghiệm 1 đáp án, đúng/sai
 * từng ý, trả lời ngắn bằng số).
 *
 * SỬA 9/9 (khách: "đủ các loại như file mẫu này nè, thiếu thì thêm vô nhé" — file mẫu có thêm
 * dạng "Đúng sai" ghi 1 giá trị T/F và dạng "Câu nhiều ý" ghi "a:A-b:T-c:123") — thêm 2 dạng:
 *   - TrueFalse: cả câu chỉ Đúng hoặc Sai (khác TrueFalseGroup: 4 ý a,b,c,d của CÙNG 1 câu).
 *   - MultiPart: 1 câu gồm nhiều ý, MỖI Ý một kiểu trả lời khác nhau.
 *
 * KHÔNG liên quan tới App\Enums\QuestionType (dạng câu hỏi RỜI trong kho — Coding/Mcq/FillBlank).
 */
enum AnswerSheetQuestionType: string
{
    // correct_answer lưu 1 ký tự: "A"|"B"|"C"|"D"...
    case SingleChoice = 'single_choice';

    // SỬA 9/9 — correct_answer lưu bool: true = Đúng, false = Sai (cả câu, không chia ý).
    case TrueFalse = 'true_false';

    // correct_answer lưu object từng ý nhỏ: {"a": true, "b": false, "c": true, "d": false}
    case TrueFalseGroup = 'true_false_group';

    // correct_answer lưu chuỗi số (giữ string để không mất số 0 ở đầu/số thập phân): "12.5"
    case ShortAnswer = 'short_answer';

    /**
     * SỬA 9/9 — correct_answer lưu từng ý kèm KIỂU của chính ý đó, giữ nguyên thứ tự ý:
     *   {"a": {"type": "single_choice", "value": "A"},
     *    "b": {"type": "true_false",    "value": true},
     *    "c": {"type": "short_answer",  "value": "123"}}
     * Kiểu của mỗi ý chỉ được là 1 trong 3 dạng "đơn" ở trên (không lồng MultiPart trong MultiPart).
     */
    case MultiPart = 'multi_part';

    public function label(): string
    {
        return match ($this) {
            self::SingleChoice => 'Trắc nghiệm (A/B/C/D)',
            self::TrueFalse => 'Đúng/Sai',
            self::TrueFalseGroup => 'Đúng/Sai 4 ý',
            self::ShortAnswer => 'Trả lời ngắn (số)',
            self::MultiPart => 'Câu nhiều ý',
        };
    }

    /** Nhãn ngắn + biểu tượng dùng cho thẻ câu hỏi ở màn soạn đáp án và màn học sinh làm bài. */
    public function icon(): string
    {
        return match ($this) {
            self::SingleChoice => '🔤',
            self::TrueFalse => '⚖️',
            self::TrueFalseGroup => '✅',
            self::ShortAnswer => '✏️',
            self::MultiPart => '🧩',
        };
    }

    /** Màu nhận diện (lớp Tailwind) — dùng chung để 2 màn soạn/làm bài nhìn giống nhau. */
    public function tone(): string
    {
        return match ($this) {
            self::SingleChoice => 'bg-sky-50 text-sky-600 border-sky-200',
            self::TrueFalse => 'bg-amber-50 text-amber-600 border-amber-200',
            self::TrueFalseGroup => 'bg-emerald-50 text-emerald-600 border-emerald-200',
            self::ShortAnswer => 'bg-violet-50 text-violet-600 border-violet-200',
            self::MultiPart => 'bg-rose-50 text-rose-600 border-rose-200',
        };
    }

    /** 3 dạng "đơn" được phép dùng cho TỪNG Ý của MultiPart. */
    public static function simpleCases(): array
    {
        return [self::SingleChoice, self::TrueFalse, self::ShortAnswer];
    }
}
