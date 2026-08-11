@php
    $items = [
        ['label' => 'Tổng quan', 'route' => 'dashboard', 'icon' => '🏠'],
        ['label' => 'Khóa học', 'route' => 'student.courses.index', 'icon' => '📚'],
        ['label' => 'Luyện tập', 'route' => 'student.practice.index', 'icon' => '📝'],
        ['label' => 'Tài liệu', 'route' => 'materials.index', 'icon' => '📖'],
        ['label' => 'Cuộc thi', 'route' => 'competitions.index', 'icon' => '🏆'],
        ['label' => 'Bảng xếp hạng', 'route' => 'leaderboard.index', 'icon' => '📊'],
        ['label' => 'Thông báo', 'route' => 'student.notifications', 'icon' => '🔔'],
        ['label' => 'Hồ sơ', 'route' => 'student.profile', 'icon' => '👤'],
    ];
@endphp
@foreach ($items as $item)
    <a href="{{ route($item['route']) }}"
       class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-slate-600 hover:bg-rose-50 hover:text-rose-600 {{ request()->routeIs($item['route']) ? 'bg-rose-50 text-rose-600' : '' }}">
        <span>{{ $item['icon'] }}</span> {{ $item['label'] }}
    </a>
@endforeach
