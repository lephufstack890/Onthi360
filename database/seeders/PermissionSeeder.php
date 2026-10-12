<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Permission chi tiết theo action, gắn vào Role (không gắn thẳng User).
 * Danh sách bên dưới bám theo đúng 12 mục nav Admin đã build (ADM-01→06,
 * xem resources/views/partials/sidebar-admin.blade.php) — mỗi khu vực 1
 * permission "quản lý" (đủ cho route/middleware hiện tại). Cần chi tiết hơn
 * (VD: chỉ xem, không duyệt) thì thêm permission mới + seed thêm ở đây,
 * không phải sửa code Controller/Middleware.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['users.manage', 'Quản lý người dùng', 'users'],
            ['teacher-approvals.manage', 'Duyệt hồ sơ giáo viên', 'users'],
            ['content.manage', 'Quản lý nội dung (học liệu/câu hỏi/đề)', 'content'],
            ['courses.manage', 'Quản lý khóa & lớp', 'courses'],
            ['products.manage', 'Quản lý sản phẩm', 'commerce'],
            ['access-rights.manage', 'Quản lý quyền truy cập', 'commerce'],
            ['orders.manage', 'Quản lý đơn hàng', 'commerce'],
            ['activation-codes.manage', 'Quản lý mã kích hoạt', 'commerce'],
            ['reviews.manage', 'Duyệt đánh giá', 'reviews'],
            ['competitions.manage', 'Quản lý cuộc thi', 'competitions'],
            ['featured-teachers.manage', 'Quản lý Giáo viên và chuyên gia', 'competitions'],
            ['ranking.view', 'Xem bảng xếp hạng quản trị', 'competitions'],
            ['reports.view', 'Xem báo cáo', 'reports'],
            ['settings.manage', 'Cấu hình hệ thống', 'settings'],
            // 11 quyền chức năng của giáo viên (tab "Phân quyền" ở Admin > Người dùng). Mặc định theo vai trò tính trong
            // code (App\Models\User::hasPermission: có vai trò teacher => có), nên KHÔNG gán vào permission_role.
            ['teacher.assign-practice', 'Giao bài', 'teacher_general'],
            ['teacher.assign-assessment', 'Giao đề', 'teacher_general'],
            ['teacher.assign-material', 'Giao tài liệu', 'teacher_general'],
            ['teacher.view-submissions', 'Xem lịch sử nộp bài', 'teacher_general'],
            ['teacher.sample-practice', 'Chỉ định bài mẫu', 'teacher_general'],
            ['teacher.organize-competition', 'Tổ chức cuộc thi', 'teacher_general'],
            ['teacher.question-create', 'Tạo câu hỏi', 'teacher_content'],
            ['teacher.question-edit', 'Sửa câu hỏi', 'teacher_content'],
            ['teacher.assessment-create', 'Tạo đề thi', 'teacher_content'],
            ['teacher.material-upload', 'Tải học liệu lên', 'teacher_content'],
            ['teacher.content-publish', 'Phát hành nội dung', 'teacher_content'],
        ];

        $models = [];
        foreach ($permissions as [$slug, $label, $group]) {
            $models[] = Permission::query()->firstOrCreate(['slug' => $slug], ['label' => $label, 'group' => $group]);
        }

        // Admin + Super Admin: đủ mọi permission (giữ đúng hành vi hiện tại — route
        // đang chặn theo role:admin,super_admin ngang nhau). Super Admin còn được
        // User::hasPermission() bypass tuyệt đối ở tầng code, permission ở đây chủ
        // yếu để Admin dùng và để sau này tách quyền chi tiết hơn cho Admin thường.
        $adminRole = Role::where('name', Role::ADMIN)->first();
        $superAdminRole = Role::where('name', Role::SUPER_ADMIN)->first();
        $editorRole = Role::where('name', Role::EDITOR)->first();

        $allIds = collect($models)->reject(fn (Permission $p) => str_starts_with($p->slug, 'teacher.'))->pluck('id');

        if ($adminRole) {
            $adminRole->permissions()->syncWithoutDetaching($allIds);
        }

        if ($superAdminRole) {
            $superAdminRole->permissions()->syncWithoutDetaching($allIds);
        }

        // Editor (Content Editor, theo docs/ARCHITECTURE.md) chỉ cần quyền nội dung —
        // ví dụ cụ thể cho việc "thêm permission mới không cần sửa code" mà docblock
        // ở trên nói tới.
        if ($editorRole) {
            $contentPermissionIds = collect($models)
                ->filter(fn (Permission $p) => in_array($p->group, ['content'], true))
                ->pluck('id');
            $editorRole->permissions()->syncWithoutDetaching($contentPermissionIds);
        }
    }
}
