<?php

namespace App\Repositories\Eloquent;

use App\Models\Course;
use App\Repositories\Contracts\CourseRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class CourseRepository extends EloquentRepository implements CourseRepositoryInterface
{
    protected string $modelClass = Course::class;

    public function withClassRoomCount(int $limit = 50): Collection
    {
        return $this->query()->withCount('classRooms')->latest()->limit($limit)->get();
    }
}
