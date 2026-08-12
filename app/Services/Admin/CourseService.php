<?php

namespace App\Services\Admin;

use App\Repositories\Contracts\ClassRoomRepositoryInterface;
use App\Repositories\Contracts\CourseRepositoryInterface;

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
}
