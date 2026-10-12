{{-- Menu trái khu Phụ huynh.
     SỬA 12/9 — CHỈ ĐỔI GIAO DIỆN: giữ nguyên 100% danh sách mục, route và luật "đang mở" khá
     riêng của khu này (mục sáng theo tham số ?tab= khi đang ở trang chi tiết của con). --}}
@php
    /*
     * SỬA 12/10 — đúng thứ tự mục của source mới (roleConfig.parent.nav): Tổng quan · Con của tôi ·
     * Bài được giao · Lịch & Điểm danh · Kết quả & Tiến độ · Đánh giá lớp · Thông báo · Hồ sơ.
     * Trang chi tiết cũ parent.children.show giờ chỉ chuyển hướng sang các mục này.
     */
    $items = [
        ['label' => 'Tổng quan', 'route' => 'dashboard', 'icon' => 'layout-dashboard', 'active' => request()->routeIs('dashboard')],
        ['label' => 'Con của tôi', 'route' => 'parent.children.index', 'icon' => 'users', 'active' => request()->routeIs('parent.children.*')],
        ['label' => 'Bài được giao', 'route' => 'parent.assignments.index', 'icon' => 'clipboard-check', 'active' => request()->routeIs('parent.assignments.index')],
        ['label' => 'Lịch & Điểm danh', 'route' => 'parent.schedule.index', 'icon' => 'calendar-days', 'active' => request()->routeIs('parent.schedule.index')],
        ['label' => 'Kết quả & Tiến độ', 'route' => 'parent.results.index', 'icon' => 'trophy', 'active' => request()->routeIs('parent.results.index')],
        ['label' => 'Đánh giá lớp', 'route' => 'parent.reviews.index', 'icon' => 'star', 'active' => request()->routeIs('parent.reviews.index')],
        ['label' => 'Thông báo', 'route' => 'parent.notifications.index', 'icon' => 'message-square-text', 'active' => request()->routeIs('parent.notifications.index')],
        ['label' => 'Hồ sơ', 'route' => 'parent.profile', 'icon' => 'user-cog', 'active' => request()->routeIs('parent.profile')],
        ['label' => 'Đổi mật khẩu', 'route' => 'parent.password.edit', 'icon' => 'lock', 'active' => request()->routeIs('parent.password.edit')],
    ];

    $navItems = array_map(fn ($item) => [
        'label' => $item['label'],
        'icon' => $item['icon'],
        'href' => route($item['route']),
        'active' => $item['active'],
    ], $items);
@endphp

@include('partials.workspace-nav', [
    'items' => $navItems,
    'wsUserName' => auth()->user()->name ?? 'Phụ huynh',
    'wsRoleLabel' => 'Phụ huynh',
])
