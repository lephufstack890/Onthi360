@php
    $items = [
        ['label' => 'Tổng quan', 'route' => 'dashboard', 'icon' => '🏠'],
        ['label' => 'Con của tôi', 'route' => 'parent.children.index', 'icon' => '🧒'],
        ['label' => 'Lịch & Điểm danh', 'route' => 'parent.schedule.index', 'icon' => '📅'],
        ['label' => 'Kết quả & Tiến độ', 'route' => 'parent.results.index', 'icon' => '📈'],
        ['label' => 'Thông báo', 'route' => 'parent.notifications.index', 'icon' => '🔔'],
        ['label' => 'Hồ sơ', 'route' => 'parent.profile', 'icon' => '👤'],
    ];
@endphp
@foreach ($items as $item)
    <a href="{{ route($item['route']) }}"
       class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-slate-600 hover:bg-rose-50 hover:text-rose-600 {{ request()->routeIs($item['route']) ? 'bg-rose-50 text-rose-600' : '' }}">
        <span>{{ $item['icon'] }}</span> {{ $item['label'] }}
    </a>
@endforeach
