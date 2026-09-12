{{-- Menu trái khu Quản trị.
     SỬA 12/9 — CHỈ ĐỔI GIAO DIỆN: giữ nguyên 100% danh sách mục, route và điều kiện lọc theo
     vai trò (superAdminOnly / editorOk) của bản cũ. Dùng chung khung partials/workspace-nav
     với 3 khu còn lại. --}}
@php
    $items = [
        ['label' => 'Tổng quan', 'route' => 'admin.dashboard', 'icon' => 'layout-dashboard'],
        ['label' => 'Người dùng', 'route' => 'admin.users.index', 'icon' => 'users', 'also' => ['admin.users.show', 'admin.teacher-approvals.index', 'admin.teacher-approvals.show']],
        ['label' => 'Kho câu hỏi và đề', 'route' => 'admin.content.index', 'icon' => 'library', 'also' => ['admin.content.show'], 'editorOk' => true],
        ['label' => 'Khóa & Lớp', 'route' => 'admin.courses.index', 'icon' => 'graduation-cap'],
        ['label' => 'Tài liệu', 'route' => 'admin.products.index', 'icon' => 'wallet-cards', 'also' => ['admin.products.show', 'admin.access-rights.index']],
        ['label' => 'Đơn hàng', 'route' => 'admin.orders.index', 'icon' => 'file-check-2', 'also' => ['admin.orders.show']],
        ['label' => 'Mã kích hoạt', 'route' => 'admin.activation-codes.index', 'icon' => 'ticket'],
        ['label' => 'Đánh giá', 'route' => 'admin.reviews.index', 'icon' => 'star', 'also' => ['admin.reviews.show']],
        // SỬA 13/9 — yêu cầu hỗ trợ tách thành mục riêng. Trước đây nó nấp làm một cái tab bên
        // trong màn "Đánh giá" nên không ai để ý là có người đang chờ trả lời. Không đặt
        // 'editorOk' => true: chỉ quản trị viên được đọc nội dung người dùng gửi.
        ['label' => 'Yêu cầu hỗ trợ', 'route' => 'admin.contact-messages.index', 'icon' => 'headphones', 'badgeNew' => true],
        ['label' => 'Cuộc thi', 'route' => 'admin.competitions.index', 'icon' => 'trophy', 'also' => ['admin.featured-teachers.index']],
        ['label' => 'Câu chuyện đồng hành', 'route' => 'admin.testimonials.index', 'icon' => 'heart', 'also' => ['admin.testimonials.create', 'admin.testimonials.edit']],
        ['label' => 'Bảng xếp hạng', 'route' => 'admin.ranking.index', 'icon' => 'bar-chart-3'],
        ['label' => 'Báo cáo', 'route' => 'admin.reports.index', 'icon' => 'scroll-text'],
        ['label' => 'Cấu hình', 'route' => 'admin.settings.index', 'icon' => 'settings', 'superAdminOnly' => true],
        ['label' => 'Tài khoản', 'route' => 'admin.profile.show', 'icon' => 'user-cog', 'editorOk' => true],
    ];
    $currentUser = auth()->user();
    $isSuperAdmin = $currentUser?->hasRole(\App\Models\Role::SUPER_ADMIN) ?? false;
    $isPureEditor = ($currentUser?->hasRole(\App\Models\Role::EDITOR) ?? false)
        && ! ($currentUser?->hasAnyRole(\App\Models\Role::ADMIN, \App\Models\Role::SUPER_ADMIN) ?? false);
    $items = array_values(array_filter(
        $items,
        fn ($item) => (! ($item['superAdminOnly'] ?? false) || $isSuperAdmin)
            && (! $isPureEditor || ($item['editorOk'] ?? false))
    ));

    /*
     * Số yêu cầu hỗ trợ chưa ai nhận — hiện thành viên số đỏ cạnh mục menu để quản trị viên
     * biết có việc đang chờ mà không phải mở từng màn ra xem. Chỉ đếm đúng một lần cho mỗi
     * lần dựng trang, và chỉ khi menu thật sự có mục đó.
     */
    $pendingSupport = collect($items)->contains(fn ($item) => $item['badgeNew'] ?? false)
        ? app(\App\Services\Admin\ContactMessageService::class)->openCount()
        : 0;

    $navItems = array_map(fn ($item) => [
        'label' => $item['label'],
        'icon' => $item['icon'],
        'href' => route($item['route']),
        'active' => request()->routeIs(array_merge([$item['route']], $item['also'] ?? [])),
        'badge' => ($item['badgeNew'] ?? false) ? $pendingSupport : null,
    ], $items);
@endphp

@include('partials.workspace-nav', [
    'items' => $navItems,
    'wsUserName' => $currentUser->name ?? 'Quản trị viên',
    'wsRoleLabel' => $isPureEditor ? 'Biên tập nội dung' : ($isSuperAdmin ? 'Quản trị cấp cao' : 'Quản trị viên'),
])
