<?php

namespace App\Services\Student;

use App\Models\User;
use App\Repositories\Contracts\ClassEnrollmentRepositoryInterface;
use App\Repositories\Contracts\ClassSessionRepositoryInterface;

/** STU-02 — danh sách lớp học sinh đang tham gia. */
class CourseService
{
    public function __construct(
        private ClassEnrollmentRepositoryInterface $classEnrollments,
        private ClassSessionRepositoryInterface $classSessions,
    ) {}

    public function activeClassesForUser(User $user): array
    {
        $enrollments = $this->classEnrollments
            ->activeForUser($user->id, ['classRoom.course', 'classRoom.teachers']);

        $classRoomIds = $enrollments->map(fn ($e) => $e->classRoom?->id)->filter()->unique()->values()->all();
        $sessionProgressByClassRoom = empty($classRoomIds)
            ? collect()
            : $this->classSessions->sessionProgressCountsForClassRoomIds($classRoomIds)->keyBy('class_room_id');

        return $enrollments
            ->map(function ($enrollment) use ($sessionProgressByClassRoom) {
                $classRoom = $enrollment->classRoom;
                $teacher = $classRoom->teachers->first();
                $progress = $sessionProgressByClassRoom->get($classRoom->id);

                return [
                    'id' => $classRoom->id,
                    'course' => $classRoom->course->title ?? '',
                    'class' => $classRoom->name,
                    'teacher' => $teacher ? 'GV '.$teacher->name : 'Chưa phân công',
                    // "% tiến độ lớp" — % buổi học đã kết thúc / tổng số buổi đã lên lịch,
                    // CÙNG công thức đã dùng thật ở
                    // App\Services\Teacher\ClassRoomService::completionPercent() (xem giải
                    // thích đầy đủ ở đó). Trước đây hardcode cứng 0 bất kể lớp nào.
                    'percent' => $this->completionPercent((int) ($progress->ended ?? 0), (int) ($progress->total ?? 0)),
                    'nextSession' => null, // TODO: cần bảng class_sessions sắp tới gần nhất.
                ];
            })->values()->all();
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
