<?php

namespace App\Repositories\Eloquent;

use App\Models\DraftQuestion;
use App\Repositories\Contracts\DraftQuestionRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class DraftQuestionRepository extends EloquentRepository implements DraftQuestionRepositoryInterface
{
    protected string $modelClass = DraftQuestion::class;

    public function countPendingReview(): int
    {
        return $this->query()->where('review_status', 'pending')->count();
    }
}
