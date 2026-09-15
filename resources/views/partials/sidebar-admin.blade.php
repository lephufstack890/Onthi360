{{-- Menu trái khu Quản trị.
     SỬA 12/9 — CHỈ ĐỔI GIAO DIỆN: giữ nguyên 100% danh sách mục, route và điều kiện lọc theo
     vai trò (superAdminOnly / editorOk) của bản cũ. Dùng chung khung partials/workspace-nav
     với 3 khu còn lại. --}}
@php
    $items = [
        ['label' => 'Tổng quan', 'route' => 'admin.dashboard', 'icon' => 'layout-dashboard'],
        ['label' => 'Người dùng', 'route' => 'admin.users.index', 'icon' => 'users', 'also' => ['admin.users.show', 'admin.teacher-approvals.index', 'admin.teacher-approvals.show']],
        ['label' => 'Kho câu hỏi và đề', 'route' => 'admin.content.index', 'icon' => 'library', 'also' => ['admin.content.show'], 'editorOk' => true],
        /*
         * SỬA 15/9 — Lộ trình là cấp trên của Khoá học nên đứng ngay trước.
         * Cùng ngày, khách chốt quay lại cách bán cũ nên mục này ĐANG ẨN. Ẩn chứ không xoá:
         * đổi Admin\LearningPathService::SHOW_ADMIN_MENU thành true là hiện lại đúng chỗ cũ.
         * Route vẫn sống, vào thẳng /admin/learning-paths vẫn quản lý được như thường.
         */
        ...(\App\Services\Admin\LearningPathService::SHOW_ADMIN_MENU ? [
            ['label' => 'Lộ trình', 'route' => 'admin.learning-paths.index', 'icon' => 'route',
                'also' => ['admin.learning-paths.create', 'admin.learning-paths.edit', 'admin.learning-paths.steps']],
        ] : []),
        ['label' => 'Khóa & Lớp', 'route' => 'admin.courses.index', 'icon' => 'graduation-cap'],
        ['label' => 'Tài liệu', 'route' => 'admin.products.index', 'icon' => 'wallet-cards', 'also' => ['admin.products.show', 'admin.access-rights.index']],
        // SỬA 15/9 (khách: "để biết được ai nạp tiền còn vô mà duyệt") — 'badge' => tên hàng
        // chờ cần đếm, cách đếm khai ở $badgeCounts bên dưới.
        ['label' => 'Đơn hàng', 'route' => 'admin.orders.index', 'icon' => 'file-check-2', 'also' => ['admin.orders.show'], 'badge' => 'orders'],
        ['label' => 'Mã kích hoạt', 'route' => 'admin.activation-codes.index', 'icon' => 'ticket'],
        ['label' => 'Đánh giá', 'route' => 'admin.reviews.index', 'icon' => 'star', 'also' => ['admin.reviews.show']],
        // SỬA 13/9 — yêu cầu hỗ trợ tách thành mục riêng. Trước đây nó nấp làm một cái tab bên
        // trong màn "Đánh giá" nên không ai để ý là có người đang chờ trả lời. Không đặt
        // 'editorOk' => true: chỉ quản trị viên được đọc nội dung người dùng gửi.
        ['label' => 'Yêu cầu hỗ trợ', 'route' => 'admin.contact-messages.index', 'icon' => 'headphones', 'badge' => 'support'],
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
     * Viên số đỏ cạnh mục menu — để quản trị viên biết có việc đang chờ mà không phải mở từng
     * màn ra xem. SỬA 15/9 — trước chỉ chạy được cho đúng 1 mục (cờ 'badgeNew' chết tên vào
     * yêu cầu hỗ trợ); giờ mục nào cần thì khai 'badge' => tên hàng chờ ở mảng $items trên,
     * thêm hàng chờ mới chỉ việc thêm 1 dòng vào $badgeCounts.
     *
     * Chỉ đếm hàng chờ nào CÒN trong menu sau khi lọc theo vai trò, mỗi hàng chờ đúng 1 truy vấn
     * cho mỗi lần dựng trang: biên tập viên không thấy 2 mục này nên không chạy truy vấn nào.
     */
    $badgeQueues = collect($items)->pluck('badge')->filter()->unique();
    $badgeCounts = [
        // Phiếu hỗ trợ chưa ai nhận.
        'support' => $badgeQueues->contains('support')
            ? app(\App\Services\Admin\ContactMessageService::class)->openCount()
            : 0,
        // Màn "Đơn hàng" gánh HAI hàng chờ khác nhau, viên số cộng cả hai vì cả hai đều chờ
        // đúng một người vào bấm duyệt (xem Admin\OrderController::index — màn đó đổ cả
        // $tokenTopups lẫn danh sách đơn):
        //   · yêu cầu NẠP TOKEN đã chuyển khoản, chờ đối chiếu sao kê rồi cộng token;
        //   · đơn mua hàng đã chuyển tiền, chờ xác nhận.
        // Đơn mới tạo/chưa thanh toán CỐ Ý không đếm (chưa trả tiền thì chưa có gì để duyệt) nên
        // số này thường nhỏ hơn con số trên tab "Chờ duyệt" của màn đó — xem
        // OrderService::AWAITING_APPROVAL_STATUSES.
        'orders' => $badgeQueues->contains('orders')
            ? app(\App\Services\WalletService::class)->pendingTopupCount()
                + app(\App\Services\Admin\OrderService::class)->awaitingApprovalCount()
            : 0,
    ];

    $navItems = array_map(fn ($item) => [
        'label' => $item['label'],
        'icon' => $item['icon'],
        'href' => route($item['route']),
        'active' => request()->routeIs(array_merge([$item['route']], $item['also'] ?? [])),
        'badge' => $badgeCounts[$item['badge'] ?? ''] ?? null,
    ], $items);
@endphp

@include('partials.workspace-nav', [
    'items' => $navItems,
    'wsUserName' => $currentUser->name ?? 'Quản trị viên',
    'wsRoleLabel' => $isPureEditor ? 'Biên tập nội dung' : ($isSuperAdmin ? 'Quản trị cấp cao' : 'Quản trị viên'),
])
