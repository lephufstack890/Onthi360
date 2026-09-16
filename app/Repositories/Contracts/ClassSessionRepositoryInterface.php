<?php

namespace App\Repositories\Contracts;

use App\Models\ClassSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface ClassSessionRepositoryInterface extends BaseRepositoryInterface
{

    public function nextUpcomingForClassRoom(int $classRoomId): ?ClassSession;

    /**
     * SỬA 16/9 — buổi ĐANG DIỄN RA, nếu không có thì buổi sắp tới gần nhất.
     *
     * nextUpcomingForClassRoom() lọc starts_at >= now nên buổi đã bắt đầu mà chưa kết thúc
     * KHÔNG lọt vào (đúng nghĩa "sắp tới", giữ nguyên, nơi khác đang dùng). Trang chi tiết lớp
     * cần cái khác: đang học thì phải thấy ngay phòng học của buổi đang chạy.
     */
    public function currentOrNextForClassRoom(int $classRoomId): ?ClassSession;

    public function allForClassRoom(int $classRoomId): Collection;

    public function countPastForClassRoom(int $classRoomId): int;

    public function upcomingForClassRoomIds(array $classRoomIds, int $limit = 5): Collection;

    public function allForClassRoomIds(array $classRoomIds): Collection;

    public function mostRecentPastForClassRoomIds(array $classRoomIds, int $limit = 5): Collection;

    public function currentlyInProgressForClassRoomIds(array $classRoomIds): Collection;

    public function sessionProgressCountsForClassRoomIds(array $classRoomIds): Collection;

    /** student.schedule.index — buổi học của nhiều lớp, giới hạn trong 1 khoảng ngày (1 tuần). */
    public function forClassRoomIdsBetween(array $classRoomIds, \DateTimeInterface $start, \DateTimeInterface $end): Collection;
}
