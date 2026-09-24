<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;

class DashboardRoutingService
{
    public const string ADMIN = 'admin';

    public const string EDITOR = 'editor';

    public const string TEACHER = 'teacher';

    public const string PARENT = 'parent';

    public const string STUDENT = 'student';

    /**
     * SỬA 24/9 (khách: "giáo viên, phụ huynh, admin bấm Lịch sử làm bài ở trang Luyện tập công
     * khai thì nhảy vào khu học tập của học sinh") — VAI TRÒ DÙNG ĐỂ CHỌN LỚP ÁO khu làm việc
     * cho các trang DÙNG CHUNG (Luyện tập, Lịch sử làm bài, Kết quả, Quyền học, Đánh giá).
     *
     * Dựa thẳng trên primaryDashboardFor() — cùng bộ luật mà /dashboard dùng để chia khu, nên
     * không có chuyện hai nơi hiểu khác nhau. Biên tập viên không có khu riêng nên mặc lớp áo
     * Quản trị, khớp với việc DashboardController đẩy họ về admin.content.index.
     *
     * Trả về đúng 1 trong 4 khoá mà layouts/workspace.blade.php hiểu.
     */
    public static function workspaceRoleFor(?User $user): string
    {
        if ($user === null) {
            return 'student';
        }

        return match ((new self())->primaryDashboardFor($user)) {
            self::ADMIN, self::EDITOR => 'admin',
            self::TEACHER => 'teacher',
            self::PARENT => 'parent',
            default => 'student',
        };
    }

    public function primaryDashboardFor(User $user): string
    {
        if ($user->hasAnyRole(Role::ADMIN, Role::SUPER_ADMIN)) {
            return self::ADMIN;
        }

        if ($user->hasRole(Role::EDITOR)) {
            return self::EDITOR;
        }

        if ($user->hasRole(Role::TEACHER)) {
            return self::TEACHER;
        }

        if ($user->hasRole(Role::PARENT)) {
            return self::PARENT;
        }

        return self::STUDENT;
    }
}
