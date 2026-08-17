<?php

namespace App\Repositories\Contracts;

use App\Models\Attendance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface AttendanceRepositoryInterface extends BaseRepositoryInterface
{

    public function countPresentForStudentInClassRoom(int $studentId, int $classRoomId): int;

    public function forStudentInClassRoom(int $studentId, int $classRoomId, int $limit = 20): Collection;

    /** Keyed by student_id — điểm danh đã có của một buổi học cụ thể. */
    public function forClassSession(int $classSessionId): Collection;

    /** Keyed by class_session_id — điểm danh của MỘT học sinh xuyên nhiều buổi học (student.schedule.index). */
    public function forStudentInSessionIds(int $studentId, array $sessionIds): Collection;
}
