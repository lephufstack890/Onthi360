<?php

namespace App\Repositories\Contracts;

use App\Models\Attendance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface AttendanceRepositoryInterface extends BaseRepositoryInterface
{

    public function countPresentForStudentInClassRoom(int $studentId, int $classRoomId): int;

    public function forStudentInClassRoom(int $studentId, int $classRoomId, int $limit = 20): Collection;
}
