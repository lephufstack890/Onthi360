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
        ['label' => 'Cấu hình', 'route' => 'admin.settings.index', 'icon' => '⚙️'],
    ];
@endphp
@foreach ($items as $item)
    @php
        $routesToMatch = array_merge([$item['route']], $item['also'] ?? []);
        $isActive = request()->routeIs($routesToMatch);
    @endphp
    <a href="{{ route($item['route']) }}"
       class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-slate-600 hover:bg-rose-50 hover:text-rose-600 {{ $isActive ? 'bg-rose-50 text-rose-600' : '' }}">
        <span>{{ $item['icon'] }}</span> {{ $item['label'] }}
    </a>
@endforeach
