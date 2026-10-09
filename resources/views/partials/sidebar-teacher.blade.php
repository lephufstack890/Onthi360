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
        // SỬA 7/10 (khách: bỏ "Đề PDF của tôi", đổi "Kho câu hỏi của tôi" thành "Kho bài tập / câu hỏi và đề")
        // — đề PDF giờ là tab "Đề/bộ bài" trong màn này. 'also' = các route con (trang quản lý/tạo đề PDF)
        // cũng làm sáng mục này.
        ['label' => 'Kho bài tập / câu hỏi và đề', 'route' => 'teacher.questions.index', 'icon' => 'library', 'also' => ['teacher.papers.*', 'teacher.questions.*']],
        ['label' => 'Cuộc thi', 'route' => 'teacher.competitions.index', 'icon' => 'trophy'],
        ['label' => 'Kết quả', 'route' => 'teacher.results.index', 'icon' => 'trending-up'],
        ['label' => 'Lịch', 'route' => 'teacher.schedule.index', 'icon' => 'calendar-days'],
        ['label' => 'Thông báo', 'route' => 'teacher.notifications.index', 'icon' => 'message-square-text'],
        // SỬA 14/9 — khu Giáo viên cũng có ví token, cùng route với khu Học sinh.
        ['label' => 'Ví token', 'route' => 'wallet.index', 'icon' => 'wallet-cards'],
        ['label' => 'Mã kích hoạt', 'route' => 'access.activate', 'icon' => 'key-round'],
        ['label' => 'Hồ sơ', 'route' => 'teacher.profile.show', 'icon' => 'user-cog'],
        ['label' => 'Đổi mật khẩu', 'route' => 'teacher.password.edit', 'icon' => 'lock'],
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
            'active' => $isPrimaryForRoute && request()->routeIs($item['route'], ...($item['also'] ?? [])),
        ];
    }
@endphp

@include('partials.workspace-nav', [
    'items' => $navItems,
    'wsUserName' => auth()->user()->name ?? 'Giáo viên',
    'wsRoleLabel' => 'Giáo viên',
])
