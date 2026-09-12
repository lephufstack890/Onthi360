{{-- Menu trái khu Phụ huynh.
     SỬA 12/9 — CHỈ ĐỔI GIAO DIỆN: giữ nguyên 100% danh sách mục, route và luật "đang mở" khá
     riêng của khu này (mục sáng theo tham số ?tab= khi đang ở trang chi tiết của con). --}}
@php
    $onChildShow = request()->routeIs('parent.children.show');
    $currentTab = $onChildShow ? request()->query('tab', 'overview') : null;

    $items = [
        ['label' => 'Tổng quan', 'route' => 'dashboard', 'icon' => 'layout-dashboard', 'active' => request()->routeIs('dashboard')],
        ['label' => 'Con của tôi', 'route' => 'parent.children.index', 'icon' => 'users',
            'active' => request()->routeIs('parent.children.index') || ($onChildShow && ! in_array($currentTab, ['schedule', 'results'], true))],
        ['label' => 'Lịch & Điểm danh', 'route' => 'parent.schedule.index', 'icon' => 'calendar-days',
            'active' => request()->routeIs('parent.schedule.index') || ($onChildShow && $currentTab === 'schedule')],
        ['label' => 'Kết quả & Tiến độ', 'route' => 'parent.results.index', 'icon' => 'trending-up',
            'active' => request()->routeIs('parent.results.index') || ($onChildShow && $currentTab === 'results')],
        ['label' => 'Thông báo', 'route' => 'parent.notifications.index', 'icon' => 'message-square-text', 'active' => request()->routeIs('parent.notifications.index')],
        ['label' => 'Hồ sơ', 'route' => 'parent.profile', 'icon' => 'user-cog', 'active' => request()->routeIs('parent.profile')],
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
