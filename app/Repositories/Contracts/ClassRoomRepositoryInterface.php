<?php

namespace App\Repositories\Contracts;

use App\Models\ClassRoom;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface ClassRoomRepositoryInterface extends BaseRepositoryInterface
{

    public function findWithCourse(int $id): ?ClassRoom;

    public function findWithCourseAndTeachers(int $id): ?ClassRoom;

    public function latestWithCourseTeachersAndStudentCount(int $limit = 50): Collection;
}
