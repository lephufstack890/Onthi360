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

    /**
     * SỬA 25/8 (2) — "cái nào đã đặt rồi thì không được đặt nữa": có đơn nào của $buyerId cho
     * đúng $productId + $scope còn đang xử lý (chưa Rejected/Canceled/Refunded) không.
     */
    public function hasOpenOrderForProduct(int $buyerId, int $productId, string $scope): bool;

    /** SỬA 25/8 (2) — "lưu lại lịch sử đặt mua có học sinh": toàn bộ Order do $buyerId đặt, mới nhất trước. */
    public function forBuyerWithItems(int $buyerId, int $limit = 50): Collection;
}
