<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface CompetitionExamRepositoryInterface extends BaseRepositoryInterface
{
    /** Kỳ thi của 1 cuộc thi, xếp theo order, kèm assessment. */
    public function forCompetition(int $competitionId): Collection;

    /** Toàn bộ kỳ thi, kèm competition + đếm số lượt xếp hạng — cho admin.ranking.index. */
    public function allWithCounts(): Collection;
}
