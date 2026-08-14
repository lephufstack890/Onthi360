<?php

namespace App\Services\Student;

use App\Enums\ReviewTargetType;
use App\Models\ClassRoom;
use App\Models\ClassSession;
use App\Models\User;
use App\Repositories\Contracts\AssignmentRepositoryInterface;
use App\Repositories\Contracts\AttemptRepositoryInterface;
use App\Repositories\Contracts\AttendanceRepositoryInterface;
use App\Repositories\Contracts\ClassEnrollmentRepositoryInterface;
use App\Repositories\Contracts\ClassMaterialRepositoryInterface;
use App\Repositories\Contracts\ClassRoomRepositoryInterface;
use App\Repositories\Contracts\ClassSessionRepositoryInterface;
use App\Repositories\Contracts\RatingSummaryRepositoryInterface;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use App\Services\AccessGateService;
use App\Services\NotificationService;

/** STU-03 — chi tiết lớp, 7 tab. */
class ClassRoomService
{
    /**
     * Giới hạn số review publish hiển thị ở tab "Đánh giá" — repo không có biến thể
     * "không giới hạn" nên dùng một mức trần rộng để giữ đúng hành vi cũ (get() không limit).
     */
    private const REVIEWS_TAB_LIMIT = 500;

    /** Cùng lý do như REVIEWS_TAB_LIMIT — đủ rộng để lấy điểm danh của TẤT CẢ buổi học đã qua. */
    private const SCHEDULE_ATTENDANCE_LIMIT = 500;

    /** Nhãn điểm danh hiển thị cho học sinh (Enums\AttendanceStatus) — khớp nhãn dùng ở teacher.schedule.attendance. */
    private const ATTENDANCE_LABELS = [
        'present' => ['Có mặt', 'success'],
        'absent' => ['Vắng', 'danger'],
        'excused' => ['Vắng có phép', 'warning'],
        'late' => ['Đi trễ', 'warning'],
    ];

    public function __construct(
        private ClassRoomRepositoryInterface $classRooms,
        private ClassEnrollmentRepositoryInterface $classEnrollments,
        private ClassSessionRepositoryInterface $classSessions,
        private ClassMaterialRepositoryInterface $classMaterials,
        private AssignmentRepositoryInterface $assignments,
        private AttemptRepositoryInterface $attempts,
        private AttendanceRepositoryInterface $attendance,
        private RatingSummaryRepositoryInterface $ratingSummaries,
        private ReviewRepositoryInterface $reviews,
        private AccessGateService $accessGate,
        private NotificationService $notifications,
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

        $enrollment = $this->classEnrollments->findActiveForUserAndClassRoom($user->id, $classRoom->id);

        // "Tiến độ lớp" — % buổi học ĐÃ KẾT THÚC / tổng số buổi đã lên lịch cho lớp, CÙNG
        // công thức đã dùng thật ở App\Services\Teacher\ClassRoomService::completionPercent()
        // (xem giải thích đầy đủ ở đó): trước đây con số này hardcode 0 kèm TODO tính theo
        // progress_unlocks + attempts, nhưng rà soát toàn bộ codebase xác nhận KHÔNG có luồng
        // nào tạo Attempt đã nộp thật nên công thức đó sẽ luôn là 0% dù đổi cách tính. Module
        // Lịch học đã chạy đầy đủ nên dùng tiến độ buổi học làm thước đo thật.
        $sessionProgress = $this->classSessions->sessionProgressCountsForClassRoomIds([$classRoom->id])->first();
        $overallPercent = $this->completionPercent(
            (int) ($sessionProgress->ended ?? 0),
            (int) ($sessionProgress->total ?? 0),
        );

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

        // Lịch học: các buổi sắp tới + đã qua gần nhất, kèm trạng thái THỜI GIAN đúng 3 mức
        // (Sắp diễn ra/Đang diễn ra/Đã kết thúc — trước đây chỉ có 2 mức nên một buổi ĐANG
        // diễn ra bị hiện nhầm thành "Đã qua") và trạng thái ĐIỂM DANH thật của chính học
        // sinh này cho từng buổi (trước đây hoàn toàn không hiển thị).
        $sessions = [];
        if ($tab === 'schedule') {
            $sessions = $this->buildScheduleTab($classRoom, $user);
        }

        // Đánh giá lớp: review đã publish.
        $reviews = collect();
        if ($tab === 'reviews') {
            $reviews = $this->reviews->publishedForTarget(ReviewTargetType::ClassRoom, $classRoom->id, self::REVIEWS_TAB_LIMIT);
        }

        // Thông báo riêng lớp: trước đây là dòng chữ TODO tĩnh hiển thị thẳng cho học sinh
        // ("cần bảng notifications") — SAI, vì hạ tầng thông báo (App\Services\
        // NotificationService, kênh 'database' Illuminate Notifications) đã có thật từ
        // trước (dùng cho chuông toàn cục + student.notifications). Lọc lại đúng thông báo
        // trỏ về lớp NÀY qua notificationsForClass() bên dưới.
        $notifications = [];
        if ($tab === 'notifications') {
            $notifications = $this->notificationsForClass($user, $classRoom);
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
            'notifications' => $notifications,
            'teachers' => $teachers,
            'students' => $students,
        ];
    }

