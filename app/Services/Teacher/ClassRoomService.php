<?php

namespace App\Services\Teacher;

use App\Enums\AccessScope;
use App\Enums\AssignmentStatus;
use App\Enums\ClassMaterialStatus;
use App\Enums\ContentStatus;
use App\Models\Assignment;
use App\Models\ClassRoom;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\AccessRightRepositoryInterface;
use App\Repositories\Contracts\AssignmentRepositoryInterface;
use App\Repositories\Contracts\ClassMaterialRepositoryInterface;
use App\Repositories\Contracts\ClassRoomRepositoryInterface;
use App\Repositories\Contracts\ClassSessionRepositoryInterface;
use App\Repositories\Contracts\CourseRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\RatingSummaryRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

/** Tổng hợp dữ liệu cho teacher.classes.index/show (TEA-02/06). */
class ClassRoomService
{
    /**
     * SỬA 31/8 (khách yêu cầu — "chỗ Học liệu trong lớp nên gắn CẢ sản phẩm: sách/chuyên
     * đề/bộ đề, có 3 loại để chọn"): nhãn 3 loại để dựng bước "chọn loại rồi chọn sản phẩm"
     * ở attachableProducts() — khớp đúng 3 tab Sách/Chuyên đề/Bộ đề dùng ở
     * Student\LibraryService/Public\MaterialService (type=course KHÔNG thuộc "học liệu",
     * loại bỏ y hệt 2 nơi đó).
     */
    private const TYPE_LABELS = [
        'book' => '📘 Sách',
        'topic' => '🗂️ Chuyên đề',
        'exam' => '📝 Bộ đề',
    ];

