<?php

namespace App\Support;

use App\Models\Role;
use App\Models\User;

/**
 * SỬA 28/10 (khách: "phân quyền làm lại cho đúng — Editor không có quyền vào Kho bài tập mà vẫn vào được là sai; phân quyền
 * cho các tính năng bên sidebar admin: ai được vào, thêm, sửa, xoá gì") — DANH MỤC QUYỀN KHU QUẢN TRỊ, theo đúng các mục
 * menu trái. Mỗi quyền là một dòng bảng permissions, slug "admin.<mục>.<thao tác>" (vd admin.content.delete).
 *
 * Ai có quyền gì:
 *   · Super Admin: luôn có tất cả (không chỉnh từng quyền được).
 *   · Admin: mặc định có tất cả; thu hồi riêng từng quyền ở Người dùng > Phân quyền.
 *   · Editor (biên tập viên): mặc định KHÔNG có quyền nào — cấp riêng từng mục / thao tác cần dùng.
 *   · Tài khoản / Đổi mật khẩu: ai vào được khu quản trị cũng dùng được. Cấu hình: chỉ Super Admin (như cũ).
 *
 * Chặn thật nằm ở middleware EnsureAdminPermission (đọc tên route → mục + thao tác qua forRoute()); menu trái chỉ hiện
 * mục có quyền Xem. Thêm mục / route mới: khai thêm vào MODULES (tiền tố tên route) là xong.
 */
final class AdminPermissions
{
    public const ACTIONS = [
        'view' => 'Xem',
        'create' => 'Thêm',
        'update' => 'Sửa',
        'delete' => 'Xoá',
        'approve' => 'Duyệt',
    ];

