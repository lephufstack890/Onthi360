<?php

namespace App\Services\Teacher;

use App\Enums\AccessScope;
use App\Enums\ClassMaterialStatus;
use App\Enums\ContentStatus;
use App\Models\ClassRoom;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\AccessRightRepositoryInterface;
use App\Repositories\Contracts\AssignmentRepositoryInterface;
use App\Repositories\Contracts\AttemptRepositoryInterface;
use App\Repositories\Contracts\ClassMaterialRepositoryInterface;
use App\Repositories\Contracts\ClassRoomRepositoryInterface;
use App\Repositories\Contracts\ClassSessionRepositoryInterface;
use App\Repositories\Contracts\CourseRepositoryInterface;
use App\Repositories\Contracts\MaterialRepositoryInterface;
use App\Repositories\Contracts\RatingSummaryRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

/** Tổng hợp dữ liệu cho teacher.classes.index/show (TEA-02/06). */
class ClassRoomService
{
    public function __construct(
        private readonly ClassRoomRepositoryInterface $classRooms,
        private readonly ClassSessionRepositoryInterface $classSessions,
        private readonly ClassMaterialRepositoryInterface $classMaterials,
        private readonly RatingSummaryRepositoryInterface $ratingSummaries,
        private readonly CourseRepositoryInterface $courses,
        private readonly AccessRightRepositoryInterface $accessRights,
        private readonly MaterialRepositoryInterface $materials,
        private readonly ScheduleService $scheduleService,
        private readonly AssignmentRepositoryInterface $assignments,
        private readonly AttemptRepositoryInterface $attempts,
    ) {}

    /** teacher.classes.index — lớp giáo viên phụ trách hoặc đồng phụ trách (8.1). */
    public function listForTeacher(User $user): array
    {
        $classRooms = $user->classRoomsTeaching()
            ->with('course')
            // students() relation đã tự lọc wherePivot('status','active') trong định nghĩa
            // (App\Models\ClassRoom) — không lặp lại điều kiện đó ở đây (gây lỗi SQL khi
            // withCount() dựng subquery đếm, đã kiểm chứng bằng lỗi thực tế).
            ->withCount('students')
            ->get();

        $classRoomIds = $classRooms->pluck('id')->all();

        // N+1 fix: một truy vấn theo lô cho toàn bộ buổi học sắp tới thay vì 1 query/dòng.
        $nextSessionByClassRoomId = $this->classSessions
            ->upcomingForClassRoomIds($classRoomIds, max(count($classRoomIds) * 5, 5))
            ->groupBy('class_room_id')
            ->map(fn ($sessions) => $sessions->first());

        // Buổi học GẦN NHẤT đã qua của mỗi lớp — để báo "buổi vừa kết thúc, chưa điểm
        // danh" thay vì im lặng không hiện gì khi buổi học không còn nằm trong "sắp tới".
        $lastSessionByClassRoomId = $this->classSessions
            ->mostRecentPastForClassRoomIds($classRoomIds, max(count($classRoomIds) * 5, 5))
            ->groupBy('class_room_id')
            ->map(fn ($sessions) => $sessions->first());

        // "Hoàn thành chung" thật: % cặp (học sinh, bài giao đã mở) có ít nhất 1 lần nộp,
        // tính theo TỪNG lớp bằng 2 truy vấn theo lô (không lặp N+1 mỗi dòng).
        $assignedCountByClassRoomId = $this->assignments->assignedForClassRoomIds($classRoomIds)
            ->groupBy('class_room_id')
            ->map->count();
        $submittedPairsByClassRoomId = $this->attempts->submittedAssignmentPairsForClassRoomIds($classRoomIds)
            ->groupBy('class_room_id')
            ->map(fn ($rows) => $rows->unique(fn ($r) => $r->assignment_id.'-'.$r->user_id)->count());

        $classes = $classRooms->map(function (ClassRoom $classRoom) use ($nextSessionByClassRoomId, $lastSessionByClassRoomId, $assignedCountByClassRoomId, $submittedPairsByClassRoomId) {
            $nextSession = $nextSessionByClassRoomId->get($classRoom->id);
            $lastSession = $nextSession === null ? $lastSessionByClassRoomId->get($classRoom->id) : null;

            $assignedCount = (int) ($assignedCountByClassRoomId->get($classRoom->id) ?? 0);
            $submittedPairs = (int) ($submittedPairsByClassRoomId->get($classRoom->id) ?? 0);

            return [
                'id' => $classRoom->id,
                'code' => $classRoom->code,
                'course' => $classRoom->course->title ?? '',
                'name' => $classRoom->name,
                'students' => $classRoom->students_count,
                // Ghi chú lịch học nhập lúc tạo lớp (8.1: "Lịch học (ghi chú hiển thị)") —
                // khác với "buổi tới" (nextSession, lấy từ class_sessions cụ thể).
                'scheduleNote' => $classRoom->schedule['note'] ?? null,
                'nextSession' => $nextSession?->starts_at->format('d/m H:i'),
                // Chỉ có ý nghĩa khi KHÔNG còn buổi sắp tới (buổi gần nhất đã qua) — báo
                // giáo viên biết buổi đã kết thúc và có điểm danh chưa, thay vì im lặng.
                'lastSessionId' => $lastSession?->id,
                'lastSessionLabel' => $lastSession?->starts_at->format('d/m H:i'),
                'lastSessionAttendanceTaken' => $lastSession !== null && $lastSession->attendances->isNotEmpty(),
                'completion' => $this->completionPercent($assignedCount, $classRoom->students_count, $submittedPairs),
            ];
        })->values()->all();

        return ['classes' => $classes];
    }

