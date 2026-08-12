<?php

namespace App\Repositories\Contracts;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface OrderRepositoryInterface extends BaseRepositoryInterface
{

    public function countByStatuses(array $statuses): int;

    public function withBuyerAndItems(int $id): ?Order;

    public function filteredWithBuyerAndItems(?array $statuses, int $limit = 50): Collection;
}
