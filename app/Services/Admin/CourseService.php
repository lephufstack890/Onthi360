<?php

namespace App\Services\Admin;

use App\Enums\ContentStatus;
use App\Models\ClassRoom;
use App\Models\Course;
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
            ->withCount('students')
            ->latest()
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'teacher' => $c->teachers->first()->name ?? null,
                'students' => $c->students_count,
                'status' => $c->status,
            ]);

        return [
            'course' => $course,
            'classRooms' => $classRooms,
            'totalStudents' => $classRooms->sum('students'),
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
            'created_by' => $creator->id,
        ]);
    }

    /** admin.courses.edit — dữ liệu form sửa (khóa học hiện tại + khối lớp/trạng thái). */
    public function editFormData(int $courseId): array
    {
        return [
            'course' => $this->courses->findOrFail($courseId),
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
        ]);
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
