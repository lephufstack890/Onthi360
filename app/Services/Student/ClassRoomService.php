<?php

namespace App\Services\Student;

use App\Enums\ReviewTargetType;
use App\Models\ClassEnrollment;
use App\Models\ClassRoom;
use App\Models\ClassSession;
use App\Models\User;
use App\Notifications\ClassJoinRequested;
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

    /**
     * SỬA 16/9 (khách yêu cầu: "bỏ chỗ nhập mã lớp tham gia lớp đi") — CÔNG TẮC lối vào lớp
     * bằng mã giáo viên cung cấp.
     *
     * ĐỔI THÀNH true LÀ BẬT LẠI TOÀN BỘ, không phải viết lại gì: joinByCode() bên dưới,
     * Student\ClassRoomController::join(), và 3 khối giao diện đang bị chú thích lại ở
     *   · resources/views/student/courses/index.blade.php   (khối "Có mã lớp?")
     *   · resources/views/access/choose-class.blade.php      (khối "Đã có mã lớp?")
     *   · resources/views/teacher/classes/show.blade.php     (dải "Mã lớp để học sinh tự tham gia")
     * Route student.classes.join vẫn ĐƯỢC ĐĂNG KÝ để route() ở chỗ khác không ném lỗi; chỉ là
     * gọi vào thì 404.
     *
     * Lối vào lớp bây giờ: học sinh bấm "Đăng ký học" ở trang lớp công khai -> requestJoin()
     * tạo dòng chờ duyệt -> giáo viên duyệt ở tab Thành viên -> vào học.
     */
    public const JOIN_BY_CODE_ENABLED = false;

    /** Số buổi tối đa hiện trong băng hoạt động ở tab Tổng quan (SỬA 16/9). */
    private const ACTIVITY_FEED_LIMIT = 12;

    /** Số buổi tối đa đưa vào ô "Đang xem" của màn lớp học (SỬA 18/9). */
    private const CLASSROOM_LESSON_LIMIT = 30;

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

    public function buildShowData(User $user, int $classId, string $tab, int $weekOffset = 0, ?int $sessionId = null): array
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
            // NGUYÊN gắn lớp, xem buildShowData() bên dưới). LÚC ĐÓ đổi nhãn 'Tài liệu' →
            // 'Học liệu' để khỏi lẫn với "Tài liệu của tôi" (trang riêng, liệt kê MỌI sản
            // phẩm đã mua, không phân biệt lớp nào).
            // SỬA 4/9 (khách yêu cầu MỚI: "bên giáo viên tab học liệu đổi tên lại thành tài
            // liệu, học sinh cũng thế") — đổi NGƯỢC LẠI 'Học liệu' → 'Tài liệu' theo đúng yêu
            // cầu này; khách ĐÃ được hỏi lại và xác nhận chấp nhận trùng tên với trang "Tài
            // liệu của tôi" (2 khái niệm khác nhau: đây là tài liệu CỦA LỚP, trang kia liệt kê
            // MỌI tài liệu đã mua không theo lớp nào).
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

        // SỬA 16/9 — nextUpcomingForClassRoom() lọc starts_at >= now nên buổi ĐANG DIỄN RA bị
        // rớt, khiến thẻ "Vào lớp trực tuyến" không thấy phòng học của chính buổi đang chạy.
        // Lấy thêm buổi đang-chạy-hoặc-sắp-tới cho trang này, KHÔNG đụng vào $nextSession (nơi
        // khác vẫn đang hiểu nó đúng nghĩa "buổi sắp tới").
        $currentSession = $this->classSessions->currentOrNextForClassRoom($classRoom->id);

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

        // SỬA 16/9 — tab Tổng quan trước đây CHỈ đổ $roadmap (bảng assignments: bài tập giao cho
        // lớp). Lớp nào giáo viên chỉ dùng HOẠT ĐỘNG BUỔI HỌC (session_activities đã bấm phát) mà
        // chưa giao assignment nào thì tab này rỗng trơn dù ngoài lịch học đã thấy hoạt động —
        // đúng lỗi khách báo. Nạp thêm băng hoạt động thật cho tab Tổng quan.
        $activityFeed = ['items' => [], 'initialIndex' => 0];
        if ($tab === 'overview') {
            $activityFeed = $this->buildActivityFeed($classRoom);
        }

        // SỬA 18/9 — màn lớp học dựng lại theo bản mẫu mới (xem buildClassroomData()). Chỉ nạp ở
        // tab Tổng quan, đúng nếp nạp-theo-tab của hàm này.
        $classroom = [];
        if ($tab === 'overview') {
            $classroom = $this->buildClassroomData($user, $classRoom, $sessionId);
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
            'currentSession' => $currentSession,
            'overallPercent' => $overallPercent,
            'ratingSummary' => $ratingSummary,
            'roadmap' => $roadmap,
            'activityFeed' => $activityFeed,
            'classroom' => $classroom,
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
     * SỬA 18/9 (khách yêu cầu: "khi vào lớp học thì bên học sinh sẽ hiển thị UI như [ảnh], lấy UI
     * trong source mới, đổ dữ liệu từ database ra, mục ghi hình buổi học tạm thời bỏ đi") —
     * dữ liệu cho màn LỚP HỌC dựng theo education-main/src/components/ClassroomPage.jsx.
     *
     * Màn này xoay quanh MỘT BUỔI HỌC: chọn buổi ở ô "Đang xem", rồi xem dải hoạt động của buổi
     * đó và nội dung/học liệu của hoạt động đang chọn.
     *
     * LUẬT HIỂN THỊ GIỮ NGUYÊN: chỉ hoạt động giáo viên ĐÃ BẤM PHÁT mới lọt ra học sinh
     * (SessionActivity::scopePublished()) — đúng yêu cầu cũ "giáo viên phải click icon play thì
     * học sinh mới thấy được".
     *
     * BA TRƯỜNG BẢN MẪU CÓ MÀ HỆ THỐNG KHÔNG CÓ NGUỒN, xử lý như sau (không bịa số):
     *   · khung giờ riêng của từng hoạt động ("19:30 – 19:35") — bảng session_activities không
     *     có cột giờ; thay bằng GIỜ GIÁO VIÊN PHÁT hoạt động (published_at), là mốc thật và cũng
     *     chính là lúc hoạt động bắt đầu với học sinh;
     *   · trạng thái hoạt động — suy từ dữ liệu thật: buổi đã kết thúc thì mọi hoạt động là "Đã
     *     tổ chức"; buổi đang diễn ra thì hoạt động phát SAU CÙNG là "Đang diễn ra", các hoạt
     *     động trước đó là "Đã tổ chức"; buổi chưa bắt đầu thì "Chưa diễn ra";
     *   · mục "Ghi hình buổi học" — khách bảo tạm bỏ, và hệ thống cũng chưa có nguồn video.
     */
    public function buildClassroomData(User $user, ClassRoom $classRoom, ?int $sessionId = null): array
    {
        $now = now();

        // Danh sách buổi để chọn: buổi ĐÃ BẮT ĐẦU hoặc đã có hoạt động được phát. Buổi tương lai
        // chưa có gì để xem nên không đưa vào ô chọn (đúng chữ "Các buổi đã tổ chức" của bản mẫu).
        $sessions = $this->classSessions->query()
            ->where('class_room_id', $classRoom->id)
            ->orderBy('starts_at')
            ->get();

        $numberBySessionId = [];
        foreach ($sessions->values() as $index => $session) {
            $numberBySessionId[$session->id] = $index + 1;
        }

        $selectable = $sessions
            ->filter(fn (ClassSession $session) => $session->starts_at !== null && $session->starts_at->lte($now))
            ->sortByDesc('starts_at')
            ->take(self::CLASSROOM_LESSON_LIMIT)
            ->values();

        // Chưa có buổi nào diễn ra thì lấy buổi gần nhất sắp tới, để màn không trống trơn.
        if ($selectable->isEmpty()) {
            $selectable = $sessions->sortBy('starts_at')->take(1)->values();
        }

        $attendanceBySessionId = $this->attendance->forStudentInSessionIds($user->id, $selectable->pluck('id')->all());

        $selected = $sessionId !== null
            ? $selectable->firstWhere('id', $sessionId)
            : null;
        $selected = $selected ?? $selectable->first();

        $lessons = $selectable->map(fn (ClassSession $session) => [
            'id' => $session->id,
            'number' => $numberBySessionId[$session->id] ?? 0,
            'title' => $session->topic ?: 'Buổi học',
            'dateLabel' => $session->starts_at?->format('d/m/Y') ?? '',
            'timeLabel' => $this->timeRangeLabel($session),
            'isSelected' => $selected !== null && $session->id === $selected->id,
            'isLatest' => $selectable->first() !== null && $session->id === $selectable->first()->id,
        ])->all();

        if ($selected === null) {
            return [
                'lessons' => [], 'selected' => null, 'activities' => [],
                'meetUrl' => null, 'roomNote' => null, 'notifications' => [], 'classMaterials' => [],
            ];
        }

        // Hoạt động ĐÃ PHÁT của đúng buổi đang xem + tài nguyên bên trong.
        $selected->load([
            'activities' => fn ($q) => $q->published()->orderBy('position')->orderBy('id'),
            'activities.resources.material',
            'activities.resources.question',
            'activities.resources.assessment',
        ]);

        // Điểm bài làm của CHÍNH học sinh này cho các đề xuất hiện trong buổi — bản mẫu có viên
        // "Đúng hết / Đúng một phần / Sai · 86/100" trên thẻ bài tập. Lấy MỘT truy vấn cho cả
        // buổi (tái dùng progressForUserAndAssessments, đúng hàm trang Luyện tập đang dùng).
        $assessmentIds = $selected->activities
            ->flatMap(fn ($activity) => $activity->resources->pluck('assessment_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $scoreByAssessment = $this->attempts->progressForUserAndAssessments($user->id, $assessmentIds)->keyBy('assessment_id');
        $totalPointsByAssessment = $selected->activities
            ->flatMap(fn ($activity) => $activity->resources)
            ->filter(fn ($r) => $r->assessment !== null)
            ->mapWithKeys(fn ($r) => [$r->assessment_id => (float) ($r->assessment->total_points ?? 0)])
            ->all();

        $sessionEnded = $selected->ends_at !== null && $now->gt($selected->ends_at);
        $sessionStarted = $selected->starts_at !== null && $now->gte($selected->starts_at);
        $lastPublishedId = $selected->activities->sortByDesc('published_at')->first()?->id;

        $activities = $selected->activities->values()->map(function ($activity, $index) use ($sessionEnded, $sessionStarted, $lastPublishedId, $scoreByAssessment, $totalPointsByAssessment) {
            [$stateKey, $stateLabel] = match (true) {
                ! $sessionStarted => ['upcoming', 'Chưa diễn ra'],
                $sessionEnded => ['completed', 'Đã tổ chức'],
                $activity->id === $lastPublishedId => ['current', 'Đang diễn ra'],
                default => ['completed', 'Đã tổ chức'],
            };

            return [
                'id' => $activity->id,
                'index' => $index + 1,
                'title' => $activity->title,
                'note' => $activity->note,
                'state' => $stateKey,
                'statusLabel' => $stateLabel,
                // Bản mẫu in khung giờ riêng của hoạt động; hệ thống chỉ có mốc GIÁO VIÊN PHÁT.
                'timeLabel' => $activity->published_at?->format('H:i') ?? '—',
                'items' => $this->groupActivityResources($activity, $scoreByAssessment, $totalPointsByAssessment),
            ];
        })->all();

        // Buổi học đang xem có phòng trực tuyến không (class_sessions.location vốn được định
        // nghĩa là "phòng học HOẶC link online").
        $isUrl = \Illuminate\Support\Str::startsWith((string) $selected->location, ['http://', 'https://']);

        return [
            'lessons' => $lessons,
            'selected' => [
                'id' => $selected->id,
                'number' => $numberBySessionId[$selected->id] ?? 0,
                'title' => $selected->topic ?: 'Buổi học',
                'dateLabel' => $selected->starts_at?->format('d/m/Y') ?? '',
                'timeLabel' => $this->timeRangeLabel($selected),
                'attendance' => $this->attendanceChip($attendanceBySessionId->get($selected->id)),
            ],
            'activities' => $activities,
            'meetUrl' => $isUrl ? $selected->location : null,
            'roomNote' => (! $isUrl && filled($selected->location)) ? $selected->location : null,
            'notifications' => $this->notificationsForClass($user, $classRoom),
            'classMaterials' => $this->classMaterials->activeForClassRoomWithProduct($classRoom->id)
                ->filter(fn ($cm) => $cm->isWholeProduct() && $cm->product !== null)
                ->map(fn ($cm) => ['id' => $cm->product->id, 'title' => $cm->product->title])
                ->values()
                ->all(),
        ];
    }

    /**
     * Viên trạng thái điểm danh của CHÍNH học sinh này ở buổi đang xem — bản mẫu chỉ có một ô
     * "Đã có mặt" màu xanh, thực tế có đủ 5 trạng thái (kể cả chưa điểm danh).
     */
    private function attendanceChip($record): array
    {
        if ($record === null) {
            return ['label' => 'Chưa điểm danh', 'tone' => 'none', 'timeLabel' => null];
        }

        [$label] = self::ATTENDANCE_LABELS[$record->status->value] ?? ['Chưa điểm danh', 'neutral'];

        return [
            'label' => $label,
            'tone' => $record->status->value,
            'timeLabel' => $record->updated_at?->format('H:i'),
        ];
    }

    /**
     * Xếp tài nguyên của một hoạt động vào 3 nhóm của bản mẫu (Bài tập · Tài liệu · Học liệu).
     *
     * Bản mẫu có sẵn trường "category" cho từng mục; hệ thống thì phân theo LOẠI TÀI NGUYÊN thật
     * (App\Enums\SessionResourceType): đề/câu hỏi là thứ để LÀM, tài liệu/ghi chú là thứ để
     * ĐỌC, video/link là thứ để XEM.
     */
    private function groupActivityResources($activity, $scoreByAssessment = null, array $totalPointsByAssessment = []): array
    {
        $groups = ['exercise' => [], 'document' => [], 'material' => []];
        $scoreByAssessment = $scoreByAssessment ?? collect();

        foreach ($activity->resources as $resource) {
            $type = $resource->type;

            $group = match ($type) {
                \App\Enums\SessionResourceType::Assessment, \App\Enums\SessionResourceType::Question => 'exercise',
                \App\Enums\SessionResourceType::Video, \App\Enums\SessionResourceType::Link => 'material',
                default => 'document',
            };

            // Cùng luật với mapScheduleSession(): chỉ mở làm bài khi là ĐỀ và đề ĐÃ PHÁT HÀNH.
            $assessmentId = $resource->assessment_id !== null
                && $resource->assessment !== null
                && $resource->assessment->status === \App\Enums\ContentStatus::Published
                    ? $resource->assessment_id
                    : null;

            // Viên điểm của bản mẫu: CHỈ hiện khi học sinh này thật sự đã nộp bài đề đó.
            $scoreLabel = null;
            $scoreTone = null;
            $row = $resource->assessment_id !== null ? $scoreByAssessment->get($resource->assessment_id) : null;
            if ($row !== null && (int) $row->submitted_count > 0 && $row->best_score !== null) {
                $best = (float) $row->best_score;
                $total = (float) ($totalPointsByAssessment[$resource->assessment_id] ?? 0);
                $scoreLabel = $total > 0
                    ? $this->trimNumber($best).'/'.$this->trimNumber($total)
                    : $this->trimNumber($best).' điểm';
                $ratio = $total > 0 ? $best / $total : ($best > 0 ? 1.0 : 0.0);
                [$scoreTone, $scoreText] = match (true) {
                    $ratio >= 1.0 => ['is-correct', 'Đúng hết'],
                    $ratio > 0 => ['is-partial', 'Đúng một phần'],
                    default => ['is-wrong', 'Sai'],
                };
                $scoreLabel = $scoreText.' · '.$scoreLabel;
            }

            $groups[$group][] = [
                'id' => $resource->id,
                'scoreLabel' => $scoreLabel,
                'scoreTone' => $scoreTone,
                'title' => $resource->displayTitle(),
                'typeLabel' => $type->label(),
                'code' => $resource->question?->code,
                'assessmentId' => $assessmentId,
                'url' => in_array($type, [\App\Enums\SessionResourceType::Video, \App\Enums\SessionResourceType::Link], true)
                    ? $resource->url
                    : null,
                'note' => $resource->note,
            ];
        }

        return $groups;
    }

    /**
     * SỬA 18/9 (khách yêu cầu: "khi click vào học nó cũng hiển thị popup như này trước") — dữ
     * liệu XEM NHANH cho popup chi tiết lớp ở trang Lớp học công khai, dành cho người ĐÃ LÀ
     * THÀNH VIÊN của lớp.
     *
     * Vì sao đặt ở đây chứ không ở Public\CourseService: mấy mục này (bài tập được giao, tài
     * liệu của lớp, thông báo, thành viên) là dữ liệu NỘI BỘ của lớp — dựng lại ở tầng công khai
     * là chép luật quyền ra chỗ thứ hai, sớm muộn cũng lệch. Ở đây dùng CHUNG đúng cửa quyền
     * (AccessGateService::canAccessClassRoom) và đúng các hàm mà trang chi tiết lớp đang dùng.
     *
     * Không phải thành viên -> trả mảng RỖNG, popup tự về đúng phần công khai. Không ném 403 vì
     * người ngoài vẫn được xem popup, chỉ là xem phần công khai.
     */
    public function previewForMember(User $user, int $classId): array
    {
        $classRoom = $this->classRooms->findWithCourseAndTeachers($classId);

        if ($classRoom === null || ! $this->accessGate->canAccessClassRoom($user, $classRoom)->allowed) {
            return [];
        }

        $progress = $this->classSessions->sessionProgressCountsForClassRoomIds([$classRoom->id])->first();
        $endedSessions = (int) ($progress->ended ?? 0);
        $totalSessions = (int) ($progress->total ?? 0);

        // Bài tập giao cho lớp + kết quả của CHÍNH học sinh này — đúng hàm mà tab Tổng quan của
        // trang chi tiết lớp đang dùng, không viết lại cách tính.
        $assignments = collect($this->buildRoadmap($classRoom, $user))
            ->flatMap(fn ($chapter) => $chapter['items'] ?? [])
            ->values()
            ->all();

        // Học liệu gắn NGUYÊN sản phẩm vào lớp — cùng điều kiện lọc với buildShowData().
        $materials = $this->classMaterials->activeForClassRoomWithProduct($classRoom->id)
            ->filter(fn ($cm) => $cm->isWholeProduct() && $cm->product !== null)
            ->map(fn ($cm) => [
                'id' => $cm->product->id,
                'title' => $cm->product->title,
                'coverPath' => $cm->product->cover_image_path,
            ])
            ->values()
            ->all();

        return [
            'percent' => $this->completionPercent($endedSessions, $totalSessions),
            'endedSessions' => $endedSessions,
            'totalSessions' => $totalSessions,
            'assignments' => $assignments,
            'materials' => $materials,
            'notifications' => $this->notificationsForClass($user, $classRoom),
            'teachers' => $classRoom->teachers->map(fn ($t) => [
                'name' => $t->name,
                'role' => ($t->pivot->role ?? 'main') === 'main' ? 'Giáo viên chính' : 'Trợ giảng',
            ])->values()->all(),
            'studentsCount' => $classRoom->students()->count(),
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

        // SỬA 9/9 (4) (khách: "giáo viên phải click icon play thì học sinh mới thấy được") — nạp
        // sẵn HOẠT ĐỘNG ĐÃ PHÁT của các buổi trong tuần. scopePublished() là chỗ DUY NHẤT quyết
        // định học sinh thấy gì: hoạt động giáo viên đang soạn (published_at = null) không bao giờ
        // lọt vào đây. Nạp kèm quan hệ của tài nguyên để displayTitle() không bắn thêm truy vấn.
        $sessions->load([
            'activities' => fn ($q) => $q->published(),
            'activities.resources.material',
            'activities.resources.question',
            'activities.resources.assessment',
        ]);

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
            // SỬA 9/9 (4) — chỉ hoạt động ĐÃ PHÁT (đã lọc từ lúc nạp quan hệ ở trên).
            'activities' => $session->activities->map(fn ($activity) => [
                'title' => $activity->title,
                'note' => $activity->note,
                'resources' => $activity->resources->map(fn ($r) => [
                    'type' => $r->type->value,
                    'typeLabel' => $r->type->label(),
                    'title' => $r->displayTitle(),
                    // SỬA 9/9 (5) — id đề để dựng nút "Làm bài"; chỉ có với tài nguyên là đề, và
                    // chỉ khi đề ĐÃ PHÁT HÀNH (đề nháp bấm vào sẽ bị server chặn, xem
                    // AttemptService::publishedActivityClassRoomIdFor()).
                    'assessmentId' => $r->assessment_id !== null
                        && $r->assessment !== null
                        && $r->assessment->status === \App\Enums\ContentStatus::Published
                            ? $r->assessment_id
                            : null,
                ])->values()->all(),
            ])->values()->all(),
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

    /**
     * SỬA 16/9 — BĂNG HOẠT ĐỘNG cho tab Tổng quan (khách báo: "có hoạt động rồi mà nó vẫn không
     * hiện"). Trước đây tab Tổng quan chỉ đọc bảng assignments; hoạt động buổi học nằm ở
     * session_activities và CHỈ được dựng trong tab Lịch học, nên lớp dạy bằng hoạt động thì tab
     * Tổng quan rỗng.
     *
     * Luật hiển thị GIỮ NGUYÊN của tính năng Hoạt động: chỉ hoạt động ĐÃ BẤM PHÁT
     * (SessionActivity::scopePublished()) mới lọt ra học sinh — cả ở whereHas lẫn lúc nạp quan hệ.
     * Nút "Làm bài" cũng theo đúng luật cũ: chỉ có với tài nguyên là ĐỀ và đề ĐÃ PHÁT HÀNH.
     *
     * Trả về theo thứ tự thời gian tăng dần + vị trí nên mở sẵn (buổi đang diễn ra; không có thì
     * buổi sắp tới gần nhất; không có nữa thì buổi gần đây nhất).
     */
    private function buildActivityFeed(ClassRoom $classRoom): array
    {
        $sessions = $this->classSessions->query()
            ->where('class_room_id', $classRoom->id)
            ->whereHas('activities', fn ($q) => $q->published())
            ->with([
                'activities' => fn ($q) => $q->published()->orderBy('position'),
                'activities.resources.material',
                'activities.resources.question',
                'activities.resources.assessment',
            ])
            ->orderBy('starts_at')
            ->get();

        if ($sessions->isEmpty()) {
            return ['items' => [], 'initialIndex' => 0];
        }

        $now = now();

        $items = $sessions->map(function (ClassSession $session) use ($now) {
            [$statusLabel, $statusTone] = $this->timeStatus($session);

            // Tiến độ buổi học: đã kết thúc = 100%, chưa bắt đầu = 0%, đang chạy = phần thời gian
            // đã trôi qua. Bản mẫu in cứng 65% — đây là số tính từ giờ học thật.
            $percent = 0;
            if ($session->ends_at !== null && $now->gt($session->ends_at)) {
                $percent = 100;
            } elseif ($session->starts_at !== null && $session->ends_at !== null && $now->gte($session->starts_at)) {
                $total = $session->starts_at->diffInSeconds($session->ends_at);
                $percent = $total > 0
                    ? (int) round(min(100, max(0, $session->starts_at->diffInSeconds($now) / $total * 100)))
                    : 0;
            }

            $activities = $session->activities;

            $resources = $activities->flatMap(fn ($activity) => $activity->resources->map(fn ($r) => [
                'type' => $r->type->value,
                'typeLabel' => $r->type->label(),
                'title' => $r->displayTitle(),
                'activityTitle' => $activity->title,
                // Cùng luật với mapScheduleSession(): chỉ mở được khi là đề VÀ đề đã phát hành.
                'assessmentId' => $r->assessment_id !== null
                    && $r->assessment !== null
                    && $r->assessment->status === \App\Enums\ContentStatus::Published
                        ? $r->assessment_id
                        : null,
                // Video/Link do giáo viên nhập tay có sẵn địa chỉ -> mở thẳng.
                'url' => in_array($r->type, [\App\Enums\SessionResourceType::Video, \App\Enums\SessionResourceType::Link], true)
                    ? $r->url
                    : null,
            ]))->values()->all();

            return [
                'sessionId' => $session->id,
                'title' => $session->topic ?: 'Buổi học',
                'dateLabel' => $session->starts_at?->format('d/m/Y') ?? '',
                'timeLabel' => $this->timeRangeLabel($session),
                'statusLabel' => $statusLabel,
                'statusTone' => $statusTone,
                'percent' => $percent,
                'isToday' => $session->starts_at?->isToday() ?? false,
                'location' => $session->location,
                'activityCount' => $activities->count(),
                'openedBy' => $activities->count() === 1
                    ? 'Giáo viên đã mở: '.$activities->first()->title
                    : 'Giáo viên đã mở '.$activities->count().' hoạt động',
                'note' => $activities->pluck('note')->filter()->implode(' · '),
                'resources' => $resources,
            ];
        })->values()->all();

        // Vị trí mở sẵn: buổi đầu tiên CHƯA kết thúc (đang chạy hoặc sắp tới); hết rồi thì buổi cuối.
        $initialIndex = count($items) - 1;
        foreach ($sessions->values() as $i => $session) {
            if ($session->ends_at === null || $now->lte($session->ends_at)) {
                $initialIndex = $i;
                break;
            }
        }

        // Lớp dạy lâu có thể có rất nhiều buổi — cắt một cửa sổ quanh vị trí mở sẵn để trang không
        // phình ra hàng trăm chấm điều hướng.
        if (count($items) > self::ACTIVITY_FEED_LIMIT) {
            $start = max(0, min($initialIndex - (int) (self::ACTIVITY_FEED_LIMIT / 2), count($items) - self::ACTIVITY_FEED_LIMIT));
            $items = array_slice($items, $start, self::ACTIVITY_FEED_LIMIT);
            $initialIndex -= $start;
        }

        return ['items' => $items, 'initialIndex' => $initialIndex];
    }

    /** 8.00 -> "8", 8.50 -> "8.5" — điểm in ra không kéo theo số 0 vô nghĩa. */
    private function trimNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
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
        // SỬA 16/9 — xem self::JOIN_BY_CODE_ENABLED. Chặn ở ĐÂY chứ không chỉ giấu nút: giấu nút
        // thôi thì ai biết địa chỉ vẫn POST thẳng vào được.
        abort_unless(self::JOIN_BY_CODE_ENABLED, 404);

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


    /**
     * SỬA 16/9 (khách yêu cầu) — HỌC SINH XIN VÀO LỚP từ trang lớp học công khai.
     *
     * Luồng mới thay cho mã lớp: bấm "Đăng ký học" -> tạo dòng class_enrollments trạng thái
     * 'pending' -> giáo viên của lớp duyệt (Teacher\ClassRoomService::approveJoinRequest()) ->
     * status thành 'active' -> vào học được.
     *
     * KHÔNG tự cho vào lớp ở bước này. AccessGateService::canAccessClassRoom() chỉ chấp nhận
     * 'active', nên trong lúc chờ duyệt học sinh gõ thẳng địa chỉ lớp vẫn bị chặn — đúng ý
     * "giáo viên duyệt thì mới được vào học".
     *
     * @throws ValidationException khi lớp đã đóng, đang chờ duyệt, hoặc đã ở trong lớp.
     */
    public function requestJoin(User $user, int $classRoomId): ClassRoom
    {
        $classRoom = $this->classRooms->query()
            ->where('id', $classRoomId)
            ->where('status', 'active')
            ->first();

        if ($classRoom === null) {
            throw ValidationException::withMessages(['class_room_id' => 'Lớp không còn mở hoặc không tồn tại.']);
        }

        $existing = $this->classEnrollments->findAnyForUserAndClassRoom($user->id, $classRoom->id);

        if ($existing !== null && $existing->status === ClassEnrollment::STATUS_ACTIVE) {
            throw ValidationException::withMessages(['class_room_id' => 'Bạn đang học lớp này rồi.']);
        }

        if ($existing !== null && $existing->status === ClassEnrollment::STATUS_PENDING) {
            throw ValidationException::withMessages(['class_room_id' => 'Yêu cầu của bạn đang chờ giáo viên duyệt.']);
        }

        // Dấu vết duyệt chỉ ghi khi máy chủ đã chạy migration — chưa chạy thì luồng vẫn chạy
        // bằng riêng cột status (xem ClassEnrollment::supportsApprovalFields()).
        $traces = ClassEnrollment::supportsApprovalFields()
            ? ['requested_at' => now(), 'approved_at' => null, 'approved_by' => null, 'reject_reason' => null]
            : [];

        if ($existing !== null) {
            // Từng rời lớp / từng bị từ chối rồi xin lại: unique(class_room_id, student_id) không
            // cho tạo dòng mới nên phải ghi đè đúng dòng cũ — cùng cách joinByCode() vẫn làm.
            $this->classEnrollments->update($existing, array_merge([
                'status' => ClassEnrollment::STATUS_PENDING,
                'left_at' => null,
            ], $traces));
        } else {
            $this->classEnrollments->create(array_merge([
                'class_room_id' => $classRoom->id,
                'student_id' => $user->id,
                'status' => ClassEnrollment::STATUS_PENDING,
                // enrolled_at có default useCurrent() ở lược đồ; mốc VÀO LỚP thật sẽ được ghi đè
                // lúc giáo viên duyệt, xem Teacher\ClassRoomService::approveJoinRequest().
                'enrolled_at' => now(),
            ], $traces));
        }

        // Báo mọi giáo viên của lớp — không có bước này thì yêu cầu nằm im, không ai biết.
        foreach ($classRoom->teachers as $teacher) {
            $teacher->notify(new ClassJoinRequested($classRoom, $user));
        }

        return $classRoom;
    }
}
