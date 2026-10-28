<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable // implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'phone', 'province', 'region', 'token_balance', 'password', 'locale', 'avatar_path', 'status',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'token_balance' => 'integer',
        ];
    }

    /** SỬA 9/10 — URL ảnh đại diện (users.avatar_path), null nếu chưa có. Xem Account\AvatarService. */
    public function avatarUrl(): ?string
    {
        return \App\Services\Account\AvatarService::url($this->avatar_path);
    }

    // -- Hồ sơ theo vai trò --------------------------------------------------

    public function teacherProfile()
    {
        return $this->hasOne(TeacherProfile::class);
    }

    /** Các liên kết mà user này là PHỤ HUYNH của học sinh khác. */
    public function childLinks(): HasMany
    {
        return $this->hasMany(ParentLink::class, 'parent_user_id');
    }

    /** Các liên kết mà user này là HỌC SINH được phụ huynh nào đó liên kết tới. */
    public function parentLinks(): HasMany
    {
        return $this->hasMany(ParentLink::class, 'student_user_id');
    }

    // -- Lớp / khóa -----------------------------------------------------------

    /** Lớp mà user này là giáo viên phụ trách hoặc đồng phụ trách (7.2). */
    public function classRoomsTeaching(): BelongsToMany
    {
        return $this->belongsToMany(ClassRoom::class, 'class_teachers')->withPivot('role')->withTimestamps();
    }

    public function classEnrollments(): HasMany
    {
        return $this->hasMany(ClassEnrollment::class, 'student_id');
    }

    // -- Quyền / thương mại -----------------------------------------------------

    public function accessRights(): HasMany
    {
        return $this->hasMany(AccessRight::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'buyer_id');
    }

    /** Yêu cầu nạp token của user này (App\Services\WalletService — note họp 13/8, mục 7-8). */
    public function tokenTopups(): HasMany
    {
        return $this->hasMany(TokenTopup::class);
    }

    // -- Làm bài / đánh giá -----------------------------------------------------

    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'reviewer_id');
    }

    public function isTeacherApproved(): bool
    {
        // SỬA 15/10 — admin đang "xem như Giáo viên" dùng được đủ chức năng giáo viên (tạo lớp…) dù không có hồ sơ GV.
        if ($this->actingRole() === Role::TEACHER) {
            return true;
        }

        return $this->teacherProfile?->isApproved() ?? false;
    }

    // -- SỬA 15/10 (khách: "admin có nút chuyển qua giáo viên, giao diện thành giáo viên, chuyển qua lại được; CHỈ admin")

    /** Khoá session lưu chế độ đang xem. Chỉ ActingRoleController ghi khoá này, và chỉ sau khi kiểm tra là admin thật. */
    public const ACTING_ROLE_SESSION_KEY = 'acting_role';

    /** Các vai trò admin được phép "xem như". Hiện chỉ Giáo viên. */
    public const ACTING_ROLES = [Role::TEACHER];

    private ?bool $realAdminCache = null;

    /**
     * Có PHẢI admin thật trong CSDL không — đọc thẳng bảng vai trò, KHÔNG qua hasRole() (hasRole() bị chế độ "xem như"
     * che vai trò admin đi). Đây là chốt chặn duy nhất quyết định ai được bật/tắt chế độ xem như.
     */
    public function isRealAdmin(): bool
    {
        if ($this->realAdminCache !== null) {
            return $this->realAdminCache;
        }

        $names = [Role::ADMIN, Role::SUPER_ADMIN];

        return $this->realAdminCache = $this->relationLoaded('roles')
            ? $this->roles->whereIn('name', $names)->isNotEmpty()
            : $this->roles()->whereIn('name', $names)->exists();
    }

    /**
     * Vai trò admin đang "xem như" (null = bình thường). Chỉ có hiệu lực khi đồng thời:
     *   · đây là CHÍNH người đang đăng nhập của request hiện tại (đối tượng User khác — ví dụ danh sách người dùng —
     *     không bao giờ bị ảnh hưởng);
     *   · session của request có khoá ACTING_ROLE_SESSION_KEY với giá trị hợp lệ;
     *   · người đó là admin thật trong CSDL (isRealAdmin) — người thường có tự đặt khoá cũng vô hiệu.
     * Chạy trong lệnh console / hàng đợi (không có request/session) thì luôn null.
     */
    public function actingRole(): ?string
    {
        try {
            if (! $this->exists || (app()->runningInConsole() && ! app()->runningUnitTests())) {
                return null;
            }
            $request = request();
            if (! $request->hasSession() || ! auth()->hasUser() || (int) auth()->id() !== (int) $this->getKey()) {
                return null;
            }
            $role = $request->session()->get(self::ACTING_ROLE_SESSION_KEY);
        } catch (\Throwable $e) {
            return null;
        }

        return is_string($role) && in_array($role, self::ACTING_ROLES, true) && $this->isRealAdmin() ? $role : null;
    }

    // -- Vai trò (RBAC tối giản, tự viết — xem docs/ARCHITECTURE.md) -----------

    /**
     * Một user có thể có NHIỀU role đồng thời (vd: vừa dạy vừa là phụ huynh) —
     * đúng yêu cầu "role switcher" ở 4.3. Danh sách role hợp lệ: xem App\Models\Role.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasRole(string $roleName): bool
    {
        // SỬA 15/10 — admin đang "xem như Giáo viên": coi như CÓ vai trò giáo viên và KHÔNG có vai trò quản trị/biên tập,
        // để mọi chỗ (menu, trang chủ khu làm việc, chặn route, quyền đọc tài liệu…) chạy đúng như một giáo viên.
        $acting = $this->actingRole();
        if ($acting !== null) {
            if ($roleName === $acting) {
                return true;
            }
            if (in_array($roleName, [Role::ADMIN, Role::SUPER_ADMIN, Role::EDITOR], true)) {
                return false;
            }
        }

        return $this->relationLoaded('roles')
            ? $this->roles->contains('name', $roleName)
            : $this->roles()->where('name', $roleName)->exists();
    }

    public function hasAnyRole(string ...$roleNames): bool
    {
        foreach ($roleNames as $name) {
            if ($this->hasRole($name)) {
                return true;
            }
        }

        return false;
    }

    public function assignRole(string $roleName): void
    {
        $role = Role::where('name', $roleName)->firstOrFail();
        $this->roles()->syncWithoutDetaching([$role->id]);
    }

    /**
     * Kiểm tra quyền chi tiết theo action, gắn qua role (không gắn thẳng vào user).
     * Super Admin luôn có mọi quyền — bypass ở đây thay vì phải seed permission cho
     * riêng role này, tránh quên seed khi thêm permission mới (đúng nguyên tắc "an
     * toàn theo mặc định": Super Admin không bao giờ bị khoá nhầm quyền).
     */
    public function hasPermission(string $slug): bool
    {
        if ($this->hasRole(Role::SUPER_ADMIN)) {
            return true;
        }

        // Phân quyền riêng của từng người (Admin > Người dùng > tab "Phân quyền") đè lên mặc định theo vai trò:
        // revoke = thu hồi, grant = cấp thêm. Không có dòng nào thì dùng mặc định như cũ.
        $override = $this->permissionOverrideFor($slug);
        if ($override !== null) {
            return $override['effect'] === 'grant';
        }

        // 11 quyền chức năng giáo viên (slug "teacher."): mặc định có nếu người đó có vai trò giáo viên.
        if (str_starts_with($slug, 'teacher.') && $this->hasRole(Role::TEACHER)) {
            return true;
        }

        // SỬA 28/10 — quyền khu quản trị (slug "admin.", App\Support\AdminPermissions): Admin mặc định có hết; Editor chỉ có
        // khi được cấp riêng (đã xét ở phần ghi đè phía trên).
        if (str_starts_with($slug, 'admin.')) {
            return $this->hasRole(Role::ADMIN);
        }

        $roles = $this->relationLoaded('roles') ? $this->roles : $this->roles()->with('permissions')->get();

        $acting = $this->actingRole();
        foreach ($roles as $role) {
            // SỬA 15/10 — đang "xem như Giáo viên" thì không mang theo quyền của vai trò quản trị/biên tập.
            if ($acting !== null && in_array($role->name, [Role::ADMIN, Role::SUPER_ADMIN, Role::EDITOR], true)) {
                continue;
            }
            if ($role->relationLoaded('permissions')
                ? $role->permissions->contains('slug', $slug)
                : $role->permissions()->where('slug', $slug)->exists()) {
                return true;
            }
        }

        return false;
    }

    public function hasAnyPermission(string ...$slugs): bool
    {
        foreach ($slugs as $slug) {
            if ($this->hasPermission($slug)) {
                return true;
            }
        }

        return false;
    }

    // -- Phân quyền theo từng người (permission_user) -------------------------------

    /** Cache theo từng đối tượng: [slug => ['effect' => grant|revoke, 'scope' => own|class|all]]. */
    private ?array $permissionOverrideMap = null;

    /** Đã chạy migration permission_user chưa — tránh lỗi 500 toàn site nếu quên migrate. */
    private static ?bool $overrideTableExists = null;

    public function permissionOverrides(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_user')->withPivot(['effect', 'scope', 'granted_by'])->withTimestamps();
    }

    /** @return array<string, array{effect: string, scope: string}> */
    public function permissionOverrideMap(): array
    {
        if ($this->permissionOverrideMap !== null) {
            return $this->permissionOverrideMap;
        }

        self::$overrideTableExists ??= \Illuminate\Support\Facades\Schema::hasTable('permission_user');
        if (! self::$overrideTableExists || ! $this->exists) {
            return $this->permissionOverrideMap = [];
        }

        $map = [];
        foreach ($this->permissionOverrides()->get(['permissions.id', 'permissions.slug']) as $p) {
            $map[$p->slug] = ['effect' => $p->pivot->effect, 'scope' => $p->pivot->scope];
        }

        return $this->permissionOverrideMap = $map;
    }

    /** @return array{effect: string, scope: string}|null */
    public function permissionOverrideFor(string $slug): ?array
    {
        return $this->permissionOverrideMap()[$slug] ?? null;
    }

    /** Admin đã thu hồi riêng quyền này của người dùng (dùng để chặn thao tác, vd. Teacher\ClassRoomController). */
    public function isPermissionRevoked(string $slug): bool
    {
        if ($this->hasRole(Role::SUPER_ADMIN)) {
            return false;
        }

        return ($this->permissionOverrideFor($slug)['effect'] ?? null) === 'revoke';
    }

    /** Phạm vi áp dụng của quyền: own (thuộc sở hữu) | class (lớp phụ trách) | all. Mặc định own. */
    public function permissionScope(string $slug): string
    {
        return $this->permissionOverrideFor($slug)['scope'] ?? 'own';
    }
}
