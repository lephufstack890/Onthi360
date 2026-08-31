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
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/** STU-03 — chi tiết lớp, 7 tab. */
class ClassRoomService
{
    /**
     * Giới hạn số review publish hiển thị ở tab "Đánh giá" — repo không có biến thể
     * "không giới hạn" nên dùng một mức trần rộng để giữ đúng hành vi cũ (get() không limit).
     */
    private const REVIEWS_TAB_LIMIT = 500;

    /** Nhãn điểm danh hiển thị cho học sinh (Enums\AttendanceStatus) — khớp nhãn dùng ở teacher.schedule.attendance. */
    private const ATTENDANCE_LABELS = [
        'present' => ['Có mặt', 'success'],
        'absent' => ['Vắng', 'danger'],
        'excused' => ['Vắng có phép', 'warning'],
        'late' => ['Đi trễ', 'warning'],
    ];

    /** Tab "Lịch học" — nhãn thứ trong tuần cho bảng lịch (khớp App\Services\Student\ScheduleService). */
    private const WEEKDAY_LABELS = ['Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy', 'Chủ Nhật'];

    /** Chặn cuộn quá xa 2 hướng cho gọn giao diện (không phải giới hạn bảo mật) — ~1 năm. */
    private const MAX_WEEK_OFFSET = 52;

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
        // SỬA 31/8 (khách yêu cầu — "học sinh xem học liệu NGAY TRONG LỚP, tách khỏi Tài
        // liệu của tôi"): tái dùng đúng LibraryService::productCard() để tab "Học liệu" ở
        // đây hiển thị resources/exercises giống hệt "Tài liệu của tôi", không viết lại
        // cách trình bày riêng — xem buildShowData() bên dưới.
        private LibraryService $libraryService,
    ) {}

    public function buildShowData(User $user, int $classId, string $tab, int $weekOffset = 0): array
    {
        $classRoom = $this->classRooms->findWithCourseAndTeachers($classId);
        abort_if($classRoom === null, 404);

        $decision = $this->accessGate->canAccessClassRoom($user, $classRoom);
        abort_unless($decision->allowed, 403, $decision->message ?? 'Không có quyền truy cập.');

        $tabsMeta = [
            ['label' => 'Tổng quan', 'key' => 'overview'],
            // ['label' => 'Lộ trình & Bài tập', 'key' => 'roadmap'],
            ['label' => 'Lịch học', 'key' => 'schedule'],
            // SỬA 31/8 (khách yêu cầu — "chi tiết lớp có tab Học liệu để xem TRONG lớp thôi,
            // tài liệu tự mua xem ở trang Tài liệu, không liên quan"): bật lại tab này (đã
            // tắt từ trước — lúc đó dựa trên Material cây chương/mục cũ, nay dựa trên Product
            // NGUYÊN gắn lớp, xem buildShowData() bên dưới). Đổi nhãn 'Tài liệu' → 'Học liệu'
            // để khỏi lẫn với "Tài liệu của tôi" (trang riêng, liệt kê MỌI sản phẩm đã mua,
            // không phân biệt lớp nào — 2 khái niệm cố ý KHÔNG liên quan nhau).
            ['label' => 'Học liệu', 'key' => 'materials'],
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

        // Học liệu lớp (SỬA 31/8, khách yêu cầu — "xem học liệu NGAY TRONG LỚP, tách khỏi
        // Tài liệu của tôi"): CHỈ lấy dòng gắn NGUYÊN 1 sản phẩm (material_id=null —
        // ClassMaterial::isWholeProduct(), xem Teacher\ClassRoomService::attachProduct())
        // đang Active — đây CHÍNH LÀ tập sản phẩm AccessGateService::hasActiveClassGrantedAccess()
        // cho phép xem miễn phí qua lớp, nên hiển thị ở đây khớp 100% với thứ học sinh thực
        // sự mở được (không hiện thứ chưa chắc mở được). Dòng cũ material_id != null (cây
        // chương/mục Material, đã bỏ từ 27/8) bị loại khỏi tab này — không áp dụng "miễn phí
        // qua lớp", giữ đúng luật cũ (7.3, ba cửa độc lập) nếu còn sót dữ liệu cũ.
        $materials = [];
        if ($tab === 'materials') {
            $materials = $this->classMaterials->activeForClassRoomWithProduct($classRoom->id)
                ->filter(fn ($cm) => $cm->isWholeProduct() && $cm->product !== null)
                ->map(fn ($cm) => $this->libraryService->productCard($cm->product))
                ->values()
                ->all();
        }

        // Lịch học: DẠNG BẢNG theo tuần (Thứ Hai → Chủ Nhật, có ngày cụ thể) — cùng cách trình
        // bày với student.schedule.index (App\Services\Student\ScheduleService::buildWeekData()),
        // chỉ khác là CHỈ lọc buổi học của lớp NÀY thay vì gộp mọi lớp. Mỗi buổi có ĐỦ 2 trạng
        // thái độc lập: thời gian (Sắp diễn ra/Đang diễn ra/Đã kết thúc) và điểm danh CỦA
        // CHÍNH học sinh này (Có mặt/Vắng/Vắng có phép/Đi trễ/Chưa điểm danh).
        $scheduleWeek = ['weekOffset' => 0, 'weekStart' => now()->startOfWeek(Carbon::MONDAY), 'weekEnd' => now()->endOfWeek(Carbon::SUNDAY), 'days' => []];
        if ($tab === 'schedule') {
            $scheduleWeek = $this->buildScheduleTab($classRoom, $user, $weekOffset);
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
            'weekOffset' => $scheduleWeek['weekOffset'],
            'weekStart' => $scheduleWeek['weekStart'],
            'weekEnd' => $scheduleWeek['weekEnd'],
            'days' => $scheduleWeek['days'],
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
     * Tab "Lịch học" (STU-03) — DẠNG BẢNG theo tuần, giống hệt cách trình bày của
     * student.schedule.index (App\Services\Student\ScheduleService::buildWeekData()) nhưng
     * lọc CHỈ buổi học của lớp NÀY (không gộp lớp khác). Mỗi buổi có ĐỦ 2 trạng thái độc
     * lập: thời gian (Sắp diễn ra/Đang diễn ra/Đã kết thúc, tính theo giờ máy chủ — không
     * tin client, 16 mục 3) và điểm danh CỦA CHÍNH học sinh này (Có mặt/Vắng/Vắng có
     * phép/Đi trễ/Chưa điểm danh).
     *
     * @return array{weekOffset:int, weekStart:Carbon, weekEnd:Carbon, days:array}
     */
    private function buildScheduleTab(ClassRoom $classRoom, User $user, int $weekOffset): array
    {
        $weekOffset = max(-self::MAX_WEEK_OFFSET, min(self::MAX_WEEK_OFFSET, $weekOffset));

        $weekStart = now()->startOfWeek(Carbon::MONDAY)->addWeeks($weekOffset)->startOfDay();
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();

        $sessions = $this->classSessions->forClassRoomIdsBetween([$classRoom->id], $weekStart, $weekEnd);

        $attendanceBySessionId = $this->attendance->forStudentInSessionIds($user->id, $sessions->pluck('id')->all());

        $days = collect(range(0, 6))->map(function (int $i) use ($weekStart, $sessions, $attendanceBySessionId) {
            $date = $weekStart->copy()->addDays($i);

            $sessionsForDay = $sessions
                ->filter(fn (ClassSession $s) => $s->starts_at !== null && $s->starts_at->isSameDay($date))
                ->sortBy('starts_at')
                ->map(fn (ClassSession $s) => $this->mapScheduleSession($s, $attendanceBySessionId))
                ->values()
                ->all();

            return [
                'label' => self::WEEKDAY_LABELS[$i],
                'date' => $date,
                'isToday' => $date->isToday(),
                'sessions' => $sessionsForDay,
            ];
        })->all();

        return [
            'weekOffset' => $weekOffset,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'days' => $days,
        ];
    }

    private function mapScheduleSession(ClassSession $session, Collection $attendanceBySessionId): array
    {
        [$timeStatusLabel, $timeStatusTone] = $this->timeStatus($session);

        $record = $attendanceBySessionId->get($session->id);
        [$attendanceLabel, $attendanceTone] = $record !== null
            ? (self::ATTENDANCE_LABELS[$record->status->value] ?? [$record->status->value, 'neutral'])
            : ['Chưa điểm danh', 'neutral'];

        return [
            'id' => $session->id,
            'topic' => $session->topic,
            'location' => $session->location,
            'startsAt' => $session->starts_at,
            'endsAt' => $session->ends_at,
            'timeRangeLabel' => $this->timeRangeLabel($session),
            'timeStatusLabel' => $timeStatusLabel,
            'timeStatusTone' => $timeStatusTone,
            'attendanceLabel' => $attendanceLabel,
            'attendanceTone' => $attendanceTone,
        ];
    }

    /** "08:00 - 09:30" — chỉ giờ:phút vì ngày đã thể hiện qua cột của bảng lịch. */
    private function timeRangeLabel(ClassSession $session): string
    {
        if ($session->starts_at === null) {
            return '—';
        }

        if ($session->ends_at === null) {
            return $session->starts_at->format('H:i');
        }

        return $session->starts_at->format('H:i').' - '.$session->ends_at->format('H:i');
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

    /**
     * student.classes.join — "Vào lớp bằng mã giáo viên cung cấp" (đã hứa sẵn ở empty-state
     * của student.courses.index và ở hướng dẫn sử dụng trang Thông tin, nhưng trước đây
     * KHÔNG có route/logic nào thực hiện việc này — bảng class_enrollments chưa từng được
     * ghi bởi bất kỳ luồng ứng dụng nào, chỉ có dữ liệu do seed/thao tác tay).
     *
     * Mã lớp (ClassRoom::code) là duy nhất toàn hệ thống (unique ở DB) nên không cần biết
     * trước khóa/lớp nào — chỉ cần đúng mã là vào đúng lớp, giống cách giáo viên chia sẻ mã
     * ngoài hệ thống (Zalo/nhóm lớp...).
     */
    public function joinByCode(User $user, string $code): ClassRoom
    {
        $classRoom = $this->classRooms->query()
            ->where('code', $code)
            ->where('status', 'active')
            ->first();

        if ($classRoom === null) {
            throw ValidationException::withMessages(['code' => 'Mã lớp không đúng hoặc lớp đã ngừng hoạt động.']);
        }

        $existing = $this->classEnrollments->findAnyForUserAndClassRoom($user->id, $classRoom->id);

        if ($existing !== null && $existing->status === 'active') {
            throw ValidationException::withMessages(['code' => 'Bạn đã tham gia lớp này rồi.']);
        }

        if ($existing !== null) {
            // Từng tham gia rồi rời lớp — unique(class_room_id, student_id) không cho tạo
            // dòng mới, phải kích hoạt lại đúng dòng cũ.
            $this->classEnrollments->update($existing, [
                'status' => 'active',
                'enrolled_at' => now(),
                'left_at' => null,
            ]);
        } else {
            $this->classEnrollments->create([
                'class_room_id' => $classRoom->id,
                'student_id' => $user->id,
                'status' => 'active',
                'enrolled_at' => now(),
            ]);
        }

        return $classRoom;
    }
}
