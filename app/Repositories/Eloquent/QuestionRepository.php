<?php

namespace App\Repositories\Eloquent;

use App\Models\Question;
use App\Repositories\Contracts\QuestionRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class QuestionRepository extends EloquentRepository implements QuestionRepositoryInterface
{
    protected string $modelClass = Question::class;

    public function byOwner(int $ownerId, ?string $status = null, int $limit = 50): Collection
    {
        $query = $this->query()->where('owner_id', $ownerId);

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->latest()->limit($limit)->get();
    }

    public function countByOwner(int $ownerId, ?string $status = null): int
    {
        $query = $this->query()->where('owner_id', $ownerId);

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->count();
    }

    public function sharedLatestWithOwner(int $limit = 50): Collection
    {
        return $this->query()->with('owner')->where('owner_type', 'shared')->latest()->limit($limit)->get();
    }

    public function countShared(): int
    {
        return $this->query()->where('owner_type', 'shared')->count();
    }
}
