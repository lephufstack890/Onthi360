<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface TokenTopupRepositoryInterface extends BaseRepositoryInterface
{
    /** Lịch sử yêu cầu nạp token của 1 người dùng, mới nhất trước (access.wallet.index). */
    public function forUser(int $userId, int $limit = 50): Collection;

    /** admin.orders.index — yêu cầu "Chờ duyệt" lên đầu, rồi tới các yêu cầu đã xử lý gần đây. */
    public function pendingAndRecent(int $limit = 20): Collection;
}
