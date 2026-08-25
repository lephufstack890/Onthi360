<?php

namespace App\Repositories\Eloquent;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class OrderRepository extends EloquentRepository implements OrderRepositoryInterface
{
    protected string $modelClass = Order::class;

    public function countByStatuses(array $statuses): int
    {
        return $this->query()->whereIn('status', $statuses)->count();
    }

    public function withBuyerAndItems(int $id): ?Order
    {
        return $this->query()->with(['buyer', 'items.product'])->find($id);
    }

    public function filteredWithBuyerAndItems(?array $statuses, int $limit = 50): Collection
    {
        $query = $this->query()->with(['buyer', 'items.product']);

        if ($statuses !== null) {
            $query->whereIn('status', $statuses);
        }

        return $query->latest()->limit($limit)->get();
    }

    public function hasOpenOrderForProduct(int $buyerId, int $productId, string $scope): bool
    {
        return $this->query()
            ->where('buyer_id', $buyerId)
            ->whereIn('status', [
                OrderStatus::Created->value,
                OrderStatus::PendingPayment->value,
                OrderStatus::PendingApproval->value,
                OrderStatus::Approved->value,
            ])
            ->whereHas('items', fn ($q) => $q->where('product_id', $productId)->where('scope', $scope))
            ->exists();
    }

    public function forBuyerWithItems(int $buyerId, int $limit = 50): Collection
    {
        return $this->query()
            ->where('buyer_id', $buyerId)
            ->with('items.product')
            ->latest()
            ->limit($limit)
            ->get();
    }
}