    /**
     * Mục quản trị: nhãn (đúng chữ ở menu trái), các thao tác có nghĩa, giải thích ngắn, và các tiền tố tên route thuộc mục.
     * Thứ tự = thứ tự hiện ở bảng phân quyền và thứ tự tìm trang đầu tiên được vào.
     */
    public const MODULES = [
        'dashboard' => [
            'label' => 'Tổng quan', 'actions' => ['view'], 'hint' => 'Trang tổng quan số liệu',
            'routes' => ['admin.dashboard'], 'home' => 'admin.dashboard',
        ],
        'users' => [
            'label' => 'Người dùng', 'actions' => ['view', 'create', 'update', 'delete', 'approve'],
            'hint' => 'Sửa: thông tin, mật khẩu, khôi phục · Duyệt: duyệt giáo viên, liên kết phụ huynh',
            'routes' => ['admin.users.', 'admin.teacher-approvals.', 'admin.parent-links.'], 'home' => 'admin.users.index',
        ],
        'user_roles' => [
            'label' => 'Vai trò & phân quyền người dùng', 'actions' => ['update'],
            'hint' => 'Đổi vai trò và cấp / thu hồi quyền cho người khác (quyền nhạy cảm)',
            'routes' => ['admin.users.roles.', 'admin.users.permissions.'],
        ],
        'content' => [
            'label' => 'Kho bài tập / câu hỏi và đề', 'actions' => ['view', 'create', 'update', 'delete', 'approve'],
            'hint' => 'Câu hỏi, đề, tag/chuyên đề, bài của tài liệu · Duyệt: phát hành, từ chối, lưu trữ, đưa vào kho chung',
            'routes' => ['admin.content.'], 'home' => 'admin.content.index',
        ],
        'learning_paths' => [
            'label' => 'Lộ trình', 'actions' => ['view', 'create', 'update', 'delete'], 'hint' => '',
            'routes' => ['admin.learning-paths.'], 'home' => 'admin.learning-paths.index',
        ],
        'courses' => [
            'label' => 'Khóa & Lớp', 'actions' => ['view', 'create', 'update', 'delete'], 'hint' => 'Khoá học và lớp học',
            'routes' => ['admin.courses.', 'admin.classes.'], 'home' => 'admin.courses.index',
        ],
        'products' => [
            'label' => 'Tài liệu', 'actions' => ['view', 'create', 'update', 'delete'],
            'hint' => 'Sách / chuyên đề / bộ đề, chương, bài tập đính kèm, bài dùng chung, quyền truy cập tài liệu',
            'routes' => ['admin.products.', 'admin.materials.', 'admin.access-rights.', 'admin.content.assessments.bundle.'],
            'home' => 'admin.products.index',
        ],
        'orders' => [
            'label' => 'Đơn hàng', 'actions' => ['view', 'approve'], 'hint' => 'Duyệt / từ chối đơn mua và yêu cầu nạp token',
            'routes' => ['admin.orders.'], 'home' => 'admin.orders.index',
        ],
        'activation_codes' => [
            'label' => 'Mã kích hoạt', 'actions' => ['view', 'create', 'delete'], 'hint' => 'Xoá = thu hồi mã',
            'routes' => ['admin.activation-codes.'], 'home' => 'admin.activation-codes.index',
        ],
        'reviews' => [
            'label' => 'Đánh giá', 'actions' => ['view', 'approve'], 'hint' => 'Duyệt: đăng, từ chối, ẩn, yêu cầu sửa, trả lời',
            'routes' => ['admin.reviews.'], 'home' => 'admin.reviews.index',
        ],
        'support' => [
            'label' => 'Yêu cầu hỗ trợ', 'actions' => ['view', 'update'], 'hint' => 'Sửa: nhận xử lý, ghi chú, đánh dấu đã xong',
            'routes' => ['admin.contact-messages.'], 'home' => 'admin.contact-messages.index',
        ],
        'competitions' => [
            'label' => 'Cuộc thi', 'actions' => ['view', 'create', 'update', 'delete', 'approve'],
            'hint' => 'Cuộc thi / khảo sát và các kỳ · Duyệt: đơn đăng ký dự thi, lưu trữ, tính lại bảng xếp hạng',
            'routes' => ['admin.competitions.'], 'home' => 'admin.competitions.index',
        ],
        'featured_teachers' => [
            'label' => 'Giáo viên & chuyên gia', 'actions' => ['view', 'create', 'update', 'delete'], 'hint' => '',
            'routes' => ['admin.featured-teachers.'], 'home' => 'admin.featured-teachers.index',
        ],
        'testimonials' => [
            'label' => 'Câu chuyện đồng hành', 'actions' => ['view', 'create', 'update', 'delete'], 'hint' => '',
            'routes' => ['admin.testimonials.'], 'home' => 'admin.testimonials.index',
        ],
        'ranking' => [
            'label' => 'Bảng xếp hạng', 'actions' => ['view'], 'hint' => '',
            'routes' => ['admin.ranking.'], 'home' => 'admin.ranking.index',
        ],
        'reports' => [
            'label' => 'Báo cáo', 'actions' => ['view'], 'hint' => '',
            'routes' => ['admin.reports.'], 'home' => 'admin.reports.index',
        ],
    ];

    /** Route ai vào được khu quản trị cũng dùng được (tài khoản của chính mình). Cấu hình do middleware role:super_admin chặn. */
    private const ALWAYS = ['admin.profile.', 'admin.password.', 'admin.settings.'];

    public static function slug(string $module, string $action): string
    {
        return 'admin.'.$module.'.'.$action;
    }

    /** @return list<string> mọi slug quyền quản trị */
    public static function allSlugs(): array
    {
        $out = [];
        foreach (self::MODULES as $key => $m) {
            foreach ($m['actions'] as $a) {
                $out[] = self::slug($key, $a);
            }
        }

        return $out;
    }

    public static function label(string $module, string $action): string
    {
        return (self::MODULES[$module]['label'] ?? $module).' — '.(self::ACTIONS[$action] ?? $action);
    }

    public static function can(?User $user, string $module, string $action = 'view'): bool
    {
        if ($user === null) {
            return false;
        }
        if ($user->hasRole(Role::SUPER_ADMIN)) {
            return true;
        }

        return $user->hasPermission(self::slug($module, $action));
    }

