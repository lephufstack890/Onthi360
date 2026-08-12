<?php

namespace App\Repositories\Eloquent;

use App\Models\Assessment;
use App\Repositories\Contracts\AssessmentRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class AssessmentRepository extends EloquentRepository implements AssessmentRepositoryInterface
{
    protected string $modelClass = Assessment::class;

    public function publishedPractice(int $limit = 30): Collection
    {
        return $this->query()
            ->where('type', 'practice')
            ->where('status', 'published')
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function countPublishedPractice(): int
    {
        return $this->query()->where('type', 'practice')->where('status', 'published')->count();
    }

    public function withItemsAndQuestions(int $id): ?Assessment
    {
        return $this->query()->with('items.question')->find($id);
    }

    public function latestWithCreator(int $limit = 50): Collection
    {
        return $this->query()->with('creator')->latest()->limit($limit)->get();
    }
}
