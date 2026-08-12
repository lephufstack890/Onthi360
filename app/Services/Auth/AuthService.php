<?php

namespace App\Services\Auth;

use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Nghiệp vụ đăng nhập/đăng ký (ACC-01). Tách khỏi AuthController để
 * controller chỉ còn validate request + gọi service + redirect/render.
 */
class AuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    public function attempt(array $credentials, bool $remember): bool
    {
        return Auth::attempt($credentials, $remember);
    }

    public function registerStudent(array $data): User
    {
        $user = $this->userRepository->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        // TODO: gán role ban đầu theo lựa chọn thật của người dùng (3.1);
        // mặc định tạm gán Học sinh để không tạo user không có role nào.
        $user->assignRole(Role::STUDENT);

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