    public function __construct(
        private readonly ClassRoomRepositoryInterface $classRooms,
        private readonly ClassSessionRepositoryInterface $classSessions,
        private readonly ClassMaterialRepositoryInterface $classMaterials,
        private readonly RatingSummaryRepositoryInterface $ratingSummaries,
        private readonly CourseRepositoryInterface $courses,
        private readonly AccessRightRepositoryInterface $accessRights,
        // SỬA 31/8 — thay MaterialRepositoryInterface (cây chương/mục cũ, đã bỏ khỏi luồng
        // gắn lớp): giờ gắn NGUYÊN 1 Product (sách/chuyên đề/bộ đề), xem attachableProducts()/
        // attachProduct() bên dưới.
        private readonly ProductRepositoryInterface $products,
        private readonly ScheduleService $scheduleService,
        private readonly AssignmentRepositoryInterface $assignments,
        // SỬA 24/8 — khách yêu cầu: "Giao đề" (chọn đề có sẵn) chuyển hẳn vào tab "Giao đề"
        // ở đây, KHÔNG còn ở Bài tập & Đề nữa — tái dùng nguyên AssessmentService::
        // assignToClass()/assessmentsForPicker() (không chép lại logic).
        private readonly AssessmentService $assessmentService,
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

        // Buổi học GẦN NHẤT đã THỰC SỰ kết thúc của mỗi lớp — để báo "buổi vừa kết
        // thúc, chưa điểm danh" thay vì im lặng không hiện gì khi buổi học không còn nằm
        // trong "sắp tới".
        $lastSessionByClassRoomId = $this->classSessions
            ->mostRecentPastForClassRoomIds($classRoomIds, max(count($classRoomIds) * 5, 5))
            ->groupBy('class_room_id')
            ->map(fn ($sessions) => $sessions->first());

        // Buổi học ĐANG DIỄN RA (đã bắt đầu, chưa kết thúc) — tách riêng khỏi "buổi gần
        // nhất đã qua" để không báo nhầm một buổi vừa mới bắt đầu là "đã kết thúc" (bug đã
        // gặp thực tế: buổi 15:10 mới bắt đầu 4 phút bị báo "đã kết thúc, chưa điểm danh").
        $inProgressSessionByClassRoomId = $this->classSessions
            ->currentlyInProgressForClassRoomIds($classRoomIds)
            ->groupBy('class_room_id')
            ->map(fn ($sessions) => $sessions->first());

        // "Hoàn thành chung" = % buổi học đã kết thúc / tổng số buổi đã lên lịch cho lớp
        // (xem completionPercent() bên dưới về lý do đổi từ đo theo % bài tập đã nộp).
        $sessionProgressByClassRoomId = $this->classSessions
            ->sessionProgressCountsForClassRoomIds($classRoomIds)
            ->keyBy('class_room_id');

        $classes = $classRooms->map(function (ClassRoom $classRoom) use ($nextSessionByClassRoomId, $inProgressSessionByClassRoomId, $lastSessionByClassRoomId, $sessionProgressByClassRoomId) {
            $nextSession = $nextSessionByClassRoomId->get($classRoom->id);
            // Ưu tiên hiển thị: buổi sắp tới > buổi đang diễn ra > buổi gần nhất đã kết
            // thúc. Một buổi chỉ rơi vào đúng 1 trong 3 nhóm (3 truy vấn không giao nhau).
            $inProgressSession = $nextSession === null ? $inProgressSessionByClassRoomId->get($classRoom->id) : null;
            $lastSession = ($nextSession === null && $inProgressSession === null) ? $lastSessionByClassRoomId->get($classRoom->id) : null;

            $progress = $sessionProgressByClassRoomId->get($classRoom->id);
            $totalSessions = (int) ($progress->total ?? 0);
            $endedSessions = (int) ($progress->ended ?? 0);

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
                // Buổi ĐANG diễn ra (đã bắt đầu, chưa kết thúc) — trạng thái riêng, không
                // gộp chung với "đã kết thúc".
                'inProgressSessionId' => $inProgressSession?->id,
                'inProgressSessionLabel' => $inProgressSession?->starts_at->format('H:i') . ($inProgressSession !== null ? '–' . $inProgressSession->ends_at->format('H:i') : ''),
                'inProgressAttendanceTaken' => $inProgressSession !== null && $inProgressSession->attendances->isNotEmpty(),
                // Chỉ có ý nghĩa khi KHÔNG còn buổi sắp tới / đang diễn ra (buổi gần nhất
                // ĐÃ THỰC SỰ kết thúc) — báo giáo viên biết buổi đã kết thúc và có điểm danh
                // chưa, thay vì im lặng.
                'lastSessionId' => $lastSession?->id,
                'lastSessionLabel' => $lastSession?->starts_at->format('d/m H:i'),
                'lastSessionAttendanceTaken' => $lastSession !== null && $lastSession->attendances->isNotEmpty(),
                'completion' => $this->completionPercent($endedSessions, $totalSessions),
                'completionEndedSessions' => $endedSessions,
                'completionTotalSessions' => $totalSessions,
            ];
        })->values()->all();

