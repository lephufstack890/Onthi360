{{-- Sidebar admin/editor độc lập (4.2), đúng 12 mục nav quy định trong BA spec. --}}
@php
    $items = [
        ['label' => 'Tổng quan', 'route' => 'admin.dashboard', 'icon' => '🏠'],
        ['label' => 'Người dùng', 'route' => 'admin.users.index', 'icon' => '👥', 'also' => ['admin.users.show', 'admin.teacher-approvals.index', 'admin.teacher-approvals.show']],
        ['label' => 'Nội dung', 'route' => 'admin.content.index', 'icon' => '🗂️', 'also' => ['admin.content.show']],
        ['label' => 'Khóa & Lớp', 'route' => 'admin.courses.index', 'icon' => '🏫'],
        ['label' => 'Sản phẩm & Quyền', 'route' => 'admin.products.index', 'icon' => '🎫', 'also' => ['admin.products.show', 'admin.access-rights.index']],
        ['label' => 'Đơn hàng', 'route' => 'admin.orders.index', 'icon' => '🧾', 'also' => ['admin.orders.show']],
        ['label' => 'Mã kích hoạt', 'route' => 'admin.activation-codes.index', 'icon' => '🔑'],
        ['label' => 'Đánh giá', 'route' => 'admin.reviews.index', 'icon' => '⭐', 'also' => ['admin.reviews.show']],
        ['label' => 'Cuộc thi', 'route' => 'admin.competitions.index', 'icon' => '🏆', 'also' => ['admin.featured-teachers.index']],
        ['label' => 'Bảng xếp hạng', 'route' => 'admin.ranking.index', 'icon' => '📊'],
        ['label' => 'Báo cáo', 'route' => 'admin.reports.index', 'icon' => '📄'],
        ['label' => 'Cấu hình', 'route' => 'admin.settings.index', 'icon' => '⚙️', 'superAdminOnly' => true],
        ['label' => 'Tài khoản', 'route' => 'admin.profile.show', 'icon' => '👤'],
    ];
    $adminName = auth()->user()->name ?? 'Quản trị viên';
    // 3.1: "Cấu hình hệ thống tối cao" chỉ dành cho Super Admin, không phải Admin thường.
    $isSuperAdmin = auth()->user()?->hasRole(\App\Models\Role::SUPER_ADMIN) ?? false;
    $items = array_values(array_filter($items, fn ($item) => ! ($item['superAdminOnly'] ?? false) || $isSuperAdmin));
@endphp

<div class="flex items-center gap-3 px-3 py-3 mb-2 rounded-xl bg-rose-50/60">
    <img src="https://ui-avatars.com/api/?name={{ urlencode($adminName) }}&background=1e293b&color=ffffff&size=64&bold=true"
         alt="{{ $adminName }}" class="w-9 h-9 rounded-full shrink-0">
    <div class="min-w-0">
        <p class="text-sm font-semibold text-slate-700 truncate">{{ $adminName }}</p>
        <p class="text-xs text-slate-400">Quản trị viên</p>
    </div>
</div>

@foreach ($items as $item)
    @php
        $routesToMatch = array_merge([$item['route']], $item['also'] ?? []);
        $isActive = request()->routeIs($routesToMatch);
    @endphp
    <a href="{{ route($item['route']) }}"
       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium border-l-4 {{ $isActive ? 'bg-rose-50 text-rose-600 border-rose-500' : 'text-slate-600 border-transparent hover:bg-rose-50 hover:text-rose-600' }}">
        <span class="w-7 h-7 rounded-lg flex items-center justify-center text-base {{ $isActive ? 'bg-white' : 'bg-slate-50' }}">{{ $item['icon'] }}</span>
        <span>{{ $item['label'] }}</span>
    </a>
@endforeach
