{{-- Menu trái khu Giáo viên.
     SỬA 12/9 — CHỈ ĐỔI GIAO DIỆN: giữ nguyên 100% danh sách mục, route, cờ tính năng
     (config('features.teacher_practice_screen')) và cách xác định mục đang mở của bản cũ.
     Thay emoji bằng icon lucide và dùng chung khung partials/workspace-nav. --}}
@php
    $items = [
        ['label' => 'Tổng quan', 'route' => 'dashboard', 'icon' => 'layout-dashboard'],
        ['label' => 'Lớp học', 'route' => 'teacher.classes.index', 'icon' => 'graduation-cap'],
        ['label' => 'Tài liệu', 'route' => 'teacher.library.index', 'icon' => 'book-open'],
        ...(config('features.teacher_practice_screen', false)
            ? [['label' => 'Luyện tập', 'route' => 'teacher.assessments.index', 'icon' => 'notebook-pen']]
            : []),
        ['label' => 'Đề PDF của tôi', 'route' => 'teacher.papers.index', 'icon' => 'scroll-text'],
        ['label' => 'Kho câu hỏi của tôi', 'route' => 'teacher.questions.index', 'icon' => 'library'],
        ['label' => 'Cuộc thi', 'route' => 'teacher.competitions.index', 'icon' => 'trophy'],
        ['label' => 'Kết quả', 'route' => 'teacher.results.index', 'icon' => 'trending-up'],
        ['label' => 'Lịch', 'route' => 'teacher.schedule.index', 'icon' => 'calendar-days'],
        ['label' => 'Thông báo', 'route' => 'teacher.notifications.index', 'icon' => 'message-square-text'],
        ['label' => 'Hồ sơ', 'route' => 'teacher.profile.show', 'icon' => 'user-cog'],
    ];

    // Giữ nguyên luật cũ: route trùng nhau thì chỉ mục ĐẦU TIÊN được tính là đang mở.
    $primaryRoutes = [];
    $navItems = [];
    foreach ($items as $item) {
        $isPrimaryForRoute = ! in_array($item['route'], $primaryRoutes, true);
        if ($isPrimaryForRoute) {
            $primaryRoutes[] = $item['route'];
        }
        $navItems[] = [
            'label' => $item['label'],
            'icon' => $item['icon'],
            'href' => route($item['route']),
            'active' => $isPrimaryForRoute && request()->routeIs($item['route']),
        ];
    }
@endphp

@include('partials.workspace-nav', [
    'items' => $navItems,
    'wsUserName' => auth()->user()->name ?? 'Giáo viên',
    'wsRoleLabel' => 'Giáo viên',
])
