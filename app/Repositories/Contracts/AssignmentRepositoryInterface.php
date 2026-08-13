<?php

namespace App\Repositories\Contracts;

use App\Models\Assignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface AssignmentRepositoryInterface extends BaseRepositoryInterface
{

    public function forClassRoomIds(array $classRoomIds, ?string $status = null, int $limit = 30): Collection;

    public function countForClassRoomIds(array $classRoomIds, ?string $status = null): int;

    public function forClassRoomWithAssessment(int $classRoomId): Collection;

    public function draftOrScheduledForClassRoomIds(array $classRoomIds, int $limit = 10): Collection;

    /**
     * Bài giao ĐÃ tới giờ mở (opens_at <= now(), loại trừ draft) của các lớp trong
     * $classRoomIds — mẫu số để tính "Hoàn thành chung". Tính theo opens_at trực tiếp
     * thay vì cột status vì status chỉ được gán 1 lần lúc tạo, không tự cập nhật theo
     * thời gian (xem ghi chú ở Eloquent AssignmentRepository).
     */
    public function assignedForClassRoomIds(array $classRoomIds): Collection;
}
