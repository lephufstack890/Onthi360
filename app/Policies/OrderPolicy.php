<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\Role;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $order->buyer_id === $user->id || $user->hasAnyRole(Role::ADMIN, Role::SUPER_ADMIN);
    }

    /** Duyệt/từ chối đơn — chỉ Admin (theo ủy quyền cho Editor có thể mở rộng sau — 3.2). */
    public function approve(User $user): bool
    {
        return $user->hasAnyRole(Role::ADMIN, Role::SUPER_ADMIN);
    }
}
