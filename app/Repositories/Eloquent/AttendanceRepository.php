<?php

namespace App\Repositories\Eloquent;

use App\Models\Attendance;
use App\Repositories\Contracts\AttendanceRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class AttendanceRepository extends EloquentRepository implements AttendanceRepositoryInterface
{
    protected string $modelClass = Attendance::class;

    public function countPresentForStudentInClassRoom(int $studentId, int $classRoomId): int
    {
        return $this->query()
            ->where('student_id', $studentId)
            ->whereIn('status', ['present', 'late'])
            ->whereHas('classSession', fn (Builder $q) => $q->where('class_room_id', $classRoomId))
            ->count();
    }

    public function forStudentInClassRoom(int $studentId, int $classRoomId, int $limit = 20): Collection
    {
        return $this->query()
            ->where('student_id', $studentId)
            ->whereHas('classSession', fn (Builder $q) => $q->where('class_room_id', $classRoomId))
            ->with('classSession')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function forClassSession(int $classSessionId): Collection
    {
        return $this->query()->where('class_session_id', $classSessionId)->get()->keyBy('student_id');
    }

    /**
     * student.schedule.index — điểm danh của 1 học sinh cho đúng tập buổi học đang hiển thị
     * trong tuần (1 câu whereIn thay vì gọi forStudentInClassRoom() riêng cho từng lớp —
     * tránh N+1 khi học sinh tham gia nhiều lớp cùng lúc).
     */
    public function forStudentInSessionIds(int $studentId, array $sessionIds): Collection
    {
        if ($sessionIds === []) {
            return new Collection();
        }

        return $this->query()
            ->where('student_id', $studentId)
            ->whereIn('class_session_id', $sessionIds)
            ->get()
            ->keyBy('class_session_id');
    }
}
