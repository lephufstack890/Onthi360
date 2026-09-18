<?php

namespace App\Repositories\Eloquent;

use App\Enums\ActivationCodeStatus;
use App\Models\ActivationCode;
use App\Repositories\Contracts\ActivationCodeRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ActivationCodeRepository extends EloquentRepository implements ActivationCodeRepositoryInterface
{
    protected string $modelClass = ActivationCode::class;

    public function latestWithOrderItemOrder(int $limit = 50, bool $withAssignedUser = true): Collection
    {
        // SỬA 18/9 — nạp kèm sản phẩm + tài khoản được cấp + người đã kích hoạt để bảng mã
        // kích hoạt bên admin trả lời được ngay "mã này của tài liệu nào, cấp cho ai, ai đã
        // dùng" mà không bắn thêm N truy vấn cho mỗi dòng.
        // $withAssignedUser = false khi máy chủ CHƯA chạy migration 000860 (cột assigned_user_id
        // chưa có) — nạp quan hệ đó sẽ lỗi SQL, xem ActivationCode::supportsAssignedUser().
        $relations = ['orderItem.order', 'product', 'activatedBy'];
        if ($withAssignedUser) {
            $relations[] = 'assignedUser';
        }

        return $this->query()->with($relations)->latest()->limit($limit)->get();
    }

    /**
     * SỬA 18/9 — tài khoản này ĐANG có sẵn mã CHƯA dùng cho đúng tài liệu + phạm vi đó chưa.
     * Dùng để chặn admin lỡ tay cấp chồng nhiều mã cho cùng một người (xem
     * Admin\ActivationCodeService::store()) — mã cũ chưa dùng thì đưa lại mã đó là xong.
     */
    public function unusedFor(int $assignedUserId, int $productId, string $scope): ?ActivationCode
    {
        return $this->query()
            ->where('assigned_user_id', $assignedUserId)
            ->where('product_id', $productId)
            ->where('scope', $scope)
            ->where('status', ActivationCodeStatus::Unused->value)
            ->latest()
            ->first();
    }
}
