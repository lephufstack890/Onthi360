<?php

namespace App\Repositories\Contracts;

use App\Models\Question;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface QuestionRepositoryInterface extends BaseRepositoryInterface
{

    public function byOwner(int $ownerId, ?string $status = null, int $limit = 50): Collection;

    public function countByOwner(int $ownerId, ?string $status = null): int;

    public function sharedLatestWithOwner(int $limit = 50): Collection;

    public function countShared(): int;

    public function allLatestWithOwner(int $limit = 50): Collection;

    /**
     * "Luyện tập theo câu" (Giai đoạn 6) — chỉ lấy ID câu hỏi ĐÃ PHÁT HÀNH + dạng Trắc nghiệm/
     * Điền đáp án (không có sandbox chấm Lập trình cho luồng luyện ngoài đề này), lọc thêm theo
     * $tagIds (rỗng = không lọc) và $type (null = cả 2 dạng). SỬA 24/8 (v2) — khách chốt: dùng
     * CẢ câu Kho chung VÀ câu thuộc kho riêng giáo viên (không lọc owner_type nữa), chỉ cần đã
     * phát hành. Chỉ trả ID — Student\PracticeByQuestionService tự fetch từng câu khi cần,
     * tránh tải hết nội dung câu hỏi vào session.
     */
    public function idsForPractice(?string $type, array $tagIds): array;
}
