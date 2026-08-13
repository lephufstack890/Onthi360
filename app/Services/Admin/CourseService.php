<?php

namespace App\Services\Admin;

use App\Enums\ContentStatus;
use App\Models\Course;
use App\Models\User;
use App\Repositories\Contracts\ClassRoomRepositoryInterface;
use App\Repositories\Contracts\CourseRepositoryInterface;
use Illuminate\Support\Str;

/**
 * Gom truy vấn cho admin.courses.index — "Khóa & Lớp" (8.1: Khóa học khác Lớp học).
 */
class CourseService
{
    public function __construct(
        private CourseRepositoryInterface $courses,
        private ClassRoomRepositoryInterface $classRooms,
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
}
