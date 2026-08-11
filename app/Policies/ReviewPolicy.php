<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\Role;
use App\Models\User;

class ReviewPolicy
{
    public function update(User $user, Review $review): bool
    {
        // Chỉ chủ review được sửa, và chỉ trong hạn 7 ngày (9.2) — Admin KHÔNG được sửa nội
        // dung review của người khác, chỉ được moderate qua moderate().
        return $review->reviewer_id === $user->id && $review->isEditable();
    }

    /** Kiểm duyệt / đăng phản hồi chính thức — chỉ Admin, Editor KHÔNG có quyền này (9.4). */
    public function moderate(User $user): bool
    {
        return $user->hasAnyRole(Role::ADMIN, Role::SUPER_ADMIN);
    }
}
