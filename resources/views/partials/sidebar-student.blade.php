@php
    $items = [
        ['label' => 'Tổng quan', 'route' => 'dashboard', 'icon' => '🏠'],
        ['label' => 'Khóa học', 'route' => 'student.courses.index', 'icon' => '📚'],
        ['label' => 'Thời khoá biểu', 'route' => 'student.schedule.index', 'icon' => '🗓️'],
        ['label' => 'Luyện tập', 'route' => 'student.practice.index', 'icon' => '📝'],
        ['label' => 'Tài liệu', 'route' => 'materials.index', 'icon' => '📖'],
        ['label' => 'Cuộc thi', 'route' => 'competitions.index', 'icon' => '🏆'],
        ['label' => 'Bảng xếp hạng', 'route' => 'leaderboard.index', 'icon' => '📊'],
        ['label' => 'Ví token', 'route' => 'wallet.index', 'icon' => '💳'],
        ['label' => 'Thông báo', 'route' => 'student.notifications', 'icon' => '🔔'],
        ['label' => 'Hồ sơ', 'route' => 'student.profile', 'icon' => '👤'],
    ];
    $studentName = auth()->user()->name ?? 'Học sinh';
@endphp

<div class="flex items-center gap-3 px-3 py-3 mb-2 rounded-xl bg-rose-50/60">
    <img src="https://ui-avatars.com/api/?name={{ urlencode($studentName) }}&background=e11d48&color=ffffff&size=64&bold=true"
         alt="{{ $studentName }}" class="w-9 h-9 rounded-full shrink-0">
    <div class="min-w-0">
        <p class="text-sm font-semibold text-slate-700 truncate">{{ $studentName }}</p>
        <p class="text-xs text-slate-400">Học sinh</p>
    </div>
</div>

@foreach ($items as $item)
    @php $isActive = request()->routeIs($item['route']); @endphp
    <a href="{{ route($item['route']) }}"
       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium border-l-4 {{ $isActive ? 'bg-rose-50 text-rose-600 border-rose-500' : 'text-slate-600 border-transparent hover:bg-rose-50 hover:text-rose-600' }}">
        <span class="w-7 h-7 rounded-lg flex items-center justify-center text-base {{ $isActive ? 'bg-white' : 'bg-slate-50' }}">{{ $item['icon'] }}</span>
        <span>{{ $item['label'] }}</span>
    </a>
@endforeach
