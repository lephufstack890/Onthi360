<?php

namespace App\Repositories\Contracts;

use App\Models\DraftQuestion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface DraftQuestionRepositoryInterface extends BaseRepositoryInterface
{

    public function countPendingReview(): int;
}
