{{-- ═══════════════ HEADER CÔNG KHAI ═══════════════
     SỬA 9/9 (10) — dựng lại theo ĐÚNG source React khách gửi (education/src/App.jsx, phần
     "1. TOP HEADER"). Giữ nguyên toàn bộ class/bố cục; chỉ đổi phần hành vi cho hợp web thật:
       · nút cuộn tới section  ->  link sang đúng trang (khách dặn "gắn link đầy đủ")
       · ô tìm kiếm            ->  form GET sang trang Tài liệu
       · chuông                ->  thông báo thật ($bellItems)
       · khối avatar           ->  người đang đăng nhập; khách vãng lai thấy Đăng nhập/Đăng ký
     Ảnh nằm ở public/assets/ (chép từ source: header-logo.png, user-avatar.png). --}}
@php
    $publicNavItems = [
        ['label' => 'Trang chủ', 'route' => 'home', 'icon' => 'home'],
        ['label' => 'Lớp học', 'route' => 'courses.index', 'icon' => 'book-open'],
        ['label' => 'Luyện tập', 'route' => 'practice.index', 'icon' => 'code'],
        ['label' => 'Tài liệu', 'route' => 'materials.index', 'icon' => 'file-text'],
        ['label' => 'Cuộc thi', 'route' => 'competitions.index', 'icon' => 'trophy'],
        ['label' => 'Bảng xếp hạng', 'route' => 'leaderboard.index', 'icon' => 'bar-chart'],
        ['label' => 'Giáo viên & Chuyên gia', 'route' => 'teachers.index', 'icon' => 'users'],
        ['label' => 'Thông tin', 'route' => 'info.index', 'icon' => 'info'],
    ];
    $bellItems = $bellItems ?? [];
    $bellUnreadCount = $bellUnreadCount ?? 0;
    $bellViewAllRoute = $bellViewAllRoute ?? null;
@endphp