    /**
     * "Hoàn thành chung" = % cặp (học sinh, bài giao đã mở) đã nộp ít nhất 1 lần, trong
     * tổng số cặp có thể có (số học sinh × số bài đã mở). Trả 0 nếu chưa có bài nào từng
     * mở hoặc lớp chưa có học sinh — tránh chia cho 0 và tránh hiểu nhầm "0 bài = 100%".
     */
    private function completionPercent(int $assignedCount, int $studentsCount, int $submittedPairs): int
    {
        $possiblePairs = $assignedCount * $studentsCount;

        if ($possiblePairs <= 0) {
            return 0;
        }

        return (int) round(min($submittedPairs, $possiblePairs) / $possiblePairs * 100);
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

        // "Hoàn thành chung" thật cho hero header (cùng công thức với teacher.classes.index,
        // chỉ khác là tính cho MỘT lớp nên không cần batch-fetch theo lô).
        $assignedCount = $this->assignments->assignedForClassRoomIds([$classRoom->id])->count();
        $submittedPairs = $this->attempts->submittedAssignmentPairsForClassRoomIds([$classRoom->id])
            ->unique(fn ($r) => $r->assignment_id.'-'.$r->user_id)
            ->count();
        $completion = $this->completionPercent($assignedCount, $studentsCount, $submittedPairs);

        $materials = [];
        $attachableMaterials = [];
        if ($tab === 'materials') {
            $materials = $this->classMaterials->activeForClassRoomWithProduct($classRoom->id)
                ->map(fn ($cm) => [
                    'id' => $cm->id,
                    'title' => $cm->material->title ?? 'Học liệu',
                    'scope' => 'Đang dùng ở lớp này',
                    'tone' => 'success',
                    'linkedStatus' => 'Đang dùng',
                ])->all();

            $attachableMaterials = $this->attachableMaterials($user, $classRoom);
        }

        $sessions = [];
        if ($tab === 'schedule') {
            $sessions = $this->scheduleService->sessionsForClassRoom($classRoom)['sessions'];
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
            'completion' => $completion,
            'materials' => $materials,
            'attachableMaterials' => $attachableMaterials,
            'sessions' => $sessions,
            'members' => $members,
            'ratingSummary' => $ratingSummary,
        ];
    }

