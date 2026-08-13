<?php

namespace App\Repositories\Eloquent;

use App\Models\ClassSession;
use App\Repositories\Contracts\ClassSessionRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ClassSessionRepository extends EloquentRepository implements ClassSessionRepositoryInterface
{
    protected string $modelClass = ClassSession::class;

    public function nextUpcomingForClassRoom(int $classRoomId): ?ClassSession
    {
        return $this->query()
            ->where('class_room_id', $classRoomId)
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->first();
    }

    public function allForClassRoom(int $classRoomId): Collection
    {
        return $this->query()->where('class_room_id', $classRoomId)->with('attendances')->orderBy('starts_at')->get();
    }

    public function countPastForClassRoom(int $classRoomId): int
    {
        return $this->query()
            ->where('class_room_id', $classRoomId)
            ->where('starts_at', '<', now())
            ->count();
    }

    public function upcomingForClassRoomIds(array $classRoomIds, int $limit = 5): Collection
    {
        return $this->query()
            ->whereIn('class_room_id', $classRoomIds)
            ->where('starts_at', '>=', now())
            ->with('classRoom')
            ->orderBy('starts_at')
            ->limit($limit)
            ->get();
    }

    /** teacher.schedule.index — mọi buổi học (quá khứ + sắp tới) xuyên các lớp giáo viên phụ trách. */
    public function allForClassRoomIds(array $classRoomIds): Collection
    {
        return $this->query()
            ->whereIn('class_room_id', $classRoomIds)
            ->with(['classRoom', 'attendances'])
            ->orderByDesc('starts_at')
            ->get();
    }

    /**
     * teacher.classes.index — buổi học GẦN NHẤT đã qua của mỗi lớp (để báo "chưa điểm
     * danh" khi buổi vừa kết thúc mà giáo viên chưa vào điểm danh). Lấy dư $limit dòng rồi
     * groupBy ở Service (cùng kiểu batch-fetch với upcomingForClassRoomIds) để tránh N+1.
     */
    public function mostRecentPastForClassRoomIds(array $classRoomIds, int $limit = 5): Collection
    {
        return $this->query()
            ->whereIn('class_room_id', $classRoomIds)
            ->where('starts_at', '<', now())
            ->with(['classRoom', 'attendances'])
            ->orderByDesc('starts_at')
            ->limit($limit)
            ->get();
    }
}
