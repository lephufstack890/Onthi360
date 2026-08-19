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

    public static function isFillBlankCorrect(Question $question, string $text): bool
    {
        $config = $question->grading_config ?? [];
        $normalized = mb_strtolower(trim($text));
        $accepted = array_map(fn ($a) => mb_strtolower(trim((string) $a)), $config['accepted_answers'] ?? []);

        return $normalized !== '' && in_array($normalized, $accepted, true);
    }
}
