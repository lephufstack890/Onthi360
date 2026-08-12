<?php

namespace App\Services\Auth;

use App\Enums\TeacherApprovalStatus;
use App\Models\Role;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

/**
 * Nghiệp vụ đăng nhập/đăng ký (ACC-01). Tách khỏi AuthController để
 * controller chỉ còn validate request + gọi service + redirect/render.
 */
class AuthService
{
    /**
     * Vai trò công khai được TỰ đăng ký (3.1) — Admin/Editor/Super Admin KHÔNG
     * bao giờ được phép tự đăng ký, chỉ Super Admin thêm được qua khu quản trị
     * (App\Services\Admin\UserService). Chặn ở đây (tầng service) thay vì chỉ ở
     * UI, để không ai tạo được admin bằng cách gửi thẳng request tới route đăng ký.
     */
    public const SELF_REGISTERABLE_ROLES = [Role::STUDENT, Role::PARENT, Role::TEACHER];

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    public function attempt(array $credentials, bool $remember): bool
    {
        return Auth::attempt($credentials, $remember);
    }

    /**
     * Đăng ký công khai theo vai trò do người dùng chọn (3.1). Giáo viên đi
     * thẳng vào luồng 3.3 "Chưa đăng ký -> Chờ duyệt" — tài khoản tạo được
     * ngay nhưng phải chờ Admin duyệt hồ sơ trước khi mua/kích hoạt quyền dạy
     * và gắn học liệu riêng tư vào lớp.
     *
     * @param  array{name: string, email: string, password: string, subjects?: string, bio?: string}  $data
     */
    public function register(array $data, string $role): User
    {
        if (! in_array($role, self::SELF_REGISTERABLE_ROLES, true)) {
            throw new InvalidArgumentException("Vai trò [$role] không được phép tự đăng ký.");
        }

        $user = $this->userRepository->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $user->assignRole($role);

        if ($role === Role::TEACHER) {
            $subjects = trim($data['subjects'] ?? '');

            TeacherProfile::create([
                'user_id' => $user->id,
                'bio' => $data['bio'] ?? null,
                'subjects' => $subjects !== '' ? array_map('trim', explode(',', $subjects)) : [],
                'approval_status' => TeacherApprovalStatus::Pending,
            ]);
        }

        return $user;
    }

    public function login(User $user): void
    {
        Auth::login($user);
    }

    public function logout(): void
    {
        Auth::logout();
    }
}
