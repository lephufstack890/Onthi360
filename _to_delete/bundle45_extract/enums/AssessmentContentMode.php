<?php

namespace App\Enums;

/**
 * SỬA 18/8 (đề PDF + phiếu đáp án): cách 1 Assessment chứa nội dung — độc lập với
 * App\Enums\AssessmentType (loại đề: practice/assignment/exam/competition_paper). 2 enum
 * này vuông góc nhau: content_mode trả lời "nội dung đề nằm ở đâu/chấm kiểu gì", còn type
 * trả lời "đề dùng để làm gì". Theo yêu cầu khách (16/8, "chốt chức năng đề luyện tập tài
 * liệu"): chỉ Luyện tập theo câu mới dùng Structured; Bài giao/Đề thi/Đề thi đấu và Luyện
 * tập theo đề đều dùng PdfAnswerSheet. Quy tắc này giữ nguyên, KHÔNG áp dụng cho case
 * Programming thêm ở dưới (xem docblock ngay tại case đó).
 *
 * SỬA 24/8 — thêm case Programming: đề toàn bộ là các bài lập trình (không phải PDF, không
 * phải câu hỏi rời trộn nhiều dạng) — xem docblock tại case Programming để biết rõ phạm vi
 * và những gì CHƯA làm.
 */
enum AssessmentContentMode: string
{
    // Câu hỏi rời lấy từ kho (App\Models\AssessmentItem -> Question) — cách làm cũ, vẫn
    // dùng cho Luyện tập theo câu.
    case Structured = 'structured';

    // Đề nguyên file PDF + phiếu đáp án (App\Models\AssessmentAnswerKey) và/hoặc bài lập
    // trình con (App\Models\AssessmentCodingItem) — cách làm mới cho "đề thi lẻ".
    case PdfAnswerSheet = 'pdf_answer_sheet';

    /**
     * THÊM 24/8 (nền tảng cho phần "đề lập trình", theo hướng đã chốt với khách: chuyển bài
     * lập trình sang Kho câu hỏi — App\Enums\QuestionType::Coding — nhập qua gói ZIP chuẩn
     * OT360-QPACK, thay cho "Bài lập trình con" nhúng trong PDF đã tạm ẩn). Khác với
     * Structured/PdfAnswerSheet, case này KHÔNG bị khoá theo AssessmentType — dùng được cho
     * cả Luyện tập/Bài tập/Đề thi/Đề thi đấu, vì 1 đề lập trình có thể đóng vai trò bất kỳ
     * loại nào trong số đó (khác PdfAnswerSheet đang bị ràng buộc cứng theo type ở
     * App\Services\Admin\ContentService::contentModeForType()).
     *
     * QUAN TRỌNG — đây CHỈ MỚI là khai báo enum (nền tảng), CHƯA có gì dùng được giá trị này:
     * chưa có nơi nào trong UI tạo được 1 Assessment với content_mode = Programming (
     * ContentService::contentModeForType() hiện chỉ trả về Structured/PdfAnswerSheet), và
     * cũng chưa có màn hình quản trị/làm bài riêng cho chế độ này. Cố tình để vậy — phần UI
     * tạo đề + màn làm bài (kiểu online judge) là 1 việc lớn riêng, cần bàn kỹ luồng trước khi
     * code, tránh làm ẩu rồi phải sửa lại. Khi build tiếp phần đó, nhớ rà lại 2 chỗ đã biết sẽ
     * bị ảnh hưởng (không vỡ, nhưng sẽ hiện sai nếu không cập nhật):
     *   - App\Models\Assessment::isPdfMode() — trả về false cho Programming, đúng ý.
     *   - resources/views/admin/content/show.blade.php (chuỗi @if/@elseif chọn view hiển thị)
     *     — 1 đề Programming hiện sẽ rơi vào nhánh "structured" chung (dòng ~137), hiển thị
     *     sai (cố render $model->items) — cần thêm 1 nhánh riêng khi có view thật cho chế độ này.
     */
    case Programming = 'programming';

    public function label(): string
    {
        return match ($this) {
            self::Structured => 'Câu hỏi rời (kho câu hỏi)',
            self::PdfAnswerSheet => 'PDF + phiếu đáp án',
            self::Programming => 'Lập trình (online judge)',
        };
    }
}
