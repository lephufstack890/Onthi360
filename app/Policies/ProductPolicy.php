<?php

namespace App\Policies;

use App\Enums\OwnerType;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;

class ProductPolicy
{
    /** Xuất bản vào kho chung — chỉ Editor/Admin, hoặc giáo viên được cấp quyền riêng (5.-, 6.5). */
    public function publish(User $user, Product $product): bool
    {
        if ($user->hasAnyRole(Role::EDITOR, Role::ADMIN, Role::SUPER_ADMIN)) {
            return true;
        }

        return $product->owner_type === OwnerType::Teacher
            && $product->owner_id === $user->id
            && $user->isTeacherApproved();
    }

    public function update(User $user, Product $product): bool
    {
        if ($user->hasAnyRole(Role::EDITOR, Role::ADMIN, Role::SUPER_ADMIN)) {
            return true;
        }

        return $product->owner_type === OwnerType::Teacher && $product->owner_id === $user->id;
    }
}
