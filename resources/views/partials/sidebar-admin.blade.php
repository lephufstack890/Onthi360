{{-- ═══════════════ MENU TRÁI TRANG QUẢN TRỊ ═══════════════
     SỬA 12/9 — dựng lại theo ĐÚNG source giao diện khách gửi
     (education-main/src/components/RoleWorkspace.jsx — aside + IconButton, roleConfig.admin).

     Đổi so với bản cũ: thanh bên dính mép màn hình + icon emoji + tông hồng  ->  MỘT THẺ TRẮNG
     bo tròn, icon lucide đúng bộ của source, mục đang mở tô xanh đặc như bản mẫu.

     LOGIC KHÔNG ĐỔI: giữ nguyên danh sách mục, điều kiện lọc theo vai trò (superAdminOnly /
     editorOk) và cách xác định mục đang mở bằng request()->routeIs(). Chỉ thay 'icon' từ emoji
     sang tên icon lucide.

     $adminNavCompact = true khi nhúng trong ngăn kéo của màn hình nhỏ (bỏ vỏ thẻ và khối trợ
     giúp, xếp 2 cột cho gọn). --}}
@php
    $adminNavCompact = $adminNavCompact ?? false;

    $items = [
        ['label' => 'Tổng quan', 'route' => 'admin.dashboard', 'icon' => 'layout-dashboard'],
        ['label' => 'Người dùng', 'route' => 'admin.users.index', 'icon' => 'users', 'also' => ['admin.users.show', 'admin.teacher-approvals.index', 'admin.teacher-approvals.show']],
        ['label' => 'Kho câu hỏi và đề', 'route' => 'admin.content.index', 'icon' => 'library', 'also' => ['admin.content.show'], 'editorOk' => true],
        ['label' => 'Khóa & Lớp', 'route' => 'admin.courses.index', 'icon' => 'graduation-cap'],
        ['label' => 'Tài liệu', 'route' => 'admin.products.index', 'icon' => 'wallet-cards', 'also' => ['admin.products.show', 'admin.access-rights.index']],
        ['label' => 'Đơn hàng', 'route' => 'admin.orders.index', 'icon' => 'file-check-2', 'also' => ['admin.orders.show']],
        ['label' => 'Mã kích hoạt', 'route' => 'admin.activation-codes.index', 'icon' => 'ticket'],
        ['label' => 'Đánh giá', 'route' => 'admin.reviews.index', 'icon' => 'star', 'also' => ['admin.reviews.show', 'admin.contact-messages.index']],
        ['label' => 'Cuộc thi', 'route' => 'admin.competitions.index', 'icon' => 'trophy', 'also' => ['admin.featured-teachers.index']],
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
    $adminName = $currentUser->name ?? 'Quản trị viên';
    $roleLabel = $isPureEditor ? 'Biên tập nội dung' : ($isSuperAdmin ? 'Quản trị cấp cao' : 'Quản trị viên');
@endphp

<div class="{{ $adminNavCompact ? '' : 'rounded-2xl border border-sky-100 bg-white p-2 shadow-[0_2px_8px_rgba(0,90,180,.04)] lg:rounded-3xl lg:p-3' }}">

    @unless ($adminNavCompact)
        <div class="border-b border-slate-100 px-2 pb-3 pt-1">
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Quản trị hệ thống</p>
            <div class="mt-2 flex items-center gap-2.5">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full border border-sky-200 bg-sky-50 text-blue-700">
                    <x-lucide name="user-cog" class="h-4 w-4" />
                </span>
                <span class="min-w-0">
                    <span class="block truncate text-[13px] font-bold text-slate-700">{{ $adminName }}</span>
                    <span class="block text-[11px] text-slate-400">{{ $roleLabel }}</span>
                </span>
            </div>
        </div>
    @endunless

    <nav aria-label="Điều hướng quản trị"
         class="{{ $adminNavCompact ? 'grid grid-cols-2 gap-1.5 sm:grid-cols-3' : 'mt-2 flex min-w-0 flex-col gap-1' }}">
        @foreach ($items as $item)
            @php
                $routesToMatch = array_merge([$item['route']], $item['also'] ?? []);
                $isActive = request()->routeIs($routesToMatch);
            @endphp
            <a href="{{ route($item['route']) }}"
               @if ($isActive) aria-current="page" @endif
               class="flex min-h-11 w-full shrink-0 items-center gap-3 rounded-xl px-3 py-2.5 text-left text-xs font-semibold transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-300 {{ $isActive ? 'bg-blue-600 text-white shadow-md shadow-blue-200' : 'text-slate-600 hover:bg-sky-50 hover:text-blue-700' }}">
                <x-lucide :name="$item['icon']" class="h-4 w-4 shrink-0" />
                <span class="min-w-0 truncate">{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>

    @unless ($adminNavCompact)
        <div class="mt-3 rounded-2xl border border-blue-100 bg-gradient-to-br from-sky-50 to-blue-50 p-3">
            <p class="flex items-center gap-1.5 text-[10px] font-bold text-blue-700">
                <x-lucide name="circle-help" class="h-3.5 w-3.5 shrink-0" />Mọi thao tác đều được ghi lại
            </p>
            <p class="mt-1 text-[10px] leading-relaxed text-slate-500">
                Duyệt, ẩn hay thu hồi quyền đều lưu người thao tác, thời gian và lý do.
            </p>
            <a href="{{ route('admin.reports.index') }}" class="mt-2 inline-flex items-center gap-1 text-[10px] font-bold text-blue-600 hover:underline">
                Xem báo cáo vận hành <x-lucide name="chevron-right" class="h-3 w-3" />
            </a>
        </div>
    @endunless
</div>
