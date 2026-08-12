<?php

namespace App\Repositories\Eloquent;

use App\Models\ClassRoom;
use App\Repositories\Contracts\ClassRoomRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
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
            ->withCount(['students' => fn (Builder $q) => $q->wherePivot('status', 'active')])
            ->latest()
            ->limit($limit)
            ->get();
    }
}
