<?php

namespace App\Services;

use App\Models\Question;

/**
 * SỬA 19/8 (Giai đoạn 6 — "Luyện tập theo câu"): tách logic so khớp đáp án Trắc nghiệm/Điền
 * đáp án ra 1 nơi DUY NHẤT, dùng chung cho cả:
 * - App\Services\AttemptService::gradeMcq()/gradeFillBlank() (chấm bài làm chính thức, có
 *   Attempt/AttemptAnswer lưu DB).
 * - App\Services\Student\PracticeByQuestionService (luyện từng câu ngoài đề, KHÔNG lưu DB).
 *
 * Lý do tách: chính bug thật đã sửa cùng lúc với Giai đoạn 6 (Teacher\QuestionService lưu
 * correct_options dạng chữ cái thay vì chỉ số, khiến AttemptService::gradeMcq() chấm sai hàng
 * loạt) cho thấy chỉ cần 1 nơi hiểu sai định dạng dữ liệu là chấm sai lan rộng — có bao nhiêu
 * nơi so khớp đáp án thì có bấy nhiêu nguy cơ lệch nhau. Từ Giai đoạn 6 trở đi, MỌI nơi so
 * khớp đáp án Mcq/FillBlank phải gọi qua đây, không tự viết lại.
 */
class QuestionGrader
{
    /**
     * $selectedOption là chỉ số phương án (0-3, khớp thứ tự mảng grading_config['options']) —
     * KHÔNG phải chữ cái A/B/C/D (xem SỬA 19/8 ở Teacher\QuestionService::buildGradingConfig()
     * để biết vì sao chữ cái là sai).
     */
    public static function isMcqCorrect(Question $question, mixed $selectedOption): bool
    {
        $config = $question->grading_config ?? [];
        $correctOptions = array_map('intval', $config['correct_options'] ?? []);

        return $selectedOption !== null && $selectedOption !== ''
            && in_array((int) $selectedOption, $correctOptions, true);
    }

    /**
     * SỬA 31/8 (2, "mở rộng ZIP bài tập") — VIẾT LẠI: trước đây LUÔN so khớp không phân biệt
     * hoa/thường (mb_strtolower cứng), ÂM THẦM BỎ QUA cờ grading_config['case_sensitive'] dù
     * cờ này đã tồn tại sẵn từ trước (Admin\ContentService::buildGradingConfig() 'fill_blank'
     * đã lưu đúng cờ này, chỉ có nơi CHẤM không đọc tới) — nay chuyển qua
     * matchesAcceptedAnswers() dùng chung, honor đúng case_sensitive đã lưu.
     */
    public static function isFillBlankCorrect(Question $question, string $text): bool
    {
        $config = $question->grading_config ?? [];

        return self::matchesAcceptedAnswers($text, $config['accepted_answers'] ?? [], [
            'trim' => true,
            'case_sensitive' => (bool) ($config['case_sensitive'] ?? false),
            'remove_diacritics' => (bool) ($config['remove_diacritics'] ?? false),
        ]);
    }

    /**
     * SỬA 31/8 (2) — so khớp đáp án dạng "điền khuyết"/"trả lời ngắn" TỔNG QUÁT, tách khỏi
     * isFillBlankCorrect() để dùng chung cho cả:
     * - Question type FillBlank (top-level, $normalization chỉ có case_sensitive — xem trên).
     * - Phần "short_answer" trong câu Composite (có thêm remove_diacritics, đúng gói ZIP mẫu
     *   Ngữ văn "NGU_VAN8DOC_HIEU_001" — normalization: {trim, case_sensitive: false,
     *   remove_diacritics: true}, xem Student\PracticeByQuestionService::gradeCompositeParts()).
     *
     * @param  array{trim?:bool, case_sensitive?:bool, remove_diacritics?:bool}  $normalization
     *         Mặc định (thiếu key nào thì dùng mặc định đó): trim=true, case_sensitive=false,
     *         remove_diacritics=false.
     */
    public static function matchesAcceptedAnswers(string $text, array $acceptedAnswers, array $normalization = []): bool
    {
        $trim = (bool) ($normalization['trim'] ?? true);
        $caseSensitive = (bool) ($normalization['case_sensitive'] ?? false);
        $removeDiacritics = (bool) ($normalization['remove_diacritics'] ?? false);

        $normalizedInput = self::normalizeAnswerText($text, $trim, $caseSensitive, $removeDiacritics);
        if ($normalizedInput === '') {
            return false;
        }

        foreach ($acceptedAnswers as $accepted) {
            if ($normalizedInput === self::normalizeAnswerText((string) $accepted, $trim, $caseSensitive, $removeDiacritics)) {
                return true;
            }
        }

        return false;
    }

