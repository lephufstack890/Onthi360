@php
    // "Con của tôi"/"Lịch & Điểm danh"/"Kết quả & Tiến độ" đều có thể kết thúc ở CÙNG 1 route
    // parent.children.show (khi phụ huynh chỉ có 1 con đã xác minh, Parent\ScheduleController/
    // ResultController tự chuyển thẳng vào tab tương ứng thay vì ở lại route riêng của chúng)
    // — nên không thể chỉ so khớp tên route như các sidebar khác, phải xét thêm query "tab".
    $onChildShow = request()->routeIs('parent.children.show');
    $currentTab = $onChildShow ? request()->query('tab', 'overview') : null;

    $items = [
        ['label' => 'Tổng quan', 'route' => 'dashboard', 'icon' => '🏠', 'active' => request()->routeIs('dashboard')],
        ['label' => 'Con của tôi', 'route' => 'parent.children.index', 'icon' => '🧒',
            'active' => request()->routeIs('parent.children.index') || ($onChildShow && ! in_array($currentTab, ['schedule', 'results'], true))],
        ['label' => 'Lịch & Điểm danh', 'route' => 'parent.schedule.index', 'icon' => '📅',
            'active' => request()->routeIs('parent.schedule.index') || ($onChildShow && $currentTab === 'schedule')],
        ['label' => 'Kết quả & Tiến độ', 'route' => 'parent.results.index', 'icon' => '📈',
            'active' => request()->routeIs('parent.results.index') || ($onChildShow && $currentTab === 'results')],
        ['label' => 'Thông báo', 'route' => 'parent.notifications.index', 'icon' => '🔔', 'active' => request()->routeIs('parent.notifications.index')],
        ['label' => 'Hồ sơ', 'route' => 'parent.profile', 'icon' => '👤', 'active' => request()->routeIs('parent.profile')],
    ];
@endphp
@foreach ($items as $item)
    <a href="{{ route($item['route']) }}"
       class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-slate-600 hover:bg-rose-50 hover:text-rose-600 {{ $item['active'] ? 'bg-rose-50 text-rose-600' : '' }}">
        <span>{{ $item['icon'] }}</span> {{ $item['label'] }}
    </a>
@endforeach