        return ['classes' => $classes];
    }

    /**
     * "Hoàn thành chung" = % buổi học ĐÃ KẾT THÚC trên tổng số buổi đã lên lịch cho lớp.
     *
     * Trước đây tính theo % cặp (học sinh, bài giao đã mở) đã nộp bài — nhưng rà soát
     * toàn bộ codebase xác nhận KHÔNG có nơi nào tạo Attempt đã nộp (không
     * Attempt::create/repository create, không seeder, route học sinh làm bài chỉ có
     * GET để xem trang chứ chưa có luồng nộp bài thật — xem TODO trong
     * AssessmentService::buildTakeData()). Vì vậy số đó LUÔN LÀ 0% bất kể dữ liệu thật,
     * gây hiểu nhầm là lỗi hiển thị. Đổi sang đo theo tiến độ buổi học — dữ liệu này CÓ
     * THẬT (module Lịch đã chạy đầy đủ) và phản ánh đúng "lớp đã học tới đâu", theo góp ý
     * trực tiếp của người dùng. Trả 0 nếu lớp chưa có buổi học nào — tránh chia cho 0 và
     * tránh hiểu nhầm "chưa có buổi nào = 100%".
     */
    private function completionPercent(int $endedSessions, int $totalSessions): int
    {
        if ($totalSessions <= 0) {
            return 0;
        }

        return (int) round(min($endedSessions, $totalSessions) / $totalSessions * 100);
    }

    /** teacher.classes.show — chi tiết lớp (TEA-02 chi tiết + TEA-06 học liệu, 8.2/8.3). */
    public function showForTeacher(User $user, int $classId, string $tab): array
    {
        $classRoom = $this->classRooms->findWithCourse($classId)
            ?? throw (new ModelNotFoundException())->setModel(ClassRoom::class, [$classId]);

        $this->ensureTeaches($classRoom, $user);

        $tabDefs = ['overview' => 'Tổng quan', 'materials' => 'Học liệu', 'schedule' => 'Buổi học', 'assign' => 'Giao đề', 'results' => 'Kết quả', 'members' => 'Thành viên'];
        $tabsData = [];
        foreach ($tabDefs as $key => $label) {
            $tabsData[] = ['label' => $label, 'href' => route('teacher.classes.show', ['class' => $classRoom->id, 'tab' => $key]), 'active' => $tab === $key];
        }

        $studentsCount = $classRoom->students()->count();
        $nextSession = $this->classSessions->nextUpcomingForClassRoom($classRoom->id);

        // "Hoàn thành chung" cho hero header (cùng công thức với teacher.classes.index,
        // chỉ khác là tính cho MỘT lớp nên không cần batch-fetch theo lô).
        $progress = $this->classSessions->sessionProgressCountsForClassRoomIds([$classRoom->id])->first();
        $totalSessions = (int) ($progress->total ?? 0);
        $endedSessions = (int) ($progress->ended ?? 0);
        $completion = $this->completionPercent($endedSessions, $totalSessions);
        $completionEndedSessions = $endedSessions;
        $completionTotalSessions = $totalSessions;

        $materials = [];
        $attachableProducts = [];
        if ($tab === 'materials') {
            // SỬA 31/8 (khách yêu cầu — "gắn CẢ sản phẩm: sách/chuyên đề/bộ đề, không chỉ 1
            // chương lẻ"): mỗi dòng giờ là 1 SẢN PHẨM nguyên vẹn (material_id=null, xem
            // migration make_material_id_nullable_on_class_materials_table +
            // ClassMaterial::isWholeProduct()) — 'productId'/'hasContent' để Blade dựng link
            // "Xem" qua access.resource (kind=content, đã cho phép giáo viên vì có
            // TeacherTeaching — AccessGateService::canAccessProduct()), thay cho link cũ
            // teacher.materials.read (chỉ đọc được 1 Material chương/mục, không còn phù hợp).
            $materials = $this->classMaterials->activeForClassRoomWithProduct($classRoom->id)
                ->map(fn ($cm) => [
                    'id' => $cm->id,
                    'productId' => $cm->product_id,
                    'hasContent' => filled($cm->product?->content_pdf_path),
                    'title' => $cm->product->title ?? 'Học liệu',
                    'typeLabel' => self::TYPE_LABELS[$cm->product?->type?->value] ?? '',
                    'scope' => 'Đang dùng ở lớp này',
                    'tone' => 'success',
                    'linkedStatus' => 'Đang dùng',
                ])->all();

            $attachableProducts = $this->attachableProducts($user, $classRoom);
        }

        $sessions = [];
        if ($tab === 'schedule') {
            $sessions = $this->scheduleService->sessionsForClassRoom($classRoom)['sessions'];
        }

        // Tab "Giao đề" (8.4) — trước đây chỉ có CTA "+ Tạo bài giao đánh giá mới" mà
        // KHÔNG hiển thị các đề ĐÃ giao cho lớp này, khiến giáo viên giao đề xong không
        // thấy đâu cả (đã xảy ra thực tế). forClassRoomWithAssessment() lấy MỌI assignment
        // của lớp (không lọc theo status — status có thể lỗi thời, xem
        // assignmentLiveStatus() bên dưới tự tính lại theo opens_at/closes_at thật).
        $assignments = [];
        $assignableAssessments = [];
        if ($tab === 'assign') {
            $assignments = $this->assignments->forClassRoomWithAssessment($classRoom->id)
                ->map(function (Assignment $assignment) {
                    [$statusLabel, $statusTone] = $this->assignmentLiveStatus($assignment);

                    return [
                        'id' => $assignment->id,
                        'title' => $assignment->assessment->title ?? '(Đề đã bị xóa)',
                        'opensAtLabel' => $assignment->opens_at?->format('d/m/Y H:i') ?? 'Không giới hạn',
                        'closesAtLabel' => $assignment->closes_at?->format('d/m/Y H:i') ?? 'Không giới hạn',
                        'statusLabel' => $statusLabel,
                        'statusTone' => $statusTone,
                        'instructions' => $assignment->instructions,
                    ];
                })->all();

            // SỬA 24/8 — khách yêu cầu: chọn đề CÓ SẴN ngay tại đây (lớp đã biết, chỉ cần
            // chọn đề) — dùng chung dữ liệu với teacher.assessments.index để không lệch
            // (xem AssessmentService::assessmentsForPicker()).
            $assignableAssessments = $this->assessmentService->assessmentsForPicker($user);
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
            'completionEndedSessions' => $completionEndedSessions,
            'completionTotalSessions' => $completionTotalSessions,
            'materials' => $materials,
            'attachableProducts' => $attachableProducts,
            'sessions' => $sessions,
            'assignments' => $assignments,
            'assignableAssessments' => $assignableAssessments,
            'members' => $members,
            'ratingSummary' => $ratingSummary,
        ];
    }

    /**
     * teacher.classes.assign — SỬA 24/8 (khách yêu cầu): "Giao đề" giờ làm từ tab này (chọn
     * đề CÓ SẴN cho 1 lớp đã biết trước), thay cho luồng cũ ở Bài tập & Đề. Tái dùng nguyên
     * AssessmentService::assignToClass() (auto-publish nếu chưa, kiểm tra lớp, chia ca...) —
     * chỉ khác chỗ $classId đến từ URL {class} thay vì 1 field chọn lớp trong form.
     */
    public function assignAssessmentToClass(User $teacher, int $classId, int $assessmentId, array $data): Assignment
    {
        $classRoom = $this->findTaughtClassRoom($teacher, $classId);
        $assessment = $this->assessmentService->findOwned($teacher, $assessmentId);

        $data['class_room_id'] = $classRoom->id;

        return $this->assessmentService->assignToClass($teacher, $assessment, $data);
    }

    /**
     * "Giao đề" 8.4: Nháp → Đã lên lịch → Đang mở → Đã đóng → Đã lưu trữ. Nháp/Đã lưu trữ
     * là hành động chủ động của giáo viên nên tin cột status; còn lại tính lại theo
     * opens_at/closes_at THẬT thay vì tin cột status có thể lỗi thời (status chỉ được gán
     * 1 lần lúc tạo, không tự cập nhật theo thời gian sau đó — xem ghi chú tại
     * AssignmentRepository::assignedForClassRoomIds()).
     */
    private function assignmentLiveStatus(Assignment $assignment): array
    {
        if ($assignment->status === AssignmentStatus::Draft) {
            return ['Nháp', 'neutral'];
        }

        if ($assignment->status === AssignmentStatus::Archived) {
            return ['Đã lưu trữ', 'neutral'];
        }

        if ($assignment->opens_at !== null && $assignment->opens_at->isFuture()) {
            return ['Đã lên lịch', 'info'];
        }

        if ($assignment->closes_at !== null && $assignment->closes_at->isPast()) {
            return ['Đã đóng', 'neutral'];
        }

        return ['Đang mở', 'success'];
    }

    /**
     * SỬA 31/8 (khách yêu cầu — "chỗ Học liệu trong lớp nên gắn CẢ sản phẩm: sách/chuyên
     * đề/bộ đề, có 3 loại để chọn, chọn xong list ra để giáo viên chọn"): THAY THẾ
     * attachableMaterials() cũ (dựa trên Material — cây chương/mục đã bỏ từ 27/8, "4 file
     * đính kèm sản phẩm" — nên gần như luôn rỗng với sản phẩm mới). Giờ trả về Product
     * giáo viên còn quyền dạy (teacher_teaching còn hạn), đã phát hành, CHƯA gắn (đang
     * active) vào lớp này — nhóm sẵn theo 3 loại (self::TYPE_LABELS) để Blade dựng bước
     * "chọn loại rồi chọn sản phẩm trong loại đó".
     *
     * @return array<string, array{label:string, products:array}>
     */
    public function attachableProducts(User $teacher, ClassRoom $classRoom): array
    {
        $activeTeachingByProduct = $this->accessRights->forUserWithProduct($teacher->id)
            ->filter(fn ($ar) => $ar->scope === AccessScope::TeacherTeaching && $ar->isCurrentlyActive())
            ->keyBy('product_id');

        if ($activeTeachingByProduct->isEmpty()) {
            return [];
        }

        $alreadyAttachedProductIds = $this->classMaterials->query()
            ->where('class_room_id', $classRoom->id)
            ->where('status', 'active')
            ->pluck('product_id')
            ->all();

        $products = $this->products->query()
            ->whereIn('id', $activeTeachingByProduct->keys())
            ->where('status', ContentStatus::Published)
            ->whereIn('type', array_keys(self::TYPE_LABELS))
            ->whereNotIn('id', $alreadyAttachedProductIds)
            ->orderBy('title')
            ->get();

        $grouped = [];
        foreach (self::TYPE_LABELS as $typeValue => $label) {
            $grouped[$typeValue] = ['label' => $label, 'products' => []];
        }

        foreach ($products as $p) {
            $grouped[$p->type->value]['products'][] = [
                'id' => $p->id,
                'title' => $p->title,
                'expiresAtLabel' => optional($activeTeachingByProduct->get($p->id)?->expires_at)->format('d/m/Y'),
            ];
        }

        return $grouped;
    }

    /**
     * teacher.classes.attachMaterial (route/URI giữ tên cũ) — "Thêm vào lớp" (8.2), giờ gắn
     * NGUYÊN 1 sản phẩm (material_id=null trên dòng class_materials tạo mới — xem
     * ClassMaterial::isWholeProduct()) thay vì 1 chương/mục lẻ. Kiểm tra lại quyền dạy còn
     * hạn TẠI THỜI ĐIỂM gắn, không tin danh sách đã hiển thị trước đó trên UI (16 mục 3).
     */
    public function attachProduct(User $teacher, int $classId, int $productId): void
    {
        $classRoom = $this->findTaughtClassRoom($teacher, $classId);
        $product = $this->products->findOrFail($productId);

        $stillEligible = $this->accessRights->forUserWithProduct($teacher->id)
            ->contains(fn ($ar) => $ar->scope === AccessScope::TeacherTeaching
                && $ar->isCurrentlyActive()
                && (int) $ar->product_id === (int) $product->id);

        abort_unless($stillEligible, 403, 'Bạn không còn quyền dạy sản phẩm này (quyền đã hết hạn hoặc chưa từng có).');

        $existing = $this->classMaterials->query()
            ->where('class_room_id', $classRoom->id)
            ->where('product_id', $productId)
            ->whereNull('material_id')
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
            'material_id' => null,
            'product_id' => $productId,
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
