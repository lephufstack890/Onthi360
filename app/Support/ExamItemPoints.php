<?php

namespace App\Support;

use App\Models\Assessment;
use App\Models\Question;

/**
 * SỬA 3/11 (khách: "phát hành câu trắc nghiệm của đề cứ báo Thiếu đáp án hoặc điểm") — câu nhập từ gói ZIP ĐỀ mà điểm khai
 * nhỏ hơn 1 (vd Phần I đề THPT 0,25 điểm/câu) bị làm tròn thành 0 ở cột questions.points (cột số nguyên), nên khâu phát hành
 * (QuestionPublishGuard: điểm phải > 0) chặn cả loạt. Điểm THẬT của câu trong đề là assessment_items.points_override (giữ
 * nguyên số lẻ, máy chấm dùng số này) — nên chỉ cần đặt điểm gốc = 1 cho các câu nằm trong đề có điểm > 0 mà điểm gốc đang 0.
 * Không đụng câu lẻ ngoài đề, không đổi điểm trong đề.
 */
final class ExamItemPoints
{
    /** Sửa cho mọi câu của 1 đề. Trả về số câu đã sửa. */
    public static function fixForAssessment(Assessment $assessment): int
    {
        $ids = $assessment->items()->where('points_override', '>', 0)->pluck('question_id')->all();

        return $ids === [] ? 0 : Question::query()->whereIn('id', $ids)->where('points', '<', 1)->update(['points' => 1]);
    }

    /** Sửa cho 1 câu (nút "Phát hành" từng câu). */
    public static function fixForQuestion(Question $question): void
    {
        if ((float) $question->points >= 1) {
            return;
        }
        if ($question->assessmentItems()->where('points_override', '>', 0)->exists()) {
            $question->forceFill(['points' => 1])->save();
        }
    }
}
