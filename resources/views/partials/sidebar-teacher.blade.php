@php
    $items = [
        ['label' => 'Tổng quan', 'route' => 'dashboard', 'icon' => '🏠'],
        ['label' => 'Lớp học', 'route' => 'teacher.classes.index', 'icon' => '🏫'],
        ['label' => 'Bài tập & Đề', 'route' => 'teacher.assessments.index', 'icon' => '🧾'],
        ['label' => 'Kho câu hỏi của tôi', 'route' => 'teacher.questions.index', 'icon' => '❓'],
        ['label' => 'Kết quả', 'route' => 'teacher.results.index', 'icon' => '📈'],
        ['label' => 'Lịch', 'route' => 'teacher.schedule.index', 'icon' => '📅'],
        ['label' => 'Thông báo', 'route' => 'teacher.notifications.index', 'icon' => '🔔'],
        ['label' => 'Hồ sơ', 'route' => 'teacher.profile.show', 'icon' => '👤'],
    ];
    $teacherName = auth()->user()->name ?? 'Giáo viên';
@endphp

<div class="flex items-center gap-3 px-3 py-3 mb-2 rounded-xl bg-rose-50/60">
    <img src="https://ui-avatars.com/api/?name={{ urlencode($teacherName) }}&background=e11d48&color=ffffff&size=64&bold=true"
         alt="{{ $teacherName }}" class="w-9 h-9 rounded-full shrink-0">
    <div class="min-w-0">
        <p class="text-sm font-semibold text-slate-700 truncate">{{ $teacherName }}</p>
        <p class="text-xs text-slate-400">Giáo viên</p>
    </div>
</div>

@php
    // Vài mục (Lịch, Thông báo, Hồ sơ) tạm trỏ lại route của mục khác vì
    // chưa có trang riêng -- nếu so khớp route như bình thường thì 2-3 mục sẽ
    // cùng sáng "active" một lúc, gây rối. Chỉ mục ĐẦU TIÊN khai báo cho mỗi
    // route mới được tính là "chủ" của route đó và được phép sáng active;
    // các mục trỏ tạm theo sau sẽ không bao giờ tự sáng lên.
    $primaryRoutes = [];
@endphp
@foreach ($items as $item)
    @php
        $isPrimaryForRoute = !in_array($item['route'], $primaryRoutes, true);
        if ($isPrimaryForRoute) {
            $primaryRoutes[] = $item['route'];
        }
        $isActive = $isPrimaryForRoute && request()->routeIs($item['route']);
    @endphp
    <a href="{{ route($item['route']) }}"
       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium border-l-4 {{ $isActive ? 'bg-rose-50 text-rose-600 border-rose-500' : 'text-slate-600 border-transparent hover:bg-rose-50 hover:text-rose-600' }}">
        <span class="w-7 h-7 rounded-lg flex items-center justify-center text-base {{ $isActive ? 'bg-white' : 'bg-slate-50' }}">{{ $item['icon'] }}</span>
        <span>{{ $item['label'] }}</span>
    </a>
@endforeach
