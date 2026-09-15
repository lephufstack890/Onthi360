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
             */
            'level_code' => $data['level_code'] ?? null,
            'level_subtitle' => $data['level_subtitle'] ?? null,
            'outcome' => $data['outcome'] ?? null,
            'session_count' => ($data['session_count'] ?? null) !== null && $data['session_count'] !== ''
                ? (int) $data['session_count']
                : null,
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
             */
            'level_code' => $data['level_code'] ?? null,
            'level_subtitle' => $data['level_subtitle'] ?? null,
            'outcome' => $data['outcome'] ?? null,
            'session_count' => ($data['session_count'] ?? null) !== null && $data['session_count'] !== ''
                ? (int) $data['session_count']
                : null,
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
