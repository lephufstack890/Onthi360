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

    /** SỬA 16/9 — yêu cầu đăng ký đang chờ giáo viên duyệt (kèm student + classRoom). */
    public function pendingForClassRoomIds(array $classRoomIds): Collection;

    /** SỬA 16/9 — [class_room_id => số yêu cầu chờ duyệt]. */
    public function pendingCountsByClassRoomIds(array $classRoomIds): array;

    /** SỬA 16/9 — các lớp mà học sinh này đang chờ được duyệt. */
    public function pendingClassRoomIdsForUser(int $userId): array;

}
