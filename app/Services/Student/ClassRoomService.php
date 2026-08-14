<?php

namespace App\Services\Student;

use App\Enums\ReviewTargetType;
use App\Models\ClassRoom;
use App\Models\User;
use App\Repositories\Contracts\AssignmentRepositoryInterface;
use App\Repositories\Contracts\AttemptRepositoryInterface;
use App\Repositories\Contracts\ClassEnrollmentRepositoryInterface;
use App\Repositories\Contracts\ClassMaterialRepositoryInterface;
use App\Repositories\Contracts\ClassRoomRepositoryInterface;
use App\Repositories\Contracts\ClassSessionRepositoryInterface;
use App\Repositories\Contracts\RatingSummaryRepositoryInterface;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use App\Services\AccessGateService;

/** STU-03 — chi tiết lớp, 7 tab. */
class ClassRoomService
{
    /**
     * Giới hạn số review publish hiển thị ở tab "Đánh giá" — repo không có biến thể
     * "không giới hạn" nên dùng một mức trần rộng để giữ đúng hành vi cũ (get() không limit).
     */
    private const REVIEWS_TAB_LIMIT = 500;

    public function __construct(
        private ClassRoomRepositoryInterface $classRooms,
        private ClassEnrollmentRepositoryInterface $classEnrollments,
        private ClassSessionRepositoryInterface $classSessions,
        private ClassMaterialRepositoryInterface $classMaterials,
        private AssignmentRepositoryInterface $assignments,
        private AttemptRepositoryInterface $attempts,
        private RatingSummaryRepositoryInterface $ratingSummaries,
        private ReviewRepositoryInterface $reviews,
        private AccessGateService $accessGate,
    ) {}

    public function buildShowData(User $user, int $classId, string $tab): array
    {
        $classRoom = $this->classRooms->findWithCourseAndTeachers($classId);
        abort_if($classRoom === null, 404);

        $decision = $this->accessGate->canAccessClassRoom($user, $classRoom);
        abort_unless($decision->allowed, 403, $decision->message ?? 'Không có quyền truy cập.');

        $tabsMeta = [
            ['label' => 'Tổng quan', 'key' => 'overview'],
            ['label' => 'Lộ trình & Bài tập', 'key' => 'roadmap'],
            ['label' => 'Lịch học', 'key' => 'schedule'],
            ['label' => 'Tài liệu', 'key' => 'materials'],
            ['label' => 'Đánh giá', 'key' => 'reviews'],
            ['label' => 'Thông báo', 'key' => 'notifications'],
            ['label' => 'Thành viên', 'key' => 'members'],
        ];
        $tabsData = array_map(fn ($t) => [
            'label' => $t['label'],
            'href' => route('student.classes.show', ['class' => $classRoom->id, 'tab' => $t['key']]),
            'active' => $tab === $t['key'],
        ], $tabsMeta);

        $mainTeacher = $classRoom->teachers->firstWhere('pivot.role', 'main') ?? $classRoom->teachers->first();

        $nextSession = $this->classSessions->nextUpcomingForClassRoom($classRoom->id);

        // Dùng để tính % tiến độ thật khi có công thức (xem TODO $overallPercent dưới đây).
        $enrollment = $this->classEnrollments->findActiveForUserAndClassRoom($user->id, $classRoom->id);
        // TODO: % tiến độ thật cần công thức tổng hợp progress_unlocks + attempts của riêng học sinh này.
        $overallPercent = 0;

        $ratingSummary = $this->ratingSummaries->findForTarget(ReviewTargetType::ClassRoom, $classRoom->id);

        // Lộ trình & bài tập: dùng Assignment thật của lớp (chưa có mô hình "chương" nên hiển thị dạng danh sách phẳng).
        $roadmap = [];
        if ($tab === 'roadmap' || $tab === 'overview') {
            $roadmap = $this->buildRoadmap($classRoom, $user);
        }

        // Tài liệu lớp: ClassMaterial đang Active.
        $materials = [];
        if ($tab === 'materials' || $tab === 'overview') {
            $materials = $this->classMaterials->activeForClassRoom($classRoom->id);
        }

        // Lịch học: các buổi sắp tới + đã qua gần nhất.
        $sessions = [];
        if ($tab === 'schedule') {
            $sessions = $this->classSessions->allForClassRoom($classRoom->id);
        }

        // Đánh giá lớp: review đã publish.
        $reviews = collect();
        if ($tab === 'reviews') {
            $reviews = $this->reviews->publishedForTarget(ReviewTargetType::ClassRoom, $classRoom->id, self::REVIEWS_TAB_LIMIT);
        }

        // Thành viên.
        $teachers = $tab === 'members' ? $classRoom->teachers : collect();
        $students = $tab === 'members' ? $classRoom->students : collect();

        return [
            'classRoom' => $classRoom,
            'tab' => $tab,
            'tabsData' => $tabsData,
            'mainTeacher' => $mainTeacher,
            'nextSession' => $nextSession,
            'overallPercent' => $overallPercent,
            'ratingSummary' => $ratingSummary,
            'roadmap' => $roadmap,
            'materials' => $materials,
            'sessions' => $sessions,
            'reviews' => $reviews,
            'teachers' => $teachers,
            'students' => $students,
        ];
    }

