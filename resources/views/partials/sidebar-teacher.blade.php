@php
    $items = [
        ['label' => 'Tổng quan', 'route' => 'dashboard', 'icon' => '🏠'],
        ['label' => 'Lớp học', 'route' => 'teacher.classes.index', 'icon' => '🏫'],
        ['label' => 'Bài tập & Đề', 'route' => 'teacher.assessments.create', 'icon' => '🧾'],
        ['label' => 'Kho câu hỏi của tôi', 'route' => 'teacher.questions.index', 'icon' => '❓'],
        ['label' => 'Kết quả', 'route' => 'teacher.results.index', 'icon' => '📈'],
        ['label' => 'Lịch', 'route' => 'teacher.classes.index', 'icon' => '📅'],
        ['label' => 'Thông báo', 'route' => 'dashboard', 'icon' => '🔔'],
        ['label' => 'Hồ sơ', 'route' => 'dashboard', 'icon' => '👤'],
    ];
@endphp
@foreach ($items as $item)
    <a href="{{ route($item['route']) }}"
       class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-slate-600 hover:bg-rose-50 hover:text-rose-600 {{ request()->routeIs($item['route']) ? 'bg-rose-50 text-rose-600' : '' }}">
        <span>{{ $item['icon'] }}</span> {{ $item['label'] }}
    </a>
@endforeach
