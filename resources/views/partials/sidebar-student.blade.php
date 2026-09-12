{{-- Menu trái khu Học sinh.
     SỬA 12/9 — CHỈ ĐỔI GIAO DIỆN: giữ nguyên 100% danh sách mục và route của bản cũ. --}}
@php
    $items = [
        ['label' => 'Tổng quan', 'route' => 'dashboard', 'icon' => 'layout-dashboard'],
        ['label' => 'Khóa học', 'route' => 'student.courses.index', 'icon' => 'book-open'],
        ['label' => 'Thời khoá biểu', 'route' => 'student.schedule.index', 'icon' => 'calendar-days'],
        ['label' => 'Luyện tập', 'route' => 'student.practice.index', 'icon' => 'notebook-pen'],
        ['label' => 'Tài liệu', 'route' => 'student.library.index', 'icon' => 'library'],
        ['label' => 'Cuộc thi', 'route' => 'competitions.index', 'icon' => 'trophy'],
        ['label' => 'Bảng xếp hạng', 'route' => 'leaderboard.index', 'icon' => 'bar-chart-3'],
        ['label' => 'Ví token', 'route' => 'wallet.index', 'icon' => 'wallet-cards'],
        ['label' => 'Thông báo', 'route' => 'student.notifications', 'icon' => 'message-square-text'],
        ['label' => 'Hồ sơ', 'route' => 'student.profile', 'icon' => 'user-cog'],
    ];

    $navItems = array_map(fn ($item) => [
        'label' => $item['label'],
        'icon' => $item['icon'],
        'href' => route($item['route']),
        'active' => request()->routeIs($item['route']),
    ], $items);
@endphp

@include('partials.workspace-nav', [
    'items' => $navItems,
    'wsUserName' => auth()->user()->name ?? 'Học sinh',
    'wsRoleLabel' => 'Học sinh',
])