    /**
     * SỬA 31/8 (2) — so khớp phần "single_choice" trong câu Composite: $correctAnswer là 1
     * chữ cái (vd "B", khớp thẳng phần tử trong grading['parts'][]['choices'] — mảng chữ cái
     * bare, KHÁC cấu trúc {id,text} của Mcq top-level, xem ContentService::
     * buildCompositePartConfig()) — so sánh chuỗi trực tiếp, không cần map qua chỉ số như Mcq.
     */
    public static function isChoiceCorrect(mixed $selected, mixed $correctAnswer): bool
    {
        return $selected !== null && $selected !== '' && (string) $selected === (string) $correctAnswer;
    }

    /**
     * SỬA 31/8 (2) — so khớp phần "true_false" trong câu Composite: $correctAnswer là bool
     * (đọc thẳng từ question.json). $selected có thể tới từ input dạng chuỗi "true"/"false"
     * (radio button ở blade) — dùng filter_var(FILTER_VALIDATE_BOOLEAN) để chấp nhận cả
     * "true"/"false"/"1"/"0" thay vì so sánh chuỗi cứng.
     */
    public static function isTrueFalseCorrect(mixed $selected, bool $correctAnswer): bool
    {
        if ($selected === null || $selected === '') {
            return false;
        }

        $normalized = filter_var($selected, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $normalized !== null && $normalized === $correctAnswer;
    }

    private static function normalizeAnswerText(string $text, bool $trim, bool $caseSensitive, bool $removeDiacritics): string
    {
        if ($trim) {
            $text = trim($text);
        }

        if ($removeDiacritics) {
            $text = self::removeDiacritics($text);
        }

        if (! $caseSensitive) {
            $text = mb_strtolower($text);
        }

        return $text;
    }

    /**
     * Bỏ dấu tiếng Việt bằng bảng chuyển ký tự tường minh (KHÔNG dùng iconv('...//TRANSLIT')
     * — hành vi phụ thuộc locale hệ thống, không đáng tin cậy để chấm điểm) — đủ dùng cho yêu
     * cầu "trả lời ngắn không phân biệt dấu" của gói ZIP mẫu Ngữ văn (remove_diacritics: true).
     */
    private static function removeDiacritics(string $text): string
    {
        $map = [
            '/[àáạảãâầấậẩẫăằắặẳẵ]/u' => 'a', '/[ÀÁẠẢÃÂẦẤẬẨẪĂẰẮẶẲẴ]/u' => 'A',
            '/[èéẹẻẽêềếệểễ]/u' => 'e', '/[ÈÉẸẺẼÊỀẾỆỂỄ]/u' => 'E',
            '/[ìíịỉĩ]/u' => 'i', '/[ÌÍỊỈĨ]/u' => 'I',
            '/[òóọỏõôồốộổỗơờớợởỡ]/u' => 'o', '/[ÒÓỌỎÕÔỒỐỘỔỖƠỜỚỢỞỠ]/u' => 'O',
            '/[ùúụủũưừứựửữ]/u' => 'u', '/[ÙÚỤỦŨƯỪỨỰỬỮ]/u' => 'U',
            '/[ỳýỵỷỹ]/u' => 'y', '/[ỲÝỴỶỸ]/u' => 'Y',
            '/đ/u' => 'd', '/Đ/u' => 'D',
        ];

        return preg_replace(array_keys($map), array_values($map), $text);
    }
}
