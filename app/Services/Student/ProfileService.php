<?php

namespace App\Services\Student;

use App\Models\User;
use App\Repositories\Contracts\ParentLinkRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/** STU-11 — hồ sơ + liên kết phụ huynh. */
class ProfileService
{
    public function __construct(
        private ParentLinkRepositoryInterface $parentLinks,
    ) {}

    /**
     * Liên kết phụ huynh của CHÍNH học sinh này (student_user_id) — chiều ngược lại với
     * các phương thức có sẵn của repo (đều theo parent_user_id), nên dùng query() (van an
     * toàn của repo) thay vì thêm phương thức riêng cho một biến thể.
     */
    public function parentLinksForUser(User $user): Collection
    {
        return $this->parentLinks->query()
            ->where('student_user_id', $user->id)
            ->with('parent')
            ->get();
    }

    /** Lưu thông tin hồ sơ cơ bản (tên, số điện thoại). Email không cho tự đổi ở đây. */
    public function updateProfile(User $user, array $data): User
    {
        $user->update($data);

        return $user;
    }
}
