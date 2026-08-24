<?php

namespace App\Repositories\Eloquent;

use App\Models\Question;
use App\Repositories\Contracts\QuestionRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class QuestionRepository extends EloquentRepository implements QuestionRepositoryInterface
{
    protected string $modelClass = Question::class;

    public function byOwner(int $ownerId, ?string $status = null, int $limit = 50): Collection
    {
        $query = $this->query()->where('owner_id', $ownerId);

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->latest()->limit($limit)->get();
    }

    public function countByOwner(int $ownerId, ?string $status = null): int
    {
        $query = $this->query()->where('owner_id', $ownerId);

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->count();
    }

    public function sharedLatestWithOwner(int $limit = 50): Collection
    {
        return $this->query()->with('owner')->where('owner_type', 'shared')->latest()->limit($limit)->get();
    }

    public function countShared(): int
    {
        return $this->query()->where('owner_type', 'shared')->count();
    }

    /** Admin xem được toàn bộ câu hỏi (Kho chung + kho riêng từng giáo viên) — không lọc owner_type. */
    public function allLatestWithOwner(int $limit = 50): Collection
    {
        return $this->query()->with('owner')->latest()->limit($limit)->get();
    }

    /**
     * SỬA 24/8 (v2) — khách chốt: "Luyện tập theo câu" dùng CẢ câu hỏi thuộc kho riêng giáo
     * viên, không chỉ Kho chung nữa — bỏ hẳn điều kiện where('owner_type', 'shared'). "Đã
     * phát hành" (status=published) vẫn là điều kiện chặn duy nhất còn lại — giáo viên phải tự
     * bấm Phát hành ở câu đó thì câu mới vào được vòng luyện công khai này, câu Nháp không bao
     * giờ lọt ra dù thuộc kho ai. Cố ý KHÔNG lọc theo cột 'visibility' (câu giáo viên tạo mặc
     * định visibility=private, xem Teacher\QuestionService::store() — lọc thêm điều kiện đó sẽ
     * loại sạch mọi câu giáo viên, ngược lại đúng yêu cầu khách vừa chốt).
     * SỬA 24/8 (v4) — khách chốt: thêm dạng 'coding' vào luôn — hệ thống vẫn CHƯA có sandbox
     * chấm code thật, nên Student\PracticeByQuestionService::answer() không tự chấm đúng/sai
     * cho câu Lập trình (chỉ ghi nhận bài làm, giống quy ước "Queued" của AttemptService) —
     * đây chỉ là nơi LẤY câu, không phải nơi quyết định có chấm được hay không.
     */
    public function idsForPractice(?string $type, array $tagIds): array
    {
        return $this->query()
            ->where('status', 'published')
            ->whereIn('type', ['mcq', 'fill_blank', 'coding'])
            ->when($type, fn (Builder $q) => $q->where('type', $type))
            ->when($tagIds !== [], fn (Builder $q) => $q->whereHas('tags', fn (Builder $qq) => $qq->whereIn('tags.id', $tagIds)))
            ->pluck('id')
            ->all();
    }
}
