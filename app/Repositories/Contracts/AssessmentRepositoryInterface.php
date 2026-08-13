<?php

namespace App\Repositories\Contracts;

use App\Models\Assessment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface AssessmentRepositoryInterface extends BaseRepositoryInterface
{

    public function publishedPractice(int $limit = 30): Collection;

    public function countPublishedPractice(): int;

    public function withItemsAndQuestions(int $id): ?Assessment;

    public function latestWithCreator(int $limit = 50): Collection;

    public function byOwner(int $ownerId, int $limit = 50): Collection;
}
