<?php

namespace App\Services\Teacher;

use App\Models\ClassRoom;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\ClassMaterialRepositoryInterface;
use App\Repositories\Contracts\ClassRoomRepositoryInterface;
use App\Repositories\Contracts\ClassSessionRepositoryInterface;
use App\Repositories\Contracts\RatingSummaryRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/** Tổng hợp dữ liệu cho teacher.classes.index/show (TEA-02/06). */
class ClassRoomService
{
    public function __construct(
        private readonly ClassRoomRepositoryInterface $classRooms,
        private readonly ClassSessionRepositoryInterface $classSessions,
        private readonly ClassMaterialRepositoryInterface $classMaterials,
        private readonly RatingSummaryRepositoryInterface $ratingSummaries,
    ) {}

    /** teacher.classes.index — lớp giáo viên phụ trách hoặc đồng phụ trách (8.1). */
    public function listForTeacher(User $user): array
    {
        $classRooms = $user->classRoomsTeaching()
            ->with('course')
            ->withCount(['students' => fn ($q) => $q->wherePivot('status', 'active')])
            ->get();

        $classRoomIds = $classRooms->pluck('id')->all();

        // N+1 fix: một truy vấn theo lô cho toàn bộ buổi học sắp tới thay vì 1 query/dòng.
        $nextSessionByClassRoomId = $this->classSessions
            ->upcomingForClassRoomIds($classRoomIds, max(count($classRoomIds) * 5, 5))
            ->groupBy('class_room_id')
            ->map(fn ($sessions) => $sessions->first());

        $classes = $classRooms->map(function (ClassRoom $classRoom) use ($nextSessionByClassRoomId) {
            $nextSession = $nextSessionByClassRoomId->get($classRoom->id);

            return [
                'id' => $classRoom->id,
                'course' => $classRoom->course->title ?? '',
                'name' => $classRoom->name,
                'students' => $classRoom->students_count,
                'nextSession' => $nextSession?->starts_at->format('d/m H:i'),
                // TODO: % hoàn thành chung thật cần tổng hợp progress_unlocks + attempts toàn lớp.
                'completion' => 0,
            ];
        })->values()->all();

        return ['classes' => $classes];
    }

    /** teacher.classes.show — chi tiết lớp (TEA-02 chi tiết + TEA-06 học liệu, 8.2/8.3). */
    public function showForTeacher(User $user, int $classId, string $tab): array
    {
        $classRoom = $this->classRooms->findWithCourse($classId)
            ?? throw (new ModelNotFoundException())->setModel(ClassRoom::class, [$classId]);

        $this->ensureTeaches($classRoom, $user);

        $tabDefs = ['overview' => 'Tổng quan', 'materials' => 'Học liệu', 'schedule' => 'Lịch/Điểm danh', 'assign' => 'Giao đề', 'results' => 'Kết quả', 'members' => 'Thành viên'];
        $tabsData = [];
        foreach ($tabDefs as $key => $label) {
            $tabsData[] = ['label' => $label, 'href' => route('teacher.classes.show', ['class' => $classRoom->id, 'tab' => $key]), 'active' => $tab === $key];
        }

        $studentsCount = $classRoom->students()->count();
        $nextSession = $this->classSessions->nextUpcomingForClassRoom($classRoom->id);

        $materials = [];
        if ($tab === 'materials') {
            $materials = $this->classMaterials->activeForClassRoomWithProduct($classRoom->id)
                ->map(fn ($cm) => [
                    'title' => $cm->material->title ?? 'Học liệu',
                    'scope' => 'Đang dùng ở lớp này',
                    'tone' => 'success',
                    'linkedStatus' => 'Đang dùng',
                ])->all();
        }

        $members = $tab === 'members' ? $classRoom->students : collect();

        // TODO: rating_summaries theo target_type=class_room cho block "Rating nội bộ" ở tab overview.
        $ratingSummary = $this->ratingSummaries->findForTarget('class_room', $classRoom->id);

        return [
            'classRoom' => $classRoom,
            'tab' => $tab,
            'tabsData' => $tabsData,
            'studentsCount' => $studentsCount,
            'nextSession' => $nextSession,
            'materials' => $materials,
            'members' => $members,
            'ratingSummary' => $ratingSummary,
        ];
    }

    /** Kiểm tra quyền: giáo viên đứng lớp (main/co_teacher) hoặc admin/super_admin (7.2). */
    public function ensureTeaches(ClassRoom $classRoom, User $user): void
    {
        abort_unless($classRoom->isTaughtBy($user) || $user->hasAnyRole(Role::ADMIN, Role::SUPER_ADMIN), 403);
    }
}
