<?php

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Hồ sơ + đổi mật khẩu cho tài khoản Admin (ACC-01/ACC-02 áp cho khu Admin). */
class ProfileService
{
    /** Lưu thông tin hồ sơ cơ bản (tên, số điện thoại). Email không cho tự đổi ở đây. */
    public function updateProfile(User $user, array $data): User
    {
        $user->update($data);

        return $user;
    }

    /**
     * Đổi mật khẩu — luôn yêu cầu đúng mật khẩu hiện tại trước khi đổi (không tin
     * client chỉ vì đã đăng nhập/có session — 16 mục 3), tránh trường hợp lộ
     * session vẫn đổi được mật khẩu người khác không biết.
     *
     * @throws ValidationException nếu mật khẩu hiện tại không đúng.
     */
    public function updatePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Mật khẩu hiện tại không đúng.',
            ]);
        }

        $user->update(['password' => Hash::make($newPassword)]);
    }
}
