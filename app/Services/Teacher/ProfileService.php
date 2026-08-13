<?php

namespace App\Services\Teacher;

use App\Models\TeacherProfile;
use App\Models\User;
use App\Repositories\Contracts\TeacherProfileRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** teacher.profile.* (Hồ sơ) — thông tin tài khoản + hồ sơ chuyên môn giáo viên (3.3). */
class ProfileService
{
    public function __construct(private readonly TeacherProfileRepositoryInterface $teacherProfiles) {}

    /** @return array{user: User, teacherProfile: ?TeacherProfile} */
    public function showData(User $user): array
    {
        return [
            'user' => $user,
            'teacherProfile' => $this->teacherProfiles->findByUserId($user->id),
        ];
    }

    /** Lưu thông tin tài khoản cơ bản (tên, số điện thoại). Email không cho tự đổi ở đây. */
    public function updateProfile(User $user, array $data): User
    {
        $user->update($data);

        return $user;
    }

    /**
     * Lưu hồ sơ chuyên môn (bio, môn dạy). KHÔNG cho tự sửa approval_status/is_featured/
     * achievement_note ở đây — các trường đó chỉ Admin đổi được qua
     * App\Services\Admin\TeacherApprovalService / FeaturedTeacherService (3.3, 16 mục 3:
     * không tin dữ liệu client tự phong "nổi bật" hay tự duyệt hồ sơ của chính mình).
     */
    public function updateTeacherProfile(User $user, array $data): TeacherProfile
    {
        $profile = $this->teacherProfiles->findByUserId($user->id);
        abort_if($profile === null, 404);

        $subjects = trim($data['subjects'] ?? '');

        $this->teacherProfiles->update($profile, [
            'bio' => $data['bio'] ?? null,
            'subjects' => $subjects !== '' ? array_values(array_filter(array_map('trim', explode(',', $subjects)))) : [],
        ]);

        return $profile->refresh();
    }

    /**
     * Đổi mật khẩu — luôn yêu cầu đúng mật khẩu hiện tại trước khi đổi (không tin
     * client chỉ vì đã đăng nhập/có session — 16 mục 3).
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
