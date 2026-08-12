<?php

namespace App\Repositories\Eloquent;

use App\Models\ClassRoom;
use App\Repositories\Contracts\ClassRoomRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ClassRoomRepository extends EloquentRepository implements ClassRoomRepositoryInterface
{
    protected string $modelClass = ClassRoom::class;

    public function findWithCourse(int $id): ?ClassRoom
    {
        return $this->query()->with('course')->find($id);
    }

    public function findWithCourseAndTeachers(int $id): ?ClassRoom
    {
        return $this->query()->with(['course', 'teachers'])->find($id);
    }

    public function latestWithCourseTeachersAndStudentCount(int $limit = 50): Collection
    {
        return $this->query()
            ->with(['course', 'teachers'])
            // students() relation đã tự lọc wherePivot('status','active') trong định nghĩa
            // (App\Models\ClassRoom) — không lặp lại điều kiện đó trong closure ở đây, vì gọi
            // wherePivot() lần 2 trong withCount() làm Laravel dựng sai SQL cho subquery đếm
            // (cột 'pivot' bị hiểu nhầm — đã tự kiểm chứng bằng lỗi SQLSTATE[42S22] thực tế).
            ->withCount('students')
            ->latest()
            ->limit($limit)
            ->get();
    }
}
