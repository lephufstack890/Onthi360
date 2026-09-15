<?php

namespace App\Services\Admin;

use App\Enums\ContentStatus;
use App\Models\ClassRoom;
use App\Models\Course;
use App\Models\Product;
use App\Models\User;
use App\Repositories\Contracts\ClassRoomRepositoryInterface;
use App\Repositories\Contracts\CourseRepositoryInterface;
use App\Repositories\Contracts\TeacherProfileRepositoryInterface;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Gom truy vấn cho admin.courses.index — "Khóa & Lớp" (8.1: Khóa học khác Lớp học).
 */
class CourseService
{
    /*
     * ══════ HAI CÔNG TẮC KHỐI TRONG FORM THÊM/SỬA KHOÁ HỌC ══════
     *
     * SỬA 15/9 (khách: "ẩn Thông tin bậc trong lộ trình đi, ẩn Bán khoá học này luôn") —
     * ẩn 2 khối đó khỏi màn Thêm và Sửa khoá học. ẨN CHỨ KHÔNG XOÁ: đổi thành true là hiện lại.
     *
     * ── VÌ SAO KHÔNG CHỈ BỌC @if Ở VIEW LÀ XONG ──
     * Ẩn ô nhập thì trình duyệt không gửi các trường đó lên nữa. Mà store()/update() bên dưới
     * ghi thẳng 'level_code' => $data['level_code'] ?? null — tức là MỖI LẦN quản trị bấm Lưu
     * một khoá học (dù chỉ sửa cái tiêu đề), 5 cột level_code, level_subtitle, outcome,
     * session_count, product_id sẽ bị ghi đè thành NULL. Dữ liệu bậc và sản phẩm bán khoá mà
     * khách đã nhập sẽ mất sạch một cách âm thầm, đúng thứ "ẩn chứ không xoá" phải tránh.
     *
     * Nên 2 công tắc này điều khiển CẢ view LẪN phần ghi dữ liệu: khi tắt, levelAttributes()
     * và productAttribute() trả về mảng rỗng, các cột đó không nằm trong câu UPDATE nên giá
     * trị cũ trong cơ sở dữ liệu được giữ nguyên vẹn.
     *
     * Dùng ở: resources/views/partials/course-level-fields.blade.php (2 khối giao diện) và
     * store()/update() ngay trong lớp này.
     */
    public const SHOW_LEVEL_FIELDS = false;

    public const SHOW_SELLING_FIELDS = false;

    public function __construct(
        private CourseRepositoryInterface $courses,
        private ClassRoomRepositoryInterface $classRooms,
        private TeacherProfileRepositoryInterface $teacherProfiles,
    ) {}

