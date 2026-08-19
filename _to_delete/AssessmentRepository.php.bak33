<?php

namespace App\Repositories\Eloquent;

use App\Models\Assessment;
use App\Repositories\Contracts\AssessmentRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class AssessmentRepository extends EloquentRepository implements AssessmentRepositoryInterface
{
    protected string $modelClass = Assessment::class;

    /**
     * SỬA 18/8 (luồng Luyện tập, tab "Tự luyện"): thêm lọc theo $questionType/$bankName +
     * eager-load items.question.bank — trước đây chỉ lọc type=practice/status=published,
     * không cách nào biết đề có câu hỏi dạng gì (Lập trình/Trắc nghiệm/Điền đáp án) hay
     * thuộc "chuyên đề" nào (tạm dùng App\Models\QuestionBank::name vì chưa có bảng Tag
     * riêng — xem PracticeService) mà không load thêm items.question.bank ở đây.
     */
    public function publishedPractice(int $limit = 30, ?string $questionType = null, ?string $bankName = null): Collection
    {
        return $this->query()
            ->where('type', 'practice')
            ->where('status', 'published')
            ->when($questionType, fn (Builder $q) => $q->whereHas('items.question', fn (Builder $qq) => $qq->where('type', $questionType)))
            ->when($bankName, fn (Builder $q) => $q->whereHas('items.question.bank', fn (Builder $qq) => $qq->where('name', $bankName)))
            ->with('items.question.bank')
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function countPublishedPractice(?string $questionType = null, ?string $bankName = null): int
    {
        return $this->query()
            ->where('type', 'practice')
            ->where('status', 'published')
            ->when($questionType, fn (Builder $q) => $q->whereHas('items.question', fn (Builder $qq) => $qq->where('type', $questionType)))
            ->when($bankName, fn (Builder $q) => $q->whereHas('items.question.bank', fn (Builder $qq) => $qq->where('name', $bankName)))
            ->count();
    }

    public function withItemsAndQuestions(int $id): ?Assessment
    {
        return $this->query()->with('items.question')->find($id);
    }

    public function latestWithCreator(int $limit = 50): Collection
    {
        return $this->query()->with('creator')->latest()->limit($limit)->get();
    }

    public function byOwner(int $ownerId, int $limit = 50): Collection
    {
        return $this->query()
            ->where('owner_type', 'teacher')
            ->where('owner_id', $ownerId)
            ->withCount(['items', 'assignments'])
            ->latest()
            ->limit($limit)
            ->get();
    }
}
