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

    /**
     * SỬA 8/9 (4) (khách: "bên giáo viên màn Kho câu hỏi, cột Loại đổ ra tiếng Việt cho người
     * dùng dễ hiểu") — trước đây Teacher\QuestionService::mapQuestionRow() đổ thẳng
     * $q->type->value ra bảng nên người dùng thấy "mcq"/"fill_blank"/"coding". Đặt nhãn ngay ở
     * enum để mọi nơi cần hiển thị dùng chung 1 nguồn, không ai phải tự viết lại bảng ánh xạ.
     * (Admin\ContentService::QUESTION_TYPE_LABELS giữ nguyên: hằng đó còn dùng làm DANH SÁCH
     * value => label để dựng dropdown/bộ lọc, không chỉ để hiển thị 1 giá trị.)
     */
    public function label(): string
    {
        return match ($this) {
            self::Coding => 'Lập trình (OJ)',
            self::Mcq => 'Trắc nghiệm',
            self::FillBlank => 'Điền khuyết',
            self::Composite => 'Nhiều phần (composite)',
        };
    }
}
