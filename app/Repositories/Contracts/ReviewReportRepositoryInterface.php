<?php

namespace App\Repositories\Contracts;

use App\Models\ReviewReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface ReviewReportRepositoryInterface extends BaseRepositoryInterface
{

    public function countForPublishedReviews(): int;
}
