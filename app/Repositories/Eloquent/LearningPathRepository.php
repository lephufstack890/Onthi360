<?php

namespace App\Repositories\Eloquent;

use App\Enums\ContentStatus;
use App\Models\LearningPath;
use App\Repositories\Contracts\LearningPathRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class LearningPathRepository extends EloquentRepository implements LearningPathRepositoryInterface
{
    protected string $modelClass = LearningPath::class;

    public function allForAdmin(): Collection
    {
        return $this->query()
            ->with(['courses'])
            ->withCount('courses')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();
    }

    public function findWithCourses(int $id): ?LearningPath
    {
        return $this->query()->with(['courses'])->find($id);
    }

    public function findBySlugWithCourses(string $slug): ?LearningPath
    {
        return $this->query()->with(['courses'])->where('slug', $slug)->first();
    }

    public function publishedWithCourses(): Collection
    {
        return $this->query()
            ->with(['courses'])
            ->where('status', ContentStatus::Published->value)
            ->orderBy('sort_order')
            ->orderBy('grade_from')
            ->get();
    }

    public function maxSortOrder(): int
    {
        return (int) $this->query()->max('sort_order');
    }

    public function countsByStatus(): array
    {
        $counts = $this->query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $result = [];

        foreach (ContentStatus::cases() as $case) {
            $result[$case->value] = (int) ($counts[$case->value] ?? 0);
        }

        return $result;
    }
}
