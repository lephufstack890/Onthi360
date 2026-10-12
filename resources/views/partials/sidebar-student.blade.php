{{-- Menu trái khu Học sinh.
     SỬA 12/10 — theo source RoleWorkspace.jsx: thêm "Bài được giao", "Kết quả", "Đánh giá của tôi"
     (giữ nguyên các mục và route cũ: Thời khoá biểu, Cuộc thi, Bảng xếp hạng, Ví token, Mã kích hoạt...). --}}
@php
    $items = [
        ['label' => 'Tổng quan', 'route' => 'dashboard', 'icon' => 'layout-dashboard'],
        ['label' => 'Khóa học của tôi', 'route' => 'student.courses.index', 'icon' => 'book-open'],
        ['label' => 'Thời khoá biểu', 'route' => 'student.schedule.index', 'icon' => 'calendar-days'],
        ['label' => 'Luyện tập', 'route' => 'student.practice.index', 'icon' => 'notebook-pen'],
        ['label' => 'Bài được giao', 'route' => 'student.assignments.index', 'icon' => 'clipboard-check'],
        ['label' => 'Kết quả', 'route' => 'student.results.index', 'icon' => 'trophy'],
        ['label' => 'Tài liệu của tôi', 'route' => 'student.library.index', 'icon' => 'library'],
        ['label' => 'Đánh giá của tôi', 'route' => 'student.reviews.index', 'icon' => 'star', 'also' => ['reviews.*']],
        ['label' => 'Cuộc thi', 'route' => 'competitions.index', 'icon' => 'trophy'],
        ['label' => 'Bảng xếp hạng', 'route' => 'leaderboard.index', 'icon' => 'bar-chart-3'],
        ['label' => 'Ví token', 'route' => 'wallet.index', 'icon' => 'wallet-cards'],
        // SỬA 14/9 — đưa thẳng vào menu cho khỏi phải chui vào menu tài khoản mới bấm được.
        ['label' => 'Mã kích hoạt', 'route' => 'access.activate', 'icon' => 'key-round'],
        ['label' => 'Thông báo', 'route' => 'student.notifications', 'icon' => 'message-square-text'],
        ['label' => 'Hồ sơ', 'route' => 'student.profile', 'icon' => 'user-cog'],
    ];

    $navItems = array_map(fn ($item) => [
        'label' => $item['label'],
        'icon' => $item['icon'],
        'href' => route($item['route']),
        'active' => request()->routeIs($item['route']) || ($item['also'] ?? []) && request()->routeIs(...$item['also']),
    ], $items);
@endphp

@include('partials.workspace-nav', [
    'items' => $navItems,
    'wsUserName' => auth()->user()->name ?? 'Học sinh',
    'wsRoleLabel' => 'Học sinh',
])
