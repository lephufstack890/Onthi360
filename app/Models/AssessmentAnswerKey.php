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
            AnswerSheetQuestionType::SingleChoice => self::singleChoiceMatches($submitted, $this->correct_answer),
            // SỬA 9/9 — cả câu chỉ Đúng hoặc Sai; bài nộp đã được PdfAttemptService ép về bool thật.
            AnswerSheetQuestionType::TrueFalse => is_bool($submitted)
                && $submitted === (bool) $this->correct_answer,
            AnswerSheetQuestionType::TrueFalseGroup => is_array($submitted)
                && $this->trueFalseGroupMatches($submitted),
            AnswerSheetQuestionType::ShortAnswer => self::shortAnswerMatches($submitted, $this->correct_answer),
            // SỬA 9/9 — câu nhiều ý: ĐÚNG TOÀN BỘ mới được tính điểm, cùng quy ước với
            // TrueFalseGroup đang dùng (hệ thống chưa có chấm điểm thành phần cho 1 câu).
            AnswerSheetQuestionType::MultiPart => is_array($submitted)
                && $this->multiPartMatches($submitted),
        };
    }

    /** So khớp 1 ý/1 câu dạng trắc nghiệm — không phân biệt hoa thường và khoảng trắng thừa. */
    private static function singleChoiceMatches(mixed $submitted, mixed $expected): bool
    {
        return (is_string($submitted) || is_numeric($submitted))
            && strtoupper(trim((string) $submitted)) === strtoupper(trim((string) $expected));
    }

    /**
     * So khớp 1 ý/1 câu trả lời ngắn — so bằng GIÁ TRỊ SỐ nên "12" và "12.0" cùng đúng.
     *
     * SỬA 16/10 (nhập đề bằng gói ZIP: "accepted_answers": ["8", "8.0"], ["def"]) — đáp án đúng có thể là NHIỀU giá trị (mảng,
     * hoặc chuỗi ngăn bằng " | ") và có thể là CHỮ: cả hai bên là số thì so theo giá trị số (nhận cả dấu phẩy "12,5"), còn lại
     * so chuỗi sau khi bỏ khoảng trắng thừa, không phân biệt hoa/thường. Đáp án cũ (1 số) chấm y như trước.
     */
    private static function shortAnswerMatches(mixed $submitted, mixed $expected): bool
    {
        if (! is_string($submitted) && ! is_numeric($submitted)) {
            return false;
        }

        $given = trim((string) $submitted);
        if ($given === '') {
            return false;
        }

        $options = is_array($expected) ? $expected : preg_split('/\s*\|\s*/u', (string) $expected);
        $givenNumber = str_replace([' ', ','], ['', '.'], $given);

        foreach ((array) $options as $option) {
            if (! is_scalar($option)) {
                continue;
            }
            $option = trim((string) $option);
            if ($option === '') {
                continue;
            }

            $optionNumber = str_replace([' ', ','], ['', '.'], $option);
            if (is_numeric($givenNumber) && is_numeric($optionNumber)) {
                if ((float) $givenNumber === (float) $optionNumber) {
                    return true;
                }

                continue;
            }

            if (mb_strtolower(preg_replace('/\s+/u', ' ', $given)) === mb_strtolower(preg_replace('/\s+/u', ' ', $option))) {
                return true;
            }
        }

        return false;
    }

    /**
     * SỬA 9/9 — câu nhiều ý: mỗi ý có KIỂU riêng lưu ngay trong đáp án đúng
     * ({"a":{"type":"single_choice","value":"A"}, …}), nên phải so từng ý theo đúng kiểu của nó.
     * Thiếu ý nào, thừa ý nào, hay sai 1 ý -> cả câu sai.
     *
     * @param  array<string, mixed>  $submitted  {"a":"A","b":true,"c":"123"}
     */
    private function multiPartMatches(array $submitted): bool
    {
        $expected = (array) $this->correct_answer;

        if ($expected === [] || array_keys($submitted) !== array_keys($expected)) {
            return false;
        }

        foreach ($expected as $part => $spec) {
            $type = is_array($spec) ? ($spec['type'] ?? null) : null;
            $value = is_array($spec) ? ($spec['value'] ?? null) : $spec;
            $given = $submitted[$part] ?? null;

            $ok = match ($type) {
                AnswerSheetQuestionType::SingleChoice->value => self::singleChoiceMatches($given, $value),
                AnswerSheetQuestionType::TrueFalse->value => is_bool($given) && $given === (bool) $value,
                AnswerSheetQuestionType::ShortAnswer->value => self::shortAnswerMatches($given, $value),
                // Kiểu lạ (dữ liệu cũ/hỏng) -> KHÔNG đoán bừa là đúng.
                default => false,
            };

            if (! $ok) {
                return false;
            }
        }

        return true;
    }

    private function trueFalseGroupMatches(array $submitted): bool
    {
        $expected = (array) $this->correct_answer;

        if (array_keys($submitted) !== array_keys($expected)) {
            return false;
        }

        foreach ($expected as $key => $value) {
            // SỬA 9/9 (3) — so sánh sau khi ép về bool ở CẢ HAI phía. Đề lưu TRƯỚC bản sửa lỗi
            // "$row + [...]" ở Controller có correct_answer là chuỗi "1"/"0" chứ không phải
            // true/false; so sánh !== theo kiểu sẽ làm mọi bài làm của những đề cũ đó đều bị chấm
            // sai. Ép bool ở đây chữa luôn dữ liệu cũ mà không cần chạy lệnh sửa CSDL.
            if ((bool) ($submitted[$key] ?? null) !== (bool) $value) {
                return false;
            }
        }

        return true;
    }
}
