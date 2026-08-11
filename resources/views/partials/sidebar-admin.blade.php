@php
    $items = [
        ['label' => 'Tổng quan', 'route' => 'admin.dashboard', 'icon' => '🏠'],
        ['label' => 'Người dùng', 'route' => 'admin.users.index', 'icon' => '👥'],
        ['label' => 'Phê duyệt giáo viên', 'route' => 'admin.teacher-approvals.index', 'icon' => '✅'],
        ['label' => 'Nội dung', 'route' => 'admin.content.index', 'icon' => '🗂️'],
        ['label' => 'Khóa & Lớp', 'route' => 'admin.content.index', 'icon' => '🏫'],
        ['label' => 'Sản phẩm & Quyền', 'route' => 'admin.products.index', 'icon' => '🎫'],
        ['label' => 'Đơn hàng', 'route' => 'admin.orders.index', 'icon' => '🧾'],
        ['label' => 'Mã kích hoạt', 'route' => 'admin.activation-codes.index', 'icon' => '🔑'],
        ['label' => 'Đánh giá', 'route' => 'admin.reviews.index', 'icon' => '⭐'],
        ['label' => 'Cuộc thi', 'route' => 'admin.competitions.index', 'icon' => '🏆'],
        ['label' => 'Bảng xếp hạng', 'route' => 'admin.ranking.index', 'icon' => '📊'],
        ['label' => 'Báo cáo', 'route' => 'admin.reports.index', 'icon' => '📄'],
        ['label' => 'Cấu hình', 'route' => 'admin.settings.index', 'icon' => '⚙️'],
    ];
@endphp
@foreach ($items as $item)
    <a href="{{ route($item['route']) }}"
       class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-slate-600 hover:bg-rose-50 hover:text-rose-600 {{ request()->routeIs($item['route']) ? 'bg-rose-50 text-rose-600' : '' }}">
        <span>{{ $item['icon'] }}</span> {{ $item['label'] }}
    </a>
@endforeach
