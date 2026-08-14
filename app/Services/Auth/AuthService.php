<?php

namespace App\Services\Auth;

use App\Enums\TeacherApprovalStatus;
use App\Models\Role;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    /**
     * "Mỗi học sinh chỉ được đăng nhập trên 1 máy" (note họp 13/8, mục 7 — yêu cầu bảo mật).
     * Dùng SESSION_DRIVER=database (bảng "sessions" có cột user_id, xem migration
     * 0001_01_01_000000_create_users_table.php) — đăng nhập ở máy mới xoá luôn mọi session
     * khác của CÙNG user_id đó, khiến các máy cũ tự động bị đăng xuất ở request kế tiếp
     * (payload session không còn tồn tại). Chỉ áp cho vai trò Học sinh theo đúng phạm vi
     * note yêu cầu — Giáo viên/Phụ huynh/Admin vẫn đăng nhập nhiều thiết bị bình thường.
     *
     * Lưu ý phạm vi: không xử lý cookie "remember me" của máy cũ (recaller token) — nếu
     * học sinh có bật "Ghi nhớ đăng nhập" ở máy cũ, máy đó có thể tự đăng nhập lại bằng
     * cookie đó. Việc rotate remember_token ở đây bị bỏ qua có chủ đích vì nó cũng sẽ vô
     * hiệu hoá luôn cookie remember vừa được cấp cho chính lượt đăng nhập hiện tại (thứ tự
     * Auth::attempt() → session()->regenerate() → hàm này chạy sau khi cookie đã gửi đi).
     */
    public function enforceSingleDeviceForStudents(User $user, string $currentSessionId): void
    {
        if (! $user->hasRole(Role::STUDENT)) {
            return;
        }

        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentSessionId)
            ->delete();
    }
}
