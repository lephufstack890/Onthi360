<?php

namespace App\Repositories\Contracts;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Collection;

interface TagRepositoryInterface extends BaseRepositoryInterface
{
    public function allOrderedByName(): Collection;

    /**
     * SỬA 24/8 — chỉ những chuyên đề có ÍT NHẤT 1 câu hỏi thoả điều kiện
     * QuestionRepositoryInterface::idsForPractice() (đã phát hành, dạng Trắc nghiệm/Điền đáp
     * án — SỬA 24/8 v2: không còn giới hạn Kho chung, câu giáo viên đã phát hành cũng tính).
     * allOrderedByName() trả về TOÀN BỘ tag trong hệ thống — kể cả tag chỉ gắn cho câu Lập
     * trình hoặc câu chưa phát hành — chọn đúng tag đó ở màn "Luyện tập theo câu" sẽ luôn ra 0
     * câu dù tag "có dữ liệu" (chỉ là dữ liệu không đủ điều kiện luyện). Dùng phương thức này ở
     * màn chọn lọc để không mời chọn chuyên đề chắc chắn rỗng.
     */
    public function allWithPracticeQuestions(): Collection;

    /** Trả về Tag có sẵn (khớp tên, không phân biệt hoa/thường) hoặc tạo mới nếu chưa có. */
    public function findOrCreateByName(string $name): Tag;
}
