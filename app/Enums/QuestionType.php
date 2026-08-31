<?php

namespace App\Enums;

enum QuestionType: string
{
    case Coding = 'coding';
    case Mcq = 'mcq';
    case FillBlank = 'fill_blank';
    // SỬA 31/8 (2, "mở rộng ZIP bài tập" nhiều dạng câu/nhiều môn) — câu hỏi NHIỀU PHẦN, mỗi
    // phần 1 dạng con khác nhau (vd Đọc hiểu Ngữ văn: phần a trắc nghiệm, phần b đúng/sai,
    // phần c trả lời ngắn, phần d tự luận) — xem grading_config['parts'] và
    // App\Services\Admin\ContentService::buildCompositeGradingConfigFromZip(). CHỈ tạo được
    // qua nhập ZIP OT360-QPACK (content.type = "composite"), không có form nhập tay tương ứng.
    case Composite = 'composite';
}
