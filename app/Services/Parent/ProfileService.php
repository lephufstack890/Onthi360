<?php

namespace App\Services\Parent;

use App\Models\User;
use App\Repositories\Contracts\ParentLinkRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/** PAR — hồ sơ phụ huynh + danh sách con đã liên kết (chiều ngược lại với Student\ProfileService). */
class ProfileService
{
    public function __construct(
        private ParentLinkRepositoryInterface $parentLinks,
    ) {}

    /** Con đã liên kết của CHÍNH phụ huynh này (mọi trạng thái — để họ theo dõi yêu cầu đang chờ). */
    public function linkedChildrenForUser(User $user): Collection
    {
        return $this->parentLinks->query()
            ->where('parent_user_id', $user->id)
            ->with('student')
            ->get();
    }

    /** Lưu thông tin hồ sơ cơ bản (tên, số điện thoại, tỉnh thành/khu vực). Email không cho tự đổi ở đây. */
    public function updateProfile(User $user, array $data): User
    {
        $user->update($data);

        return $user;
    }
}
