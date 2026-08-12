<?php

namespace App\Repositories\Contracts;

use App\Models\Question;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface QuestionRepositoryInterface extends BaseRepositoryInterface
{

    public function byOwner(int $ownerId, ?string $status = null, int $limit = 50): Collection;

    public function countByOwner(int $ownerId, ?string $status = null): int;

    public function sharedLatestWithOwner(int $limit = 50): Collection;

    public function countShared(): int;
}
