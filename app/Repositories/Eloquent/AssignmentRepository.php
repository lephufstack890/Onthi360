<?php

namespace App\Repositories\Eloquent;

use App\Models\Assignment;
use App\Repositories\Contracts\AssignmentRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class AssignmentRepository extends EloquentRepository implements AssignmentRepositoryInterface
{
    protected string $modelClass = Assignment::class;

    public function forClassRoomIds(array $classRoomIds, ?string $status = null, int $limit = 30): Collection
    {
        $query = $this->query()->whereIn('class_room_id', $classRoomIds);

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->with(['assessment', 'classRoom.course'])->latest('opens_at')->limit($limit)->get();
    }

    public function countForClassRoomIds(array $classRoomIds, ?string $status = null): int
    {
        $query = $this->query()->whereIn('class_room_id', $classRoomIds);

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->count();
    }

    public function forClassRoomWithAssessment(int $classRoomId): Collection
    {
        return $this->query()
            ->where('class_room_id', $classRoomId)
            ->with('assessment')
            ->latest('opens_at')
            ->get();
    }

    public function draftOrScheduledForClassRoomIds(array $classRoomIds, int $limit = 10): Collection
    {
        return $this->query()
            ->whereIn('class_room_id', $classRoomIds)
            ->whereIn('status', ['draft', 'scheduled'])
            ->with(['assessment', 'classRoom'])
            ->limit($limit)
            ->get();
    }

    /**
     * KHÔNG lọc theo cột status (status chỉ được tính 1 LẦN lúc tạo assignment —
     * AssessmentService::computeAssignmentStatus() — và không tự cập nhật lại theo thời
     * gian sau đó, nên 1 bài "scheduled" mà đã qua giờ mở vẫn còn nằm ở status cũ). Tính
     * trực tiếp theo opens_at để không phụ thuộc giá trị status có thể đã lỗi thời.
     */
    public function assignedForClassRoomIds(array $classRoomIds): Collection
    {
        return $this->query()
            ->whereIn('class_room_id', $classRoomIds)
            ->where('status', '!=', 'draft')
            ->where(function ($q) {
                $q->whereNull('opens_at')->orWhere('opens_at', '<=', now());
            })
            ->get(['id', 'class_room_id']);
    }
}
