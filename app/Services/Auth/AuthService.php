<?php

namespace App\Services\Auth;

use App\Enums\TeacherApprovalStatus;
use App\Models\Role;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Models\RegistrationVerification;
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
     * SỬA 11/9 — giao diện đăng nhập mới (education-main/src/components/AccessCenterModal.jsx)
     * cho chọn Email HOẶC Số điện thoại. Cột users.phone đã có sẵn (nullable + unique, xem
     * migration add_profile_fields_to_users_table) nên chỉ cần chọn đúng cột để đối chiếu,
     * không phải đổi cấu trúc bảng.
     *
     * Nhận dạng theo NỘI DUNG người dùng gõ chứ không theo tab họ đang chọn: gõ email vào tab
     * số điện thoại vẫn đăng nhập được, đỡ một lỗi vặt rất hay gặp.
     */
    public function attemptByIdentifier(string $identifier, string $password, bool $remember): bool
    {
        $identifier = trim($identifier);
        $field = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false ? 'email' : 'phone';

        if ($field === 'phone') {
            $identifier = self::normalizePhone($identifier);
        }

        return Auth::attempt([$field => $identifier, 'password' => $password], $remember);
    }

    /**
     * Bỏ khoảng trắng, dấu chấm/gạch và đưa +84 về 0 để "098 123 4567", "098-123-4567" và
     * "+84981234567" cùng khớp một số đã lưu. Không tự ý đổi gì khác.
     */
    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/[^0-9+]/', '', $phone) ?? '';

        if (str_starts_with($digits, '+84')) {
            $digits = '0'.substr($digits, 3);
        } elseif (str_starts_with($digits, '84') && strlen($digits) > 9) {
            $digits = '0'.substr($digits, 2);
        }

        return $digits;
    }

    /**
     * SỬA 11/9 — vá một lỗ hổng có thật: Admin có thể khoá tài khoản (users.status=suspended,
     * xem Admin\UserController) nhưng luồng đăng nhập TRƯỚC ĐÂY không hề kiểm tra cột đó, nên
     * người bị khoá vẫn đăng nhập và dùng hệ thống bình thường. Giờ đăng nhập xong mà tài
     * khoản đang bị khoá thì đăng xuất ngay và báo rõ lý do.
     */
    public function isSuspended(?User $user): bool
    {
        return $user !== null && $user->status === 'suspended';
    }

    /**
     * Tạo tài khoản thật từ bản ghi đăng ký tạm đã qua xác minh (bước 3 của luồng đăng ký).
     * Mật khẩu trong bản ghi tạm ĐÃ băm từ bước 1 nên truyền thẳng, không băm lại.
     *
     * @param  array{subjects?: ?string, bio?: ?string}  $extra
     */
    public function registerFromPending(RegistrationVerification $pending, string $role, array $extra = []): User
    {
        if (! in_array($role, self::SELF_REGISTERABLE_ROLES, true)) {
            throw new InvalidArgumentException("Vai trò [$role] không được phép tự đăng ký.");
        }

        $user = $this->userRepository->create([
            'name' => $pending->name,
            'email' => $pending->email,
            'phone' => $pending->phone,
            'password' => $pending->password,
            'email_verified_at' => $pending->verified_at,
        ]);

        $user->assignRole($role);

        if ($role === Role::TEACHER) {
            $this->createTeacherProfile($user, $extra);
        }

        return $user;
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
            // SỬA 11/9 — trước đây số điện thoại người dùng nhập lúc đăng ký bị BỎ QUA ở đây
            // (chỉ lưu name/email/password), nên sau khi đăng ký xong họ không đăng nhập được
            // bằng số điện thoại vừa khai. Giờ lưu đúng vào cột users.phone (nullable+unique).
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
        ]);

        $user->assignRole($role);

        if ($role === Role::TEACHER) {
            $this->createTeacherProfile($user, $data);
        }

        return $user;
    }

    /**
     * Hồ sơ giáo viên luôn tạo ở trạng thái "Chờ duyệt" (3.3) — tài khoản dùng được ngay
     * nhưng chưa mở lớp/mua quyền dạy được cho tới khi Admin duyệt.
     *
     * @param  array{subjects?: ?string, bio?: ?string}  $data
     */
    private function createTeacherProfile(User $user, array $data): void
    {
        $subjects = trim((string) ($data['subjects'] ?? ''));

        TeacherProfile::create([
            'user_id' => $user->id,
            'bio' => $data['bio'] ?? null,
            'subjects' => $subjects !== '' ? array_map('trim', explode(',', $subjects)) : [],
            'approval_status' => TeacherApprovalStatus::Pending,
        ]);
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
