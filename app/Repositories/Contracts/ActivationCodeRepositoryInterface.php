<?php

namespace App\Repositories\Contracts;

use App\Models\ActivationCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface ActivationCodeRepositoryInterface extends BaseRepositoryInterface
{

    public function latestWithOrderItemOrder(int $limit = 50, bool $withAssignedUser = true): Collection;

    /**
     * SỬA 18/9 — mã CHƯA dùng đang gán cho đúng (tài khoản, tài liệu, phạm vi) này, hoặc null.
     * Xem Admin\ActivationCodeService::store() — chặn cấp chồng mã cho cùng một người.
     */
    public function unusedFor(int $assignedUserId, int $productId, string $scope): ?ActivationCode;
}
