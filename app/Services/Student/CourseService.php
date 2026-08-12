<?php

namespace App\Services\Student;

use App\Models\User;
use App\Repositories\Contracts\ClassEnrollmentRepositoryInterface;

/** STU-02 — danh sách lớp học sinh đang tham gia. */
class CourseService
{
    public function __construct(
        private ClassEnrollmentRepositoryInterface $classEnrollments,
    ) {}

    public function activeClassesForUser(User $user): array
    {
        return $this->classEnrollments
            ->activeForUser($user->id, ['classRoom.course', 'classRoom.teachers'])
            ->map(function ($enrollment) {
                $classRoom = $enrollment->classRoom;
                $teacher = $classRoom->teachers->first();

                return [
                    'id' => $classRoom->id,
                    'course' => $classRoom->course->title ?? '',
                    'class' => $classRoom->name,
                    'teacher' => $teacher ? 'GV '.$teacher->name : 'Chưa phân công',
                    // TODO: % tiến độ thật cần công thức tổng hợp progress_unlocks + attempts theo lớp.
                    'percent' => 0,
                    'nextSession' => null, // TODO: cần bảng class_sessions sắp tới gần nhất.
                ];
            })->values()->all();
    }
}