    /** @return array{tab: string, tabs: array, rows: array} */
    public function indexData(string $tab): array
    {
        $tabs = [
            ['label' => 'Khóa học', 'href' => route('admin.courses.index'), 'active' => $tab === 'courses', 'count' => $this->courses->count()],
            ['label' => 'Lớp học', 'href' => route('admin.courses.index', ['tab' => 'classes']), 'active' => $tab === 'classes', 'count' => $this->classRooms->count()],
        ];

        if ($tab === 'classes') {
            $rows = $this->classRooms->latestWithCourseTeachersAndStudentCount(50)->map(function ($c) {
                $teacher = $c->teachers->first();

                return [
                    'id' => $c->id,
                    'name' => $c->name.' ('.($c->course->title ?? '').')',
                    'meta' => ($teacher ? 'GV '.$teacher->name : 'Chưa phân công').' · '.$c->students_count.' học sinh',
                    'status' => $c->status === 'active' ? 'Đang học' : (string) $c->status,
                    'tone' => $c->status === 'active' ? 'success' : 'neutral',
                ];
            })->all();
        } else {
            $rows = $this->courses->withClassRoomCount(50)->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->title,
                'meta' => $c->class_rooms_count.' lớp đang triển khai',
                'status' => $c->status->value === 'published' ? 'Đang mở' : (string) $c->status->value,
                'tone' => $c->status->value === 'published' ? 'success' : 'neutral',
            ])->all();
        }

        return ['tab' => $tab, 'tabs' => $tabs, 'rows' => $rows];
    }

    /** admin.courses.create — dữ liệu tĩnh cho form (khối lớp áp dụng, trạng thái xuất bản). */
    public function createFormData(): array
    {
        return [
            'courseProducts' => $this->courseProducts(),
            'grades' => ['Lớp 6', 'Lớp 7', 'Lớp 8', 'Lớp 9', 'Lớp 10', 'Lớp 11', 'Lớp 12'],
            'statuses' => [
                ContentStatus::Draft->value => 'Bản nháp — chưa hiện công khai',
                ContentStatus::Published->value => 'Xuất bản — hiện ngay ở trang Khóa học công khai',
            ],
        ];
    }

    /**
     * admin.courses.show — chi tiết 1 khóa học + danh sách lớp thuộc khóa đó (8.1).
     */
    public function showData(int $courseId): array
    {
        $course = $this->courses->query()->with('creator')->findOrFail($courseId);

        $classRooms = $this->classRooms->query()
            ->where('course_id', $courseId)
            ->with('teachers')
            ->withCount(['students', 'sessions'])
            ->latest()
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'teacher' => $c->teachers->first()->name ?? null,
                'students' => $c->students_count,
                'status' => $c->status,
                /*
                 * SỬA 15/9 (A10) — số buổi ĐÃ XẾP LỊCH THẬT của lớp này.
                 * Khác courses.session_count (số buổi theo chương trình). View đối chiếu hai
                 * con số để phát hiện lớp xếp thiếu buổi — không có chỗ này thì lớp thiếu buổi
                 * chỉ lộ ra khi học sinh kêu.
                 */
                'scheduledSessions' => $c->sessions_count,
            ]);

        return [
            'course' => $course,
            'classRooms' => $classRooms,
            'totalStudents' => $classRooms->sum('students'),
            // Số buổi theo chương trình của khoá, để view so với từng lớp.
            'designedSessions' => (int) $course->session_count,
        ];
    }

    /**
     * admin.courses.store — tạo khóa học mới (8.1: Khóa học khác Lớp học, lớp được
     * tạo riêng sau đó và gắn về khóa này). Slug tự sinh từ tiêu đề, tự thêm số thứ
     * tự nếu trùng — không bắt admin phải tự nghĩ slug.
     */
    public function store(User $creator, array $data): Course
    {
        $baseSlug = Str::slug($data['title']);
        $slug = $baseSlug;
        $suffix = 2;
        while ($this->courses->query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $this->courses->create([
            'title' => $data['title'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'subject' => $data['subject'] ?? null,
            'grade' => $data['grade'] ?? null,
            'status' => $data['status'],
            /*
             * Bốn trường "bậc" — chỉ có ý nghĩa khi khoá học được xếp vào một lộ trình.
             * Để trống hoàn toàn bình thường: khoá lẻ không thuộc lộ trình nào vẫn chạy y như
             * trước. Xem migration add_level_fields_to_courses_table.
             * Khối đang ẩn -> levelAttributes() trả rỗng, 4 cột giữ nguyên giá trị cũ.
             */
            ...$this->levelAttributes($data),
            'created_by' => $creator->id,
            // C1 — sản phẩm bán khoá này. Để trống nghĩa là chưa mở bán trực tuyến.
            ...$this->productAttribute($data),
        ]);
    }

    /** admin.courses.edit — dữ liệu form sửa (khóa học hiện tại + khối lớp/trạng thái). */
    public function editFormData(int $courseId): array
    {
        return [
            'course' => $this->courses->findOrFail($courseId),
            'courseProducts' => $this->courseProducts(),
            'grades' => ['Lớp 6', 'Lớp 7', 'Lớp 8', 'Lớp 9', 'Lớp 10', 'Lớp 11', 'Lớp 12'],
            'statuses' => [
                ContentStatus::Draft->value => 'Bản nháp — chưa hiện công khai',
                ContentStatus::Published->value => 'Xuất bản — hiện ngay ở trang Khóa học công khai',
                ContentStatus::Archived->value => 'Lưu trữ — ẩn khỏi trang công khai, vẫn còn dữ liệu',
            ],
        ];
    }

    /**
     * admin.courses.update — CHỦ ĐỘNG không cho đổi slug ở đây (giữ nguyên link công khai
     * đã chia sẻ/SEO); chỉ slug được sinh 1 lần lúc tạo (store()).
     */
    public function update(Course $course, array $data): Course
    {
        return $this->courses->update($course, [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'subject' => $data['subject'] ?? null,
            'grade' => $data['grade'] ?? null,
            'status' => $data['status'],
            /*
             * Bốn trường "bậc" — chỉ có ý nghĩa khi khoá học được xếp vào một lộ trình.
             * Để trống hoàn toàn bình thường: khoá lẻ không thuộc lộ trình nào vẫn chạy y như
             * trước. Xem migration add_level_fields_to_courses_table.
             * Khối đang ẩn -> levelAttributes() trả rỗng, 4 cột giữ nguyên giá trị cũ.
             */
            ...$this->levelAttributes($data),
            // C1 — sản phẩm bán khoá này. Để trống nghĩa là chưa mở bán trực tuyến.
            ...$this->productAttribute($data),
        ]);
    }

    /**
     * Danh sách sản phẩm loại 'course' để chọn ở ô "Sản phẩm bán khoá này".
     *
     * Chỉ lấy loại course: gắn nhầm khoá học vào một quyển sách thì người mua trả tiền sách
     * mà lại được vào lớp, nên chặn ngay từ danh sách chọn chứ không chỉ nhắc bằng lời.
     *
     * @return array<int, string> id => nhãn hiển thị kèm giá
     */
    private function courseProducts(): array
    {
        // Khối "Bán khoá học này" đang ẩn -> không cần danh sách sản phẩm, khỏi chạy truy vấn
        // thừa mỗi lần mở màn Thêm/Sửa khoá học. Xem self::SHOW_SELLING_FIELDS.
        if (! self::SHOW_SELLING_FIELDS) {
            return [];
        }

        return Product::query()
            ->where('type', \App\Enums\ProductType::Course->value)
            ->orderBy('title')
            ->get(['id', 'title', 'price'])
            ->mapWithKeys(fn (Product $p) => [
                $p->id => $p->title.' — '.number_format((int) $p->price).'đ',
            ])
            ->all();
    }

    /**
     * Cặp [cột => giá trị] cho khối "Thông tin bậc trong lộ trình".
     *
     * Trả về MẢNG RỖNG khi khối đang ẩn, để 4 cột bậc không nằm trong câu INSERT/UPDATE và
     * giữ nguyên giá trị cũ — xem ghi chú dài ở self::SHOW_LEVEL_FIELDS.
     *
     * @return array<string, string|int|null>
     */
    private function levelAttributes(array $data): array
    {
        if (! self::SHOW_LEVEL_FIELDS) {
            return [];
        }

        $sessionCount = $data['session_count'] ?? null;

        return [
            'level_code' => $data['level_code'] ?? null,
            'level_subtitle' => $data['level_subtitle'] ?? null,
            'outcome' => $data['outcome'] ?? null,
            'session_count' => ($sessionCount !== null && $sessionCount !== '') ? (int) $sessionCount : null,
        ];
    }

    /**
     * Cặp [cột => giá trị] cho ô "Bán khoá học này".
     *
     * Trả về MẢNG RỖNG khi bản cài chưa chạy migration add_product_id_to_courses_table —
     * xem Course::supportsProduct(). Ghi thẳng cột chưa tồn tại thì cả màn Sửa khoá học hỏng,
     * quản trị không đổi nổi mỗi cái tiêu đề.
     *
     * Ô để trống gửi lên chuỗi rỗng; phải quy về null chứ không ghi 0 vào khoá ngoại.
     *
     * @return array<string, int|null>
     */
    private function productAttribute(array $data): array
    {
        // Khối "Bán khoá học này" đang ẩn -> không đụng tới cột product_id, giữ nguyên giá trị
        // cũ trong CSDL. Xem ghi chú ở self::SHOW_SELLING_FIELDS.
        if (! self::SHOW_SELLING_FIELDS) {
            return [];
        }

        if (! Course::supportsProduct()) {
            return [];
        }

        $value = $data['product_id'] ?? null;

        return ['product_id' => ($value === null || $value === '') ? null : (int) $value];
    }

    /** admin.courses.destroy — xóa mềm, PHẢI có lý do + audit log (10.4). */
    public function destroy(Course $course, string $reason): void
    {
        Course::$auditReason = $reason;
        $this->courses->delete($course);
        Course::$auditReason = null;
    }

    /** admin.courses.classes.create — danh sách giáo viên đã duyệt để phân công + trạng thái. */
    public function classCreateFormData(Course $course): array
    {
        return [
            'course' => $course,
            'teachers' => $this->teacherProfiles->approvedWithUser()->map(fn ($p) => [
                'id' => $p->user->id ?? null,
                'name' => $p->user->name ?? '',
            ])->filter(fn ($t) => $t['id'] !== null)->values()->all(),
        ];
    }

    /**
     * admin.courses.classes.store — Admin tạo lớp thay mặt hệ thống (khác với giáo viên tự
     * tạo ở App\Services\Teacher\ClassRoomService::store(), nơi giáo viên hiện tại luôn là
     * giáo viên chính) — ở đây admin CHỌN giáo viên phụ trách từ danh sách đã duyệt, có thể
     * để trống ("Chưa phân công") vì UI danh sách lớp đã có sẵn trạng thái đó (8.1).
     */
    public function storeClass(Course $course, array $data): ClassRoom
    {
        if ($this->classRooms->query()->where('code', $data['code'])->exists()) {
            throw ValidationException::withMessages(['code' => 'Mã lớp này đã được dùng, chọn mã khác.']);
        }

        $classRoom = $this->classRooms->create([
            'course_id' => $course->id,
            'code' => $data['code'],
            'name' => $data['name'],
            'schedule' => filled($data['schedule_note'] ?? null) ? ['note' => $data['schedule_note']] : null,
            'status' => $data['status'] ?? 'active',
        ]);

        if (filled($data['teacher_id'] ?? null)) {
            $classRoom->teachers()->attach($data['teacher_id'], ['role' => 'main']);
        }

        return $classRoom;
    }

    /** admin.classes.edit — lớp hiện tại (kèm khóa + giáo viên) + danh sách giáo viên đã duyệt. */
    public function classEditFormData(int $classRoomId): array
    {
        $classRoom = $this->classRooms->findWithCourseAndTeachers($classRoomId)
            ?? $this->classRooms->findOrFail($classRoomId);

        return [
            'classRoom' => $classRoom,
            'currentTeacherId' => $classRoom->teachers->first()->id ?? null,
            'teachers' => $this->teacherProfiles->approvedWithUser()->map(fn ($p) => [
                'id' => $p->user->id ?? null,
                'name' => $p->user->name ?? '',
            ])->filter(fn ($t) => $t['id'] !== null)->values()->all(),
        ];
    }

    /**
     * admin.classes.update — đổi giáo viên chính bằng cách detach toàn bộ rồi attach lại
     * (lớp này chỉ có đúng 1 giáo viên chính trong mô hình hiện tại — không có UI đồng phụ
     * trách; nếu thêm sau, đổi chỗ này để sync() thay vì attach 1 người).
     */
    public function updateClass(ClassRoom $classRoom, array $data): ClassRoom
    {
        if ($data['code'] !== $classRoom->code
            && $this->classRooms->query()->where('code', $data['code'])->where('id', '!=', $classRoom->id)->exists()) {
            throw ValidationException::withMessages(['code' => 'Mã lớp này đã được dùng, chọn mã khác.']);
        }

        $this->classRooms->update($classRoom, [
            'code' => $data['code'],
            'name' => $data['name'],
            'schedule' => filled($data['schedule_note'] ?? null) ? ['note' => $data['schedule_note']] : null,
            'status' => $data['status'],
        ]);

        $classRoom->teachers()->detach();
        if (filled($data['teacher_id'] ?? null)) {
            $classRoom->teachers()->attach($data['teacher_id'], ['role' => 'main']);
        }

        return $classRoom;
    }

    /** admin.classes.destroy — xóa mềm lớp, PHẢI có lý do + audit log (10.4). */
    public function destroyClass(ClassRoom $classRoom, string $reason): void
    {
        ClassRoom::$auditReason = $reason;
        $this->classRooms->delete($classRoom);
        ClassRoom::$auditReason = null;
    }
}
