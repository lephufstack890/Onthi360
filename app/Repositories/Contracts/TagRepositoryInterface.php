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

    /**
     * SỬA 9/9 (6) (khách: "khi lọc dạng câu hỏi thì nó sẽ hiển thị chuyên đề thuộc dạng đó cho
     * đúng") — số câu LUYỆN ĐƯỢC của từng chuyên đề, TÁCH THEO TỪNG DẠNG câu hỏi. Màn chọn lọc
     * dùng số này để: (1) chỉ hiện chuyên đề thật sự có câu ở dạng đang chọn, (2) hiện luôn số
     * câu để học sinh biết chọn cái nào có bài mà làm.
     *
     * Điều kiện đếm phải TRÙNG KHỚP QuestionRepositoryInterface::idsForPractice() — lệch một
     * điều kiện là giao diện mời chọn xong bấm vào lại báo "không tìm thấy câu hỏi".
     *
     * @return array<int, array{name: string, counts: array<string, int>, total: int}> keyed theo tag id
     */
    public function practiceCountsByType(): array;

    /** SỬA 9/9 (6) — tổng số câu luyện được theo từng dạng (tính cả câu KHÔNG gắn chuyên đề nào). */
    public function practiceTotalsByType(): array;

    /** Trả về Tag có sẵn (khớp tên, không phân biệt hoa/thường) hoặc tạo mới nếu chưa có. */
    public function findOrCreateByName(string $name): Tag;
}
