<?php

namespace App\Repositories\Contracts;

use App\Models\ActivationCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface ActivationCodeRepositoryInterface extends BaseRepositoryInterface
{

    public function latestWithOrderItemOrder(int $limit = 50): Collection;
}
