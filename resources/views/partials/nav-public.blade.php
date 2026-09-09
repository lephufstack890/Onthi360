@php
    $publicNavItems = [
        ['label' => 'Trang chủ', 'route' => 'home', 'icon' => 'home'],
        ['label' => 'Lớp học', 'route' => 'courses.index', 'icon' => 'class'],
        ['label' => 'Luyện tập', 'route' => 'practice.index', 'icon' => 'code'],
        ['label' => 'Tài liệu', 'route' => 'materials.index', 'icon' => 'doc'],
        ['label' => 'Cuộc thi', 'route' => 'competitions.index', 'icon' => 'trophy'],
        ['label' => 'Bảng xếp hạng', 'route' => 'leaderboard.index', 'icon' => 'chart'],
        ['label' => 'Giáo viên và chuyên gia', 'route' => 'teachers.index', 'icon' => 'users'],
        ['label' => 'Thông tin', 'route' => 'info.index', 'icon' => 'info'],
    ];
@endphp

<header class="bg-white border-b border-slate-200/80 sticky top-0 z-40">
    <div class="max-w-[1600px] mx-auto px-4 xl:px-5 h-[68px] flex items-center gap-3">

        <a href="{{ route('home') }}" class="flex items-center gap-2.5 shrink-0">
            <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-sky-400 to-blue-600 flex items-center justify-center shadow-sm shadow-blue-200">
                <svg viewBox="0 0 24 24" fill="none" class="w-6 h-6 text-white">
                    <path d="M12 3 3 7.5 12 12l9-4.5L12 3Z" fill="currentColor" opacity=".95"/>
                    <path d="M6 10.5v4.2c0 1.8 2.7 3.3 6 3.3s6-1.5 6-3.3v-4.2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                </svg>
            </span>
            <span class="leading-none">
                <span class="block text-[19px] font-extrabold text-slate-800 tracking-tight">Ôn Thi <span class="text-amber-400">360</span></span>
                <span class="hidden sm:block text-[10px] text-slate-400 mt-1">Học đúng mục tiêu · Vươn xa ước mơ</span>
            </span>
        </a>

        <nav class="hidden xl:flex items-center gap-0 mx-auto flex-nowrap">
            @foreach ($publicNavItems as $item)
                @php $isActive = request()->routeIs($item['route']); @endphp
                <a href="{{ route($item['route']) }}"
                   class="flex items-center gap-1 px-1.5 py-2 rounded-xl text-[13.5px] font-semibold whitespace-nowrap transition {{ $isActive ? 'bg-sky-50 text-sky-600' : 'text-slate-500 hover:bg-slate-50 hover:text-sky-600' }}">
                    <x-nav-icon :name="$item['icon']" class="w-3.5 h-3.5 shrink-0" />
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="flex items-center gap-3 ml-auto xl:ml-0 shrink-0">
            <form method="GET" action="{{ route('materials.index') }}" class="hidden md:block relative">
                <svg viewBox="0 0 24 24" fill="none" class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2">
                    <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/>
                    <path d="m20 20-3.5-3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Tìm kiếm bài học, đề thi, sách…"
                       class="w-40 h-10 pl-10 pr-4 rounded-full bg-slate-50 border border-slate-200 text-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-300 transition">
            </form>

            @auth
                @php
                    $bellItems = $bellItems ?? [];
                    $bellUnreadCount = $bellUnreadCount ?? 0;
                    $bellViewAllRoute = $bellViewAllRoute ?? null;
                    $headerUser = auth()->user();
                    $headerRoleLabel = $headerUser->hasRole(\App\Models\Role::STUDENT) ? 'Học sinh'
                        : ($headerUser->hasRole(\App\Models\Role::TEACHER) ? 'Giáo viên'
                        : ($headerUser->hasRole(\App\Models\Role::PARENT) ? 'Phụ huynh' : 'Thành viên'));
                @endphp

                <div x-data="{ open: false }" class="relative">
                    <button type="button" @click="open = !open" @click.outside="open = false"
                            class="relative w-10 h-10 rounded-xl text-slate-500 hover:bg-slate-50 flex items-center justify-center transition" aria-label="Thông báo">
                        <x-nav-icon name="bell" class="w-5 h-5" />
                        @if ($bellUnreadCount > 0)
                            <span class="absolute top-1.5 right-1.5 min-w-[16px] h-4 px-1 rounded-full bg-amber-400 text-white text-[10px] font-bold flex items-center justify-center">{{ $bellUnreadCount > 9 ? '9+' : $bellUnreadCount }}</span>
                        @endif
                    </button>
                    <div x-show="open" x-cloak x-transition class="absolute right-0 mt-2 w-80 bg-white rounded-2xl border border-slate-200 shadow-xl z-50 overflow-hidden">
                        <div class="max-h-96 overflow-y-auto divide-y divide-slate-100">
                            @forelse ($bellItems as $n)
                                <a href="{{ route('notifications.read', $n['id']) }}"
                                   class="flex items-start gap-2.5 px-4 py-3 hover:bg-slate-50 {{ empty($n['read']) ? 'bg-sky-50/50' : '' }}">
                                    <span class="text-lg shrink-0">{{ $n['icon'] ?? '🔔' }}</span>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-slate-700 truncate">{{ $n['title'] }}</p>
                                        <p class="text-xs text-slate-400 mt-0.5">{{ $n['time'] }}</p>
                                    </div>
                                </a>
                            @empty
                                <p class="px-4 py-6 text-center text-sm text-slate-400">Chưa có thông báo nào.</p>
                            @endforelse
                        </div>
                        @if ($bellViewAllRoute)
                            <a href="{{ $bellViewAllRoute }}" class="block px-4 py-2.5 text-center text-sm font-semibold text-sky-600 bg-slate-50">Xem tất cả</a>
                        @endif
                    </div>
                </div>

                <div x-data="{ open: false }" class="relative">
                    <button type="button" @click="open = !open" @click.outside="open = false"
                            class="flex items-center gap-2.5 pl-1 pr-2 py-1 rounded-xl hover:bg-slate-50 transition">
                        <img src="https://ui-avatars.com/api/?name={{ urlencode($headerUser->name ?? 'U') }}&background=dbeafe&color=1d4ed8&size=80&bold=true"
                             alt="{{ $headerUser->name }}" class="w-9 h-9 rounded-full object-cover shrink-0">
                        <span class="hidden lg:block text-left leading-tight">
                            <span class="block text-[13px] font-bold text-slate-700">{{ $headerUser->name }}</span>
                            <span class="block text-[11px] text-slate-400">{{ $headerRoleLabel }}</span>
                        </span>
                        <svg viewBox="0 0 24 24" fill="none" class="w-4 h-4 text-slate-400 hidden lg:block"><path d="m7 10 5 5 5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                    <div x-show="open" x-cloak x-transition class="absolute right-0 mt-2 w-48 bg-white rounded-2xl border border-slate-200 shadow-xl py-1 z-50">
                        <a href="{{ route('dashboard') }}" class="block px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Vào học</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full text-left px-4 py-2 text-sm text-rose-600 hover:bg-rose-50">Đăng xuất</button>
                        </form>
                    </div>
                </div>
            @else
                <a href="{{ route('login') }}" class="px-4 py-2 rounded-xl text-sm font-semibold text-slate-600 hover:text-sky-600 transition">Đăng nhập</a>
                <a href="{{ route('register') }}" class="px-4 py-2 rounded-xl bg-gradient-to-r from-sky-500 to-blue-600 text-white text-sm font-semibold shadow-sm shadow-blue-200 hover:from-sky-600 hover:to-blue-700 transition">Đăng ký</a>
            @endauth

            <div x-data="{ open: false }" class="xl:hidden relative">
                <button type="button" @click="open = !open" aria-label="Mở menu"
                        class="w-10 h-10 rounded-xl border border-slate-200 text-slate-500 flex items-center justify-center">☰</button>
                <div x-show="open" x-cloak @click.outside="open = false"
                     class="absolute right-0 mt-2 w-60 rounded-2xl bg-white border border-slate-200 shadow-xl p-2 z-50">
                    @foreach ($publicNavItems as $item)
                        <a href="{{ route($item['route']) }}"
                           class="flex items-center gap-2 px-3 py-2 rounded-xl text-sm font-medium {{ request()->routeIs($item['route']) ? 'bg-sky-50 text-sky-600' : 'text-slate-600 hover:bg-slate-50' }}">
                            <x-nav-icon :name="$item['icon']" />
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</header>
