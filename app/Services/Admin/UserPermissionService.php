<?php

namespace App\Services\Admin;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Phân quyền chức năng THEO TỪNG NGƯỜI DÙNG (Admin > Người dùng > Chi tiết > tab "Phân quyền").
 *
 * Mô hình: mặc định theo vai trò + chênh lệch riêng của người đó (bảng permission_user, effect grant/revoke
 * + phạm vi own/class/all) — xem migration 2025_01_01_001160 và App\Models\User::hasPermission().
 * Chỉ nhóm 11 quyền chức năng giáo viên (slug "teacher.") được hiển thị ở đây.
 */
class UserPermissionService
{
    public const GROUPS = [
        'teacher_general' => 'Quyền chung',
        'teacher_content' => 'Bài tập & câu hỏi',
    ];

    public const SCOPES = [
        'own' => 'Thuộc sở hữu',
        'class' => 'Lớp phụ trách',
        'all' => 'Toàn hệ thống',
    ];

    /** Vai trò có hiển thị bảng quyền chức năng. */
    private const ELIGIBLE_ROLES = [Role::TEACHER, Role::EDITOR, Role::ADMIN, Role::SUPER_ADMIN];

    /** Vai trò mà mặc định có sẵn nhóm teacher.* — chỉ giáo viên. */
    private const DEFAULT_ON_ROLE = Role::TEACHER;

    private static ?bool $tableReady = null;

    private function tableReady(): bool
    {
        return self::$tableReady ??= \Illuminate\Support\Facades\Schema::hasTable('permission_user');
    }