    /**
     * Tên route + phương thức HTTP → [mục, thao tác]; 'always' = không cần quyền riêng; null = route không thuộc danh mục.
     *
     * @return array{0: string, 1: string}|string|null
     */
    public static function forRoute(?string $name, string $method = 'GET')
    {
        if ($name === null) {
            return null;
        }
        foreach (self::ALWAYS as $p) {
            if (str_starts_with($name, $p)) {
                return 'always';
            }
        }

        // Tiền tố dài nhất thắng (vd admin.users.permissions. thuộc "Vai trò & phân quyền", không phải "Người dùng").
        $best = null;
        $bestLen = -1;
        foreach (self::MODULES as $key => $m) {
            foreach ($m['routes'] as $p) {
                $hit = str_ends_with($p, '.') ? str_starts_with($name, $p) : $name === $p;
                if ($hit && strlen($p) > $bestLen) {
                    $best = $key;
                    $bestLen = strlen($p);
                }
            }
        }
        if ($best === null) {
            return null;
        }

        return [$best, self::actionFor($best, $name, strtoupper($method))];
    }

    /** Thao tác của một route trong mục: theo phương thức + đuôi tên route; mục không có thao tác đó thì quy về gần nhất. */
    private static function actionFor(string $module, string $name, string $method): string
    {
        $want = match (true) {
            in_array($method, ['GET', 'HEAD'], true) => match (true) {
                (bool) preg_match('/\.(create|createManual|import|bulk\.create|reviewDraft)$/', $name) => 'create',
                (bool) preg_match('/\.edit$/', $name) => 'update',
                default => 'view',
            },
            $method === 'DELETE' => 'delete',
            (bool) preg_match('/\.(publish|publishAll|reject|archive|unarchive|approve|promoteShared|request-revision|hide|reply|suspend|reinstate|recompute-aggregate|registrations\.[a-z]+)$/', $name) => 'approve',
            (bool) preg_match('/\.revoke$/', $name) => 'delete',
            (bool) preg_match('/\.(store|storeManual|import\.store|zipImport|prepare|import|finish|split|multi|add|attach|drafts\.store|test-cases\.import)$/', $name) => 'create',
            default => 'update',
        };

        $actions = self::MODULES[$module]['actions'];
        if (in_array($want, $actions, true)) {
            return $want;
        }
        // Mục không tách thao tác này → dùng thao tác gần nhất mà mục có.
        $fallbacks = ['approve' => ['update'], 'create' => ['update'], 'delete' => ['update'], 'update' => ['approve']];
        foreach ($fallbacks[$want] ?? [] as $alt) {
            if (in_array($alt, $actions, true)) {
                return $alt;
            }
        }

        return in_array('update', $actions, true) ? 'update' : $actions[array_key_last($actions)];
    }

    /** Route được phép với người này không (dùng cho menu / nút). */
    public static function allowsRoute(?User $user, string $name, string $method = 'GET'): bool
    {
        $rule = self::forRoute($name, $method);
        if ($rule === 'always') {
            return $user !== null;
        }
        if ($rule === null) {
            // Route quản trị chưa khai mục: chỉ Admin / Super Admin (an toàn theo mặc định).
            return $user !== null && $user->hasAnyRole(Role::ADMIN, Role::SUPER_ADMIN);
        }

        return self::can($user, $rule[0], $rule[1]);
    }

    /** Trang đầu tiên người này được vào trong khu quản trị (theo thứ tự menu); không có thì trang Tài khoản. */
    public static function firstAllowedRoute(?User $user): string
    {
        foreach (self::MODULES as $key => $m) {
            if (! empty($m['home']) && self::can($user, $key, 'view') && \Illuminate\Support\Facades\Route::has($m['home'])) {
                return $m['home'];
            }
        }

        return 'admin.profile.show';
    }
}