    /**
     * Danh sách bài tập của lớp + trạng thái/kết quả theo học sinh hiện tại.
     * Lấy attempt mới nhất cho MỌI assignment trong một truy vấn (nhóm theo assignment_id)
     * thay vì một truy vấn riêng cho từng assignment (tránh N+1).
     *
     * status/mở-đóng dùng isOpenNowFor($user->id) thay vì isOpenNow() để đúng với ca thi
     * riêng của học sinh này nếu Assignment có chia ca (note họp 13/8, mục 7) — hiển thị
     * thêm "shiftLabel" để học sinh biết trước ca của mình là khung giờ nào, không phải
     * chỉ biết khi bị chặn lúc bấm vào làm bài.
     */
    private function buildRoadmap(ClassRoom $classRoom, User $user): array
    {
        // Cần đúng thứ tự opens_at tăng dần như hành vi cũ — forClassRoomWithAssessment()
        // sắp xếp giảm dần nên dùng query() (van an toàn của repo) cho biến thể này.
        $assignments = $this->assignments->query()
            ->where('class_room_id', $classRoom->id)
            ->with('assessment')
            ->orderBy('opens_at')
            ->get();

        $assignmentIds = $assignments->pluck('id')->all();

        $latestAttemptsByAssignment = $this->attempts->query()
            ->where('user_id', $user->id)
            ->whereIn('assignment_id', $assignmentIds)
            ->orderByDesc('submitted_at')
            ->get()
            ->groupBy('assignment_id');

        $items = $assignments->map(function ($a) use ($latestAttemptsByAssignment, $user) {
            $attempt = ($latestAttemptsByAssignment[$a->id] ?? collect())->first();

            $status = match (true) {
                $a->status->value === 'draft' || $a->status->value === 'scheduled' => 'Giáo viên chưa mở',
                $attempt !== null => 'Đã làm',
                $a->isOpenNowFor($user->id) => 'Đã mở',
                default => 'Đã đóng',
            };
            $tone = match ($status) {
                'Giáo viên chưa mở' => 'neutral',
                'Đã làm' => 'success',
                'Đã mở' => 'info',
                default => 'neutral',
            };

            $shiftLabel = null;
            if ($a->hasShifts()) {
                $window = $a->shiftWindowFor($user->id);
                $shiftLabel = sprintf(
                    'Ca %d/%d: %s – %s',
                    $window['index'] + 1,
                    $window['count'],
                    $window['opens_at']?->format('H:i d/m') ?? '—',
                    $window['closes_at']?->format('H:i d/m') ?? '—',
                );
            }

            return [
                'title' => $a->assessment->title ?? 'Bài tập',
                'type' => $a->assessment?->type?->value ?? '',
                'status' => $status,
                'tone' => $tone,
                'result' => $attempt?->total_score !== null ? (string) $attempt->total_score : 'Chưa làm',
                'shiftLabel' => $shiftLabel,
            ];
        })->values()->all();

        if (empty($items)) {
            return [];
        }

        return [['chapter' => 'Bài tập của lớp', 'items' => $items]];
    }
}