    /**
     * @return array{
     *   mode: string, note: ?string, readonly: bool, groups: array<int, array{key: string, label: string, items: array}>,
     *   total: int, granted: int
     * }
     */
    public function matrix(User $target, ?User $actor = null): array
    {
        $roleNames = $target->roles->pluck('name')->all();
        $isSuper = in_array(Role::SUPER_ADMIN, $roleNames, true);
        $eligible = count(array_intersect($roleNames, self::ELIGIBLE_ROLES)) > 0;

        $base = ['mode' => 'matrix', 'note' => null, 'readonly' => false, 'groups' => [], 'total' => 0, 'granted' => 0];

        if (! $eligible) {
            return ['mode' => 'none', 'note' => 'Học sinh và phụ huynh dùng đúng quyền mặc định của vai trò — không có chức năng nào cần cấp riêng.'] + $base;
        }

        $perms = Permission::query()->where('slug', 'like', 'teacher.%')->orderBy('id')->get();
        if ($perms->isEmpty()) {
            return ['mode' => 'none', 'note' => 'Chưa có danh mục quyền chức năng. Chạy "php artisan migrate" để tạo.'] + $base;
        }

        $overrides = $this->tableReady() ? $target->permissionOverrideMap() : [];
        $isTeacher = in_array(self::DEFAULT_ON_ROLE, $roleNames, true);

        if ($isSuper) {
            $base['readonly'] = true;
            $base['note'] = 'Super Admin luôn có đầy đủ mọi quyền và không thể thu hồi từng quyền.';
        } elseif ($actor && $actor->id === $target->id) {
            $base['readonly'] = true;
            $base['note'] = 'Tài khoản quản trị đang sử dụng được bảo vệ khỏi thay đổi quyền tại đây.';
        } elseif ($isTeacher) {
            $base['note'] = 'Giáo viên mặc định có đủ các chức năng dưới đây. Bỏ chọn để thu hồi riêng cho người này; đổi phạm vi để giới hạn nơi chức năng có hiệu lực.';
        } else {
            $base['note'] = 'Tài khoản này không phải giáo viên nên mặc định chưa có chức năng nào. Chọn để cấp thêm riêng cho người này.';
        }

        // SỬA 28/10 — QUYỀN KHU QUẢN TRỊ (menu trái admin) cho Admin / Editor: bảng mục × thao tác (Xem/Thêm/Sửa/Xoá/Duyệt).
        // Admin mặc định có hết (bỏ tick = thu hồi), Editor mặc định không có gì (tick = cấp). Xem App\Support\AdminPermissions.
        $isAdminRole = in_array(Role::ADMIN, $roleNames, true);
        $adminArea = $isSuper || $isAdminRole || in_array(Role::EDITOR, $roleNames, true);
        $adminModules = [];
        if ($adminArea) {
            $adminDefault = $isSuper || $isAdminRole;
            foreach (\App\Support\AdminPermissions::MODULES as $mKey => $m) {
                $cells = [];
                foreach (array_keys(\App\Support\AdminPermissions::ACTIONS) as $action) {
                    if (! in_array($action, $m['actions'], true)) {
                        $cells[$action] = null;

                        continue;
                    }
                    $slug = \App\Support\AdminPermissions::slug($mKey, $action);
                    $ov = $overrides[$slug] ?? null;
                    $on = $isSuper ? true : ($ov ? $ov['effect'] === 'grant' : $adminDefault);
                    $cells[$action] = [
                        'slug' => $slug,
                        'label' => \App\Support\AdminPermissions::label($mKey, $action),
                        'action' => $action,
                        'on' => $on,
                        'scope' => 'own',
                        'state' => match (true) {
                            $isSuper => 'role',
                            $ov && $ov['effect'] === 'revoke' => 'revoked',
                            $ov && $ov['effect'] === 'grant' && ! $adminDefault => 'extra',
                            default => 'role',
                        },
                        'role_default' => $adminDefault,
                    ];
                }
                $adminModules[] = ['key' => $mKey, 'label' => $m['label'], 'hint' => $m['hint'] ?? '', 'cells' => $cells];
            }
            if (! $isSuper && ! ($actor && $actor->id === $target->id)) {
                $base['note'] = $isAdminRole
                    ? 'Admin mặc định có đủ mọi quyền quản trị bên dưới. Bỏ chọn để thu hồi riêng cho người này.'
                    : 'Biên tập viên (Editor) mặc định CHƯA có quyền quản trị nào — tick đúng mục / thao tác cần cấp. Mục nào không có quyền "Xem" thì không hiện trên menu và không mở được.';
            }
        }

        $groups = [];
        foreach ($perms as $p) {
            $roleDefault = $isTeacher || $isSuper;
            $ov = $overrides[$p->slug] ?? null;
            $on = $isSuper ? true : ($ov ? $ov['effect'] === 'grant' : $roleDefault);
            $scope = $ov['scope'] ?? 'own';
            $state = match (true) {
                $isSuper => 'role',
                $ov && $ov['effect'] === 'revoke' => 'revoked',
                $ov && $ov['effect'] === 'grant' && ! $roleDefault => 'extra',
                default => 'role',
            };

            $groups[$p->group][] = [
                'slug' => $p->slug,
                'label' => $p->label,
                'on' => $on,
                'scope' => $on ? $scope : 'own',
                'state' => $state,
                'role_default' => $roleDefault,
            ];
        }

        $out = [];
        $total = 0;
        $granted = 0;
        foreach (self::GROUPS as $key => $label) {
            if (empty($groups[$key])) {
                continue;
            }
            $out[] = ['key' => $key, 'label' => $label, 'items' => $groups[$key]];
            foreach ($groups[$key] as $i) {
                $total++;
                $granted += $i['on'] ? 1 : 0;
            }
        }

        foreach ($adminModules as $am) {
            foreach ($am['cells'] as $cell) {
                if ($cell !== null) {
                    $total++;
                    $granted += $cell['on'] ? 1 : 0;
                }
            }
        }

        return array_merge($base, ['groups' => $out, 'adminModules' => $adminModules, 'total' => $total, 'granted' => $granted]);
    }

