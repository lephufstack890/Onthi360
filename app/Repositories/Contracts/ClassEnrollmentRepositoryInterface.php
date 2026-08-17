<?php

namespace App\Repositories\Contracts;

use App\Models\ClassEnrollment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface ClassEnrollmentRepositoryInterface extends BaseRepositoryInterface
{

    public function activeForUser(int $userId, array $with = []): Collection;

    public function activeClassRoomIdsForUser(int $userId): array;

    public function findActiveForUserAndClassRoom(int $userId, int $classRoomId): ?ClassEnrollment;

    public function existsActiveForUserAndClassRoom(int $userId, int $classRoomId): bool;

    /**
     * Bất kể trạng thái (active|left) — dùng khi tham gia lại lớp cũ (join-by-code): bảng
     * class_enrollments có unique(class_room_id, student_id) nên KHÔNG được tạo dòng mới
     * nếu học sinh từng có dòng 'left' cho đúng lớp này, phải cập nhật lại dòng cũ.
     */
    public function findAnyForUserAndClassRoom(int $userId, int $classRoomId): ?ClassEnrollment;
}
