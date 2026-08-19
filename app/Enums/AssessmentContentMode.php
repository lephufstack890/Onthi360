<?php

namespace App\Enums;

/**
 * SỬA 18/8 (đề PDF + phiếu đáp án): cách 1 Assessment chứa nội dung — độc lập với
 * App\Enums\AssessmentType (loại đề: practice/assignment/exam/competition_paper). 2 enum
 * này vuông góc nhau: content_mode trả lời "nội dung đề nằm ở đâu/chấm kiểu gì", còn type
 * trả lời "đề dùng để làm gì". Theo yêu cầu khách (16/8, "chốt chức năng đề luyện tập tài
 * liệu"): chỉ Luyện tập theo câu mới dùng Structured; Bài giao/Đề thi/Đề thi đấu và Luyện
 * tập theo đề đều dùng PdfAnswerSheet.
 */
enum AssessmentContentMode: string
{
    // Câu hỏi rời lấy từ kho (App\Models\AssessmentItem -> Question) — cách làm cũ, vẫn
    // dùng cho Luyện tập theo câu.
    case Structured = 'structured';

    // Đề nguyên file PDF + phiếu đáp án (App\Models\AssessmentAnswerKey) và/hoặc bài lập
    // trình con (App\Models\AssessmentCodingItem) — cách làm mới cho "đề thi lẻ".
    case PdfAnswerSheet = 'pdf_answer_sheet';

    public function label(): string
    {
        return match ($this) {
            self::Structured => 'Câu hỏi rời (kho câu hỏi)',
            self::PdfAnswerSheet => 'PDF + phiếu đáp án',
        };
    }
}