    /**
     * Tab "Thông báo" (8.3) — lọc thông báo THẬT của học sinh (App\Services\
     * NotificationService::forUser(), dùng chung mọi vai trò) theo url trỏ ĐÚNG về lớp này,
     * để không lẫn thông báo của lớp khác/vai trò khác vào đây.
     *
     * Lưu ý phạm vi: hiện CHƯA có nơi nào trong hệ thống thực sự TẠO thông báo khi có bài
     * mới mở/lịch đổi/giáo viên thông báo cho lớp (chỉ mới có
     * App\Notifications\TeacherApprovalStatusChanged cho giáo viên) — nên tab này có thể
     * hiện rỗng cho tới khi các sự kiện đó được nối thêm; đó là một tính năng riêng, rộng
     * hơn phạm vi sửa lần này. Khác với TODO cũ, đây là truy vấn thật trên dữ liệu thật,
     * không phải dòng chữ placeholder tĩnh.
     */
    private function notificationsForClass(User $user, ClassRoom $classRoom): array
    {
        $classUrl = route('student.classes.show', ['class' => $classRoom->id]);

        return collect($this->notifications->forUser($user)['items'])
            ->filter(fn ($n) => $n['url'] !== null && str_starts_with($n['url'], $classUrl))
            ->values()
            ->all();
    }

    /**
     * Tab "Lịch học" (STU-03) — mỗi buổi có ĐỦ 2 trạng thái độc lập: thời gian (Sắp diễn
     * ra/Đang diễn ra/Đã kết thúc, tính theo giờ máy chủ — không tin client, 16 mục 3) và
     * điểm danh CỦA CHÍNH học sinh này (Có mặt/Vắng/Vắng có phép/Đi trễ/Chưa điểm danh) —
     * trước đây tab này chỉ có 1 điều kiện nhị phân (isFuture()) nên buổi đang diễn ra bị
     * hiện sai thành "Đã qua", và hoàn toàn không có thông tin điểm danh.
     */
    private function buildScheduleTab(ClassRoom $classRoom, User $user): array
    {
        $sessions = $this->classSessions->allForClassRoom($classRoom->id);

        $attendanceBySessionId = $this->attendance
            ->forStudentInClassRoom($user->id, $classRoom->id, self::SCHEDULE_ATTENDANCE_LIMIT)
            ->keyBy('class_session_id');

        return $sessions->map(function (ClassSession $s) use ($attendanceBySessionId) {
            [$timeStatusLabel, $timeStatusTone] = $this->timeStatus($s);

            $record = $attendanceBySessionId->get($s->id);
            [$attendanceLabel, $attendanceTone] = $record !== null
                ? (self::ATTENDANCE_LABELS[$record->status->value] ?? [$record->status->value, 'neutral'])
                : ['Chưa điểm danh', 'neutral'];

            return [
                'id' => $s->id,
                'topic' => $s->topic,
                'location' => $s->location,
                'startsAt' => $s->starts_at,
                'timeStatusLabel' => $timeStatusLabel,
                'timeStatusTone' => $timeStatusTone,
                'attendanceLabel' => $attendanceLabel,
                'attendanceTone' => $attendanceTone,
            ];
        })->values()->all();
    }

    /**
     * Cùng logic với App\Services\Teacher\ScheduleService::timeStatus() (giữ nhất quán 1
     * cách tính trạng thái buổi học trong toàn hệ thống): so theo giờ THỰC hiện tại, không
     * chỉ dựa vào bucket "sắp tới/đã qua" của starts_at.
     *
     * @return array{0: string, 1: string}
     */
    private function timeStatus(ClassSession $session): array
    {
        $now = now();

        if ($session->starts_at !== null && $now->lt($session->starts_at)) {
            return ['Sắp diễn ra', 'info'];
        }

        if ($session->ends_at !== null && $now->gt($session->ends_at)) {
            return ['Đã kết thúc', 'neutral'];
        }

        return ['Đang diễn ra', 'warning'];
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

    /** Xem giải thích đầy đủ ở App\Services\Teacher\ClassRoomService::completionPercent(). */
    private function completionPercent(int $endedSessions, int $totalSessions): int
    {
        if ($totalSessions <= 0) {
            return 0;
        }

        return (int) round(min($endedSessions, $totalSessions) / $totalSessions * 100);
    }
}
