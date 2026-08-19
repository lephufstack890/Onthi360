<?php

namespace App\Services;

use App\Models\Assessment;
use App\Support\AccessDecision;

/**
 * SỬA 18/8 (đề PDF + phiếu đáp án): điều kiện phát hành cho Assessment có content_mode =
 * pdf_answer_sheet — cùng vai trò với App\Services\QuestionPublishGuard (một cổng kiểm tra
 * duy nhất, không rải rác logic ở nhiều nơi), nhưng khác điều kiện vì đối tượng khác hẳn
 * (đề PDF nguyên khối + phiếu đáp án, không phải câu hỏi rời có grading_config). Theo yêu
 * cầu khách (16/8 mục 5): "Chỉ đề đã đủ dữ liệu bắt buộc... mới hiện cho học sinh hoặc được
 * dùng trong lớp/Kỳ thi/Cuộc thi; đề chưa hoàn thiện không bị lộ ra ngoài."
 */
class PdfAssessmentPublishGuard
{
    public function canPublish(Assessment $assessment): AccessDecision
    {
        if (blank($assessment->pdf_path)) {
            return AccessDecision::deny('missing_pdf', 'Chưa tải file PDF của đề.');
        }

        $answerKeyCount = $assessment->relationLoaded('answerKeys')
            ? $assessment->answerKeys->count()
            : $assessment->answerKeys()->count();
        $codingItems = $assessment->relationLoaded('codingItems')
            ? $assessment->codingItems
            : $assessment->codingItems()->with('testCases')->get();

        if ($answerKeyCount === 0 && $codingItems->isEmpty()) {
            return AccessDecision::deny(
                'missing_answer_data',
                'Đề chưa có câu nào — cần nhập đáp án trắc nghiệm/đúng-sai/trả lời ngắn hoặc thêm ít nhất 1 bài lập trình.',
            );
        }

        $codingItemsWithoutTestCase = $codingItems->filter(fn ($item) => $item->testCases()->count() === 0);
        if ($codingItemsWithoutTestCase->isNotEmpty()) {
            $names = $codingItemsWithoutTestCase->pluck('code')->implode(', ');

            return AccessDecision::deny(
                'missing_test_cases',
                "Bài lập trình còn thiếu test case: {$names}.",
            );
        }

        return AccessDecision::allow();
    }
}