    /**
     * Học liệu giáo viên còn quyền dạy (teacher_teaching còn hạn), đã phát hành, chưa gắn vào
     * lớp này (8.2: "Danh sách chỉ hiển thị học liệu mà giáo viên có quyền dạy còn hạn").
     */
    public function attachableMaterials(User $teacher, ClassRoom $classRoom): array
    {
        $activeTeachingByProduct = $this->accessRights->forUserWithProduct($teacher->id)
            ->filter(fn ($ar) => $ar->scope === AccessScope::TeacherTeaching && $ar->isCurrentlyActive())
            ->keyBy('product_id');

        if ($activeTeachingByProduct->isEmpty()) {
            return [];
        }

        $alreadyAttachedMaterialIds = $this->classMaterials->query()
            ->where('class_room_id', $classRoom->id)
            ->where('status', 'active')
            ->pluck('material_id')
            ->all();

        return $this->materials->query()
            ->whereIn('product_id', $activeTeachingByProduct->keys())
            ->where('status', ContentStatus::Published)
            ->whereNull('parent_id')
            ->whereNotIn('id', $alreadyAttachedMaterialIds)
            ->with('product')
            ->orderBy('order')
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'title' => $m->title,
                'product' => $m->product->title ?? '',
                'expiresAtLabel' => optional($activeTeachingByProduct->get($m->product_id)?->expires_at)->format('d/m/Y'),
            ])->all();
    }

    /**
     * teacher.classes.attachMaterial — "Thêm vào lớp" (8.2). Kiểm tra lại quyền dạy còn hạn
     * TẠI THỜI ĐIỂM gắn, không tin danh sách đã hiển thị trước đó trên UI (16 mục 3).
     */
    public function attachMaterial(User $teacher, int $classId, int $materialId): void
    {
        $classRoom = $this->findTaughtClassRoom($teacher, $classId);
        $material = $this->materials->findOrFail($materialId);

        $stillEligible = $this->accessRights->forUserWithProduct($teacher->id)
            ->contains(fn ($ar) => $ar->scope === AccessScope::TeacherTeaching
                && $ar->isCurrentlyActive()
                && (int) $ar->product_id === (int) $material->product_id);

        abort_unless($stillEligible, 403, 'Bạn không còn quyền dạy học liệu này (quyền đã hết hạn hoặc chưa từng có).');

        $existing = $this->classMaterials->query()
            ->where('class_room_id', $classRoom->id)
            ->where('material_id', $materialId)
            ->first();

        if ($existing !== null) {
            $this->classMaterials->update($existing, [
                'status' => ClassMaterialStatus::Active,
                'removed_at' => null,
                'added_by' => $teacher->id,
                'added_at' => now(),
            ]);

            return;
        }

        $this->classMaterials->create([
            'class_room_id' => $classRoom->id,
            'material_id' => $materialId,
            'product_id' => $material->product_id,
            'release_version' => 1,
            'status' => ClassMaterialStatus::Active,
            'added_by' => $teacher->id,
            'added_at' => now(),
        ]);
    }

    /**
     * teacher.classes.detachMaterial — "Gỡ" (8.2: không xóa lịch sử, chỉ chuyển trạng thái
     * "Đã gỡ" — bài đã làm trước đó vẫn dẫn đến kết quả cũ).
     */
    public function detachMaterial(User $teacher, int $classId, int $classMaterialId): void
    {
        $classRoom = $this->findTaughtClassRoom($teacher, $classId);
        $classMaterial = $this->classMaterials->findOrFail($classMaterialId);

        abort_unless((int) $classMaterial->class_room_id === $classRoom->id, 404);

        $this->classMaterials->update($classMaterial, [
            'status' => ClassMaterialStatus::Removed,
            'removed_at' => now(),
        ]);
    }

    private function findTaughtClassRoom(User $teacher, int $classId): ClassRoom
    {
        $classRoom = $this->classRooms->findOrFail($classId);
        $this->ensureTeaches($classRoom, $teacher);

        return $classRoom;
    }

    /** Kiểm tra quyền: giáo viên đứng lớp (main/co_teacher) hoặc admin/super_admin (7.2). */
    public function ensureTeaches(ClassRoom $classRoom, User $user): void
    {
        abort_unless($classRoom->isTaughtBy($user) || $user->hasAnyRole(Role::ADMIN, Role::SUPER_ADMIN), 403);
    }

    /** teacher.classes.create — danh sách khóa học để chọn khi tạo lớp mới. */
    public function createFormData(): array
    {
        $courses = $this->courses->query()
            ->where('status', ContentStatus::Published)
            ->orderBy('title')
            ->get()
            ->map(fn ($course) => ['id' => $course->id, 'title' => $course->title])
            ->all();

        return ['courses' => $courses];
    }

    /**
     * teacher.classes.store — tạo lớp mới thuộc một khóa đã có (8.1: Khóa học khác Lớp học,
     * một khóa có thể có nhiều lớp) và tự gắn giáo viên hiện tại làm giáo viên chính
     * (class_teachers role=main) — không thì lớp vừa tạo sẽ không hiện ở danh sách của
     * chính giáo viên đó (User::classRoomsTeaching()).
     */
    public function store(User $teacher, array $data): ClassRoom
    {
        if ($this->classRooms->query()->where('code', $data['code'])->exists()) {
            throw ValidationException::withMessages(['code' => 'Mã lớp này đã được dùng, chọn mã khác.']);
        }

        $classRoom = $this->classRooms->create([
            'course_id' => $data['course_id'],
            'code' => $data['code'],
            'name' => $data['name'],
            'schedule' => filled($data['schedule_note'] ?? null) ? ['note' => $data['schedule_note']] : null,
            'status' => $data['status'] ?? 'active',
        ]);

        $classRoom->teachers()->attach($teacher->id, ['role' => 'main']);

        return $classRoom;
    }
}
