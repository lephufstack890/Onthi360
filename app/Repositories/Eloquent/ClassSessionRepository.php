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
     * teacher.classes.index — buổi học GẦN NHẤT đã THỰC SỰ kết thúc (ends_at đã qua) của
     * mỗi lớp, để báo "buổi đã kết thúc, chưa điểm danh". Lọc theo ends_at (KHÔNG phải
     * starts_at) — nếu lọc theo starts_at thì một buổi VỪA MỚI BẮT ĐẦU (đang diễn ra, chưa
     * kết thúc) sẽ bị coi nhầm là "đã kết thúc" (bug thực tế đã gặp: buổi 15:10 mới bắt đầu
     * được 4 phút đã báo "đã kết thúc"). Lấy dư $limit dòng rồi groupBy ở Service (cùng kiểu
     * batch-fetch với upcomingForClassRoomIds) để tránh N+1.
     */
    public function mostRecentPastForClassRoomIds(array $classRoomIds, int $limit = 5): Collection
    {
        return $this->query()
            ->whereIn('class_room_id', $classRoomIds)
            ->where('ends_at', '<', now())
            ->with(['classRoom', 'attendances'])
            ->orderByDesc('starts_at')
            ->limit($limit)
            ->get();
    }

    /**
     * teacher.classes.index — buổi học ĐANG DIỄN RA của mỗi lớp (đã bắt đầu nhưng chưa kết
     * thúc: starts_at <= now() <= ends_at). Cần truy vấn riêng vì upcomingForClassRoomIds()
     * chỉ lấy buổi CHƯA bắt đầu (starts_at >= now()) và mostRecentPastForClassRoomIds() chỉ
     * lấy buổi ĐÃ kết thúc (ends_at < now()) — một buổi đang diễn ra không khớp điều kiện
     * nào trong 2 hàm trên nên phải có hàm thứ 3 để không bị "biến mất" khỏi danh sách lớp.
     */
    public function currentlyInProgressForClassRoomIds(array $classRoomIds): Collection
    {
        return $this->query()
            ->whereIn('class_room_id', $classRoomIds)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->with(['classRoom', 'attendances'])
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * teacher.classes.index/show — "Hoàn thành chung" = % buổi học ĐÃ KẾT THÚC trên tổng
     * số buổi đã lên lịch cho lớp (xem ghi chú tại ClassRoomService::completionPercent()
     * về lý do đổi từ đo theo % bài tập đã nộp sang đo theo tiến độ buổi học). Gộp COUNT
     * và SUM có điều kiện trong 1 câu query, GROUP BY class_room_id — tránh N+1 khi có
     * nhiều lớp cùng lúc (giống cách batch-fetch của các hàm khác trong repo này).
     */
    public function sessionProgressCountsForClassRoomIds(array $classRoomIds): Collection
    {
        return $this->query()
            ->whereIn('class_room_id', $classRoomIds)
            ->selectRaw('class_room_id, COUNT(*) as total, SUM(CASE WHEN ends_at < ? THEN 1 ELSE 0 END) as ended', [now()])
            ->groupBy('class_room_id')
            ->get();
    }
}
