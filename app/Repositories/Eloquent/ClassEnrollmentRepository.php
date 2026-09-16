<?php

namespace App\Repositories\Eloquent;

use App\Models\ClassEnrollment;
use App\Repositories\Contracts\ClassEnrollmentRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ClassEnrollmentRepository extends EloquentRepository implements ClassEnrollmentRepositoryInterface
{
    protected string $modelClass = ClassEnrollment::class;

    public function activeForUser(int $userId, array $with = []): Collection
    {
        return $this->query()
            ->where('student_id', $userId)
            ->where('status', 'active')
            ->with($with)
            ->get();
    }

    public function activeClassRoomIdsForUser(int $userId): array
    {
        return $this->query()
            ->where('student_id', $userId)
            ->where('status', 'active')
            ->pluck('class_room_id')
            ->all();
    }

    public function findActiveForUserAndClassRoom(int $userId, int $classRoomId): ?ClassEnrollment
    {
        return $this->query()
            ->where('student_id', $userId)
            ->where('class_room_id', $classRoomId)
            ->where('status', 'active')
            ->first();
    }

    public function existsActiveForUserAndClassRoom(int $userId, int $classRoomId): bool
    {
        return $this->query()
            ->where('student_id', $userId)
            ->where('class_room_id', $classRoomId)
            ->where('status', 'active')
            ->exists();
    }

    /**
     * SỬA 16/9 — các yêu cầu ĐANG CHỜ DUYỆT của một loạt lớp (kèm học sinh để dựng danh sách).
     * Cũ nhất lên trước: giáo viên xử lý theo thứ tự ai xin trước.
     */
    public function pendingForClassRoomIds(array $classRoomIds): Collection
    {
        if ($classRoomIds === []) {
            return new Collection;
        }

        return $this->query()
            ->whereIn('class_room_id', $classRoomIds)
            ->where('status', ClassEnrollment::STATUS_PENDING)
            ->with(['student:id,name,email', 'classRoom:id,name,code'])
            ->orderBy('created_at')
            ->get();
    }

    /** SỬA 16/9 — chỉ đếm, cho huy hiệu trên thẻ lớp / nhãn tab (không nạp cả danh sách). */
    public function pendingCountsByClassRoomIds(array $classRoomIds): array
    {
        if ($classRoomIds === []) {
            return [];
        }

        return $this->query()
            ->whereIn('class_room_id', $classRoomIds)
            ->where('status', ClassEnrollment::STATUS_PENDING)
            ->selectRaw('class_room_id, COUNT(*) as aggregate')
            ->groupBy('class_room_id')
            ->pluck('aggregate', 'class_room_id')
            ->all();
    }

    /** SỬA 16/9 — các lớp học sinh này đang chờ duyệt, để thẻ lớp công khai đổi nút. */
    public function pendingClassRoomIdsForUser(int $userId): array
    {
        return $this->query()
            ->where('student_id', $userId)
            ->where('status', ClassEnrollment::STATUS_PENDING)
            ->pluck('class_room_id')
            ->all();
    }

    public function findAnyForUserAndClassRoom(int $userId, int $classRoomId): ?ClassEnrollment
    {
        return $this->query()
            ->where('student_id', $userId)
            ->where('class_room_id', $classRoomId)
            ->first();
    }
}