<header x-data="{ mobileMenuOpen: false }"
        class="sticky top-0 z-50 bg-white/95 backdrop-blur-md border-b border-sky-100 shadow-[0_1px_3px_rgba(0,0,0,0.02)] w-full">
    <div class="max-w-[1780px] mx-auto px-3 sm:px-6 lg:px-8 2xl:px-10 py-2 sm:py-2.5 flex items-center justify-between gap-2 lg:gap-3">

        {{-- Trái: nút menu (màn nhỏ) + logo --}}
        <div class="flex items-center gap-2">
            <button type="button" @click="mobileMenuOpen = !mobileMenuOpen"
                    class="xl:hidden p-1.5 text-slate-600 hover:text-blue-600 hover:bg-sky-50 rounded-lg transition-colors cursor-pointer"
                    aria-label="Toggle Menu">
                <x-lucide name="x" class="w-5 h-5" x-show="mobileMenuOpen" x-cloak />
                <x-lucide name="menu" class="w-5 h-5" x-show="!mobileMenuOpen" />
            </button>

            <a href="{{ route('home') }}" class="flex items-center gap-2 cursor-pointer shrink-0">
                <img src="{{ asset('assets/header-logo.png') }}" alt="Ôn Thi 360 - Học cùng mục tiêu – Vươn xa ước mơ"
                     class="h-7 sm:h-8 lg:h-8.5 xl:h-9 object-contain">
            </a>
        </div>

        {{-- Thanh điều hướng --}}
        <nav class="hidden xl:flex items-center gap-0.5 2xl:gap-1 text-xs 2xl:text-[13px] font-semibold whitespace-nowrap shrink-0">
            @foreach ($publicNavItems as $item)
                @php $isActive = request()->routeIs($item['route']); @endphp
                <a href="{{ route($item['route']) }}"
                   class="flex items-center gap-1 xl:gap-1.5 px-2 xl:px-2.5 py-1.5 rounded-full transition-all cursor-pointer whitespace-nowrap shrink-0 {{ $isActive ? 'bg-[#E6F3FF] text-[#0066CC] shadow-2xs font-bold' : 'text-slate-700 hover:text-blue-600 hover:bg-sky-50' }}">
                    <x-lucide :name="$item['icon']" class="w-3.5 h-3.5 xl:w-4 xl:h-4 shrink-0 {{ $isActive ? 'text-[#0066CC]' : 'text-blue-600' }}" />
                    <span class="whitespace-nowrap">{{ $item['label'] }}</span>
                </a>
                @unless ($loop->last)
                    <span class="text-slate-200 font-light text-xs px-0.5 select-none">|</span>
                @endunless
            @endforeach
        </nav>

        {{-- Tìm kiếm & tài khoản --}}
        <div class="flex items-center gap-1.5 sm:gap-2.5 shrink-0">
            <form method="GET" action="{{ route('materials.index') }}" class="relative hidden 2xl:block">
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Tìm kiếm bài học, đề thi, sách..."
                       class="w-44 2xl:w-56 pl-8 pr-3 py-1.5 text-xs bg-[#F0F6FC] border border-sky-200/80 rounded-full text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:bg-white transition-all shadow-inner">
                <x-lucide name="search" class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2" />
            </form>

            @auth
                @php
                    $headerUser = auth()->user();
                    $headerRoleLabel = $headerUser->hasRole(\App\Models\Role::STUDENT) ? 'Học sinh'
                        : ($headerUser->hasRole(\App\Models\Role::TEACHER) ? 'Giáo viên'
                        : ($headerUser->hasRole(\App\Models\Role::PARENT) ? 'Phụ huynh' : 'Thành viên'));
                @endphp

                <div x-data="{ open: false }" class="relative shrink-0">
                    <button type="button" @click="open = !open" @click.outside="open = false"
                            class="relative p-1.5 text-slate-600 hover:text-blue-600 hover:bg-sky-50 rounded-full transition-colors cursor-pointer shrink-0" aria-label="Thông báo">
                        <x-lucide name="bell" class="w-4.5 h-4.5 text-slate-600" />
                        @if ($bellUnreadCount > 0)
                            <span class="absolute top-1 right-1 w-2.5 h-2.5 bg-amber-400 rounded-full ring-2 ring-white"></span>
                        @endif
                    </button>
                    <div x-show="open" x-cloak x-transition class="absolute right-0 mt-2 w-80 bg-white rounded-2xl border border-sky-100 shadow-xl z-50 overflow-hidden">
                        <div class="max-h-96 overflow-y-auto divide-y divide-sky-50">
                            @forelse ($bellItems as $n)
                                <a href="{{ route('notifications.read', $n['id']) }}"
                                   class="flex items-start gap-2.5 px-4 py-3 hover:bg-sky-50/70 {{ empty($n['read']) ? 'bg-sky-50/50' : '' }}">
                                    <span class="text-base shrink-0">{{ $n['icon'] ?? '🔔' }}</span>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-slate-800 truncate">{{ $n['title'] }}</p>
                                        <p class="text-[11px] text-slate-400 mt-0.5">{{ $n['time'] }}</p>
                                    </div>
                                </a>
                            @empty
                                <p class="px-4 py-6 text-center text-sm text-slate-400">Chưa có thông báo nào.</p>
                            @endforelse
                        </div>
                        @if ($bellViewAllRoute)
                            <a href="{{ $bellViewAllRoute }}" class="block px-4 py-2.5 text-center text-sm font-bold text-blue-600 bg-[#F0F6FC]">Xem tất cả</a>
                        @endif
                    </div>
                </div>

                <div x-data="{ open: false }" class="relative shrink-0">
                    <button type="button" @click="open = !open" @click.outside="open = false"
                            class="flex items-center gap-1.5 sm:gap-2 pl-2 border-l border-slate-200 cursor-pointer shrink-0">
                        <img src="{{ asset('assets/user-avatar.png') }}" alt="{{ $headerUser->name }}"
                             class="w-8 h-8 rounded-full border-2 border-sky-300 object-cover shadow-2xs shrink-0">
                        <span class="text-left hidden 2xl:block leading-tight">
                            <span class="block text-xs font-bold text-slate-800 leading-tight truncate max-w-[110px]">{{ $headerUser->name }}</span>
                            <span class="block text-[10px] text-blue-600 font-medium">{{ $headerRoleLabel }}</span>
                        </span>
                        <x-lucide name="chevron-down" class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                    </button>
                    <div x-show="open" x-cloak x-transition class="absolute right-0 mt-2 w-48 bg-white rounded-2xl border border-sky-100 shadow-xl py-1 z-50">
                        <a href="{{ route('dashboard') }}" class="block px-4 py-2 text-sm text-slate-600 hover:bg-sky-50">Vào học</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full text-left px-4 py-2 text-sm text-rose-600 hover:bg-rose-50 cursor-pointer">Đăng xuất</button>
                        </form>
                    </div>
                </div>
            @else
                <a href="{{ route('login') }}" class="px-3 py-1.5 rounded-full text-xs font-semibold text-slate-700 hover:text-blue-600 hover:bg-sky-50 transition-colors whitespace-nowrap">Đăng nhập</a>
                <a href="{{ route('register') }}" class="px-3.5 py-1.5 rounded-full bg-[#0091FF] hover:bg-blue-600 text-white text-xs font-bold shadow-2xs transition-colors whitespace-nowrap">Đăng ký</a>
            @endauth
        </div>
    </div>

    {{-- Ngăn kéo menu cho màn hình nhỏ --}}
    <div x-show="mobileMenuOpen" x-cloak x-transition
         class="xl:hidden border-t border-sky-100 bg-white px-4 py-3 shadow-lg flex flex-col gap-1">
        @foreach ($publicNavItems as $item)
            @php $isActive = request()->routeIs($item['route']); @endphp
            <a href="{{ route($item['route']) }}"
               class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-semibold text-left transition-colors {{ $isActive ? 'bg-[#E6F3FF] text-[#0066CC] font-bold' : 'text-slate-700 hover:bg-sky-50' }}">
                <x-lucide :name="$item['icon']" class="w-4 h-4 {{ $isActive ? 'text-[#0066CC]' : 'text-blue-600' }}" />
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
        @guest
            <div class="flex items-center gap-2 pt-2 mt-1 border-t border-sky-100">
                <a href="{{ route('login') }}" class="flex-1 text-center px-3 py-2 rounded-xl text-xs font-semibold text-slate-700 bg-sky-50">Đăng nhập</a>
                <a href="{{ route('register') }}" class="flex-1 text-center px-3 py-2 rounded-xl text-xs font-bold text-white bg-[#0091FF]">Đăng ký</a>
            </div>
        @endguest
    </div>
</header>