    /**
     * Lưu toàn bộ bảng quyền của một người. $input = [slug => ['on' => bool, 'scope' => own|class|all]].
     *
     * @return int số quyền thay đổi
     */
    public function save(User $actor, User $target, array $input): int
    {
        if ($actor->id === $target->id) {
            throw ValidationException::withMessages(['permissions' => 'Không thể tự thay đổi quyền của chính mình ở đây.']);
        }
        if ($target->hasRole(Role::SUPER_ADMIN)) {
            throw ValidationException::withMessages(['permissions' => 'Super Admin luôn có mọi quyền, không thể chỉnh từng quyền.']);
        }
        if (! $this->tableReady()) {
            throw ValidationException::withMessages(['permissions' => 'Chưa chạy migration lưu phân quyền. Hãy chạy "php artisan migrate".']);
        }

        $before = $this->matrix($target, null);
        if ($before['mode'] !== 'matrix') {
            throw ValidationException::withMessages(['permissions' => 'Người dùng này không có bảng quyền chức năng để chỉnh.']);
        }

        $isTeacher = $target->hasRole(self::DEFAULT_ON_ROLE);
        $isAdminRole = $target->hasRole(Role::ADMIN); // SỬA 28/10 — mặc định quyền khu quản trị
        $permIds = Permission::query()->where(fn ($q) => $q->where('slug', 'like', 'teacher.%')->orWhere('slug', 'like', 'admin.%'))->pluck('id', 'slug');

        // Gộp quyền giáo viên + quyền khu quản trị thành một danh sách để lưu chung một vòng.
        $items = [];
        foreach ($before['groups'] as $group) {
            foreach ($group['items'] as $item) {
                $items[] = $item + ['admin' => false];
            }
        }
        foreach ($before['adminModules'] ?? [] as $am) {
            foreach ($am['cells'] as $cell) {
                if ($cell !== null) {
                    $items[] = $cell + ['admin' => true];
                }
            }
        }

        $beforeText = [];
        $afterText = [];
        $changed = 0;

        DB::transaction(function () use ($actor, $target, $input, $items, $isTeacher, $isAdminRole, $permIds, &$beforeText, &$afterText, &$changed) {
            foreach ([$items] as $groupItems) {
                foreach ($groupItems as $item) {
                    $slug = $item['slug'];
                    $row = $input[$slug] ?? [];
                    $wantOn = filter_var($row['on'] ?? false, FILTER_VALIDATE_BOOLEAN);
                    $scope = (string) ($row['scope'] ?? 'own');
                    if (! array_key_exists($scope, self::SCOPES) || $item['admin']) {
                        $scope = 'own'; // quyền khu quản trị không có phạm vi
                    }

                    $oldText = $item['admin'] ? ($item['on'] ? 'Bật' : 'Tắt') : $this->describe($item['on'], $item['scope']);
                    $newText = $item['admin'] ? ($wantOn ? 'Bật' : 'Tắt') : $this->describe($wantOn, $wantOn ? $scope : 'own');
                    if ($oldText === $newText) {
                        continue;
                    }

                    $permId = $permIds[$slug] ?? null;
                    if ($permId === null) {
                        continue;
                    }

                    $where = ['user_id' => $target->id, 'permission_id' => $permId];
                    $roleDefault = $item['admin'] ? $isAdminRole : $isTeacher;

                    if ($wantOn && $roleDefault && $scope === 'own') {
                        // Trùng mặc định vai trò -> xóa phần chênh lệch.
                        DB::table('permission_user')->where($where)->delete();
                    } elseif ($wantOn) {
                        $this->upsert($where, 'grant', $scope, $actor->id);
                    } elseif ($roleDefault) {
                        $this->upsert($where, 'revoke', 'own', $actor->id);
                    } else {
                        DB::table('permission_user')->where($where)->delete();
                    }

                    $beforeText[$item['label']] = $oldText;
                    $afterText[$item['label']] = $newText;
                    $changed++;
                }
            }

            if ($changed > 0) {
                AuditLog::create([
                    'actor_id' => $actor->id,
                    'action' => 'permissions_updated',
                    'auditable_type' => User::class,
                    'auditable_id' => $target->id,
                    'changes' => ['before' => $beforeText, 'after' => $afterText],
                    'reason' => null,
                ]);
            }
        });

        return $changed;
    }

    private function upsert(array $where, string $effect, string $scope, int $grantedBy): void
    {
        $exists = DB::table('permission_user')->where($where)->exists();
        $now = now();
        if ($exists) {
            DB::table('permission_user')->where($where)->update(['effect' => $effect, 'scope' => $scope, 'granted_by' => $grantedBy, 'updated_at' => $now]);
        } else {
            DB::table('permission_user')->insert($where + ['effect' => $effect, 'scope' => $scope, 'granted_by' => $grantedBy, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    private function describe(bool $on, string $scope): string
    {
        return $on ? 'Bật · '.(self::SCOPES[$scope] ?? self::SCOPES['own']) : 'Tắt';
    }
}
