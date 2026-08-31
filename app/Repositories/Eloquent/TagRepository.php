<?php

namespace App\Repositories\Eloquent;

use App\Models\Tag;
use App\Repositories\Contracts\TagRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class TagRepository extends EloquentRepository implements TagRepositoryInterface
{
    protected string $modelClass = Tag::class;

    public function allOrderedByName(): Collection
    {
        return $this->query()->orderBy('name')->get();
    }

    /**
     * SỬA 24/8 — xem docblock ở TagRepositoryInterface::allWithPracticeQuestions(). Cùng điều
     * kiện với QuestionRepository::idsForPractice(null, []) (đã phát hành, type mcq/fill_blank/
     * coding) nhưng lọc theo phía Tag qua whereHas('questions', ...) — KHÔNG lọc theo
     * $type/$tagIds cụ thể ở đây vì đây là danh sách "còn chọn được" hiển thị SẴN cho người
     * dùng trước khi họ bấm lọc, không phải kết quả của 1 lượt lọc.
     * SỬA 24/8 (v2) — khách chốt: bỏ điều kiện owner_type='shared' — câu hỏi giáo viên đã phát
     * hành cũng tính, không chỉ Kho chung (khớp QuestionRepository::idsForPractice() bản mới).
     * SỬA 24/8 (v4) — khách chốt: thêm dạng 'coding' vào luôn, khớp idsForPractice() bản mới.
     */
    public function allWithPracticeQuestions(): Collection
    {
        return $this->query()
            ->whereHas('questions', function ($q) {
                // SỬA 31/8 (2) — thêm 'composite', khớp QuestionRepository::idsForPractice() bản mới.
                $q->where('status', 'published')
                    ->whereIn('type', ['mcq', 'fill_blank', 'coding', 'composite']);
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * firstOrCreate() dựa vào collation mặc định của cột 'name' (utf8mb4_unicode_ci — không
     * phân biệt hoa/thường) để tự khớp "đại số" với "Đại Số" đã có sẵn, tránh tự sinh thêm
     * tag gần trùng chỉ vì khác cách viết hoa — KHÔNG tự viết thêm điều kiện LOWER() ở đây.
     */
    public function findOrCreateByName(string $name): Tag
    {
        return Tag::firstOrCreate(['name' => trim($name)]);
    }
}
