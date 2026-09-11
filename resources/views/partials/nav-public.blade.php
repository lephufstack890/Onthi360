{{-- ═══════════════ [GLOBAL-01] HEADER TOÀN TRANG ═══════════════
     Dựng lại đúng theo source giao diện khách gửi: education-main/src/components/Header.jsx.
     Giữ nguyên toàn bộ class/bố cục của source; chỉ đổi phần hành vi cho hợp web thật:
       · nút đổi vai trò (demo) -> menu tài khoản thật của người đang đăng nhập
       · ô tìm kiếm             -> form GET sang trang Tài liệu
       · chuông                 -> thông báo thật ($bellItems)
     Ảnh nằm ở public/assets/ (chép từ source). --}}
@php
    $publicNavItems = [
        ['label' => 'Trang chủ', 'route' => 'home', 'icon' => 'home'],
        ['label' => 'Lớp học', 'route' => 'courses.index', 'icon' => 'book-open'],
        ['label' => 'Luyện tập', 'route' => 'practice.index', 'icon' => 'code'],
        ['label' => 'Tài liệu', 'route' => 'materials.index', 'icon' => 'file-text'],
        ['label' => 'Cuộc thi', 'route' => 'competitions.index', 'icon' => 'trophy'],
        ['label' => 'Bảng xếp hạng', 'route' => 'leaderboard.index', 'icon' => 'bar-chart'],
        ['label' => 'Giáo viên & chuyên gia', 'route' => 'teachers.index', 'icon' => 'users'],
        ['label' => 'Thông tin', 'route' => 'info.index', 'icon' => 'info'],
    ];
    $bellItems = $bellItems ?? [];
    $bellUnreadCount = $bellUnreadCount ?? 0;
    $bellViewAllRoute = $bellViewAllRoute ?? null;

    $headerUser = auth()->user();
    $headerRoleLabel = 'Khách vãng lai';
    $headerSub = 'Chưa đăng nhập';
    if ($headerUser) {
        $headerRoleLabel = $headerUser->hasRole(\App\Models\Role::ADMIN) ? 'Quản trị viên'
            : ($headerUser->hasRole(\App\Models\Role::TEACHER) ? 'Giáo viên'
            : ($headerUser->hasRole(\App\Models\Role::PARENT) ? 'Phụ huynh'
            : ($headerUser->hasRole(\App\Models\Role::STUDENT) ? 'Học sinh' : 'Thành viên')));
        $headerSub = $headerUser->email;
    }
@endphp

<header x-data="{ mobileNavOpen: false, notificationOpen: false, roleDropdownOpen: false, closeTimer: null,
                  openMenu() { clearTimeout(this.closeTimer); this.roleDropdownOpen = true },
                  scheduleClose() { clearTimeout(this.closeTimer); this.closeTimer = setTimeout(() => this.roleDropdownOpen = false, 160) } }"
        class="header-typography relative sticky top-0 z-50 bg-white/95 backdrop-blur-md border-b border-sky-100 shadow-[0_1px_3px_rgba(0,0,0,0.02)] w-full">
    <div class="w-full px-3 sm:px-5 lg:px-4 2xl:px-8 py-2 sm:py-2.5 flex items-center gap-2">

        {{-- [GLOBAL-01A] Logo Ôn Thi 360 --}}
        <div class="flex shrink-0 items-center min-w-[144px]">
            <a href="{{ route('home') }}" class="flex items-center gap-2 cursor-pointer shrink-0 group">
                <img src="{{ asset('assets/header-logo.png') }}"
                     alt="Ôn Thi 360 - Học cùng mục tiêu – Vươn xa ước mơ"
                     class="h-7 sm:h-8 lg:h-8.5 object-contain group-hover:scale-102 transition-transform">
            </a>
        </div>

        <button type="button" @click="mobileNavOpen = !mobileNavOpen"
                class="grid h-9 w-9 shrink-0 place-items-center rounded-xl border border-sky-100 bg-white text-slate-600 transition-colors hover:bg-sky-50 hover:text-[#126F91] focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-400 lg:hidden"
                :aria-label="mobileNavOpen ? 'Đóng menu điều hướng' : 'Mở menu điều hướng'"
                :aria-expanded="mobileNavOpen">
            <x-lucide name="x" class="h-4.5 w-4.5" x-show="mobileNavOpen" x-cloak />
            <x-lucide name="menu" class="h-4.5 w-4.5" x-show="!mobileNavOpen" />
        </button>

        {{-- [GLOBAL-01B] MENU TOP — điều hướng chính trên màn hình desktop --}}
        <nav class="hidden lg:flex min-w-0 flex-1 items-center justify-between gap-0.5 rounded-2xl border border-sky-100/80 bg-sky-50/50 p-1 text-xs 2xl:text-[13px] font-semibold leading-snug whitespace-nowrap">
            @foreach ($publicNavItems as $item)
                @php $isActive = request()->routeIs($item['route']); @endphp
                <a href="{{ route($item['route']) }}"
                   class="flex items-center gap-1 px-1.5 xl:gap-1.5 xl:px-2.5 py-2 rounded-xl transition-all cursor-pointer whitespace-nowrap shrink-0 {{ $isActive ? 'bg-white text-[#0066CC] shadow-sm ring-1 ring-blue-100' : 'text-slate-700 hover:text-blue-700 hover:bg-white/90' }}">
                    <x-lucide :name="$item['icon']" class="w-4 h-4 shrink-0 {{ $isActive ? 'text-[#0066CC]' : 'text-blue-600' }}" />
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>

        {{-- [GLOBAL-01C] TIỆN ÍCH HEADER — tìm kiếm, thông báo và tài khoản --}}
        <div class="ml-auto flex items-center gap-1.5 sm:gap-2 shrink-0">

            {{-- [GLOBAL-01C1] Ô tìm kiếm --}}
            <form method="GET" action="{{ route('materials.index') }}" class="relative hidden min-[1600px]:block">
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Tìm bài học, đề thi, sách..."
                       class="w-40 min-[2048px]:w-44 pl-8 pr-3 py-1.5 text-xs bg-[#F0F6FC] border border-sky-200/80 rounded-full text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:bg-white transition-all shadow-inner">
                <x-lucide name="search" class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2" />
            </form>

            {{-- [GLOBAL-01C2] Nút và bảng thông báo --}}
            <div class="relative" @click.outside="notificationOpen = false">
                <button type="button" @click="notificationOpen = !notificationOpen"
                        class="relative p-1.5 text-slate-600 hover:text-blue-600 hover:bg-sky-50 rounded-full transition-colors cursor-pointer shrink-0"
                        aria-label="Thông báo">
                    <x-lucide name="bell" class="w-4.5 h-4.5 text-slate-600" />
                    @if ($bellUnreadCount > 0)
                        <span class="absolute top-1 right-1 w-2.5 h-2.5 bg-amber-400 rounded-full ring-2 ring-white"></span>
                    @endif
                </button>

                <div x-show="notificationOpen" x-cloak x-transition
                     class="absolute right-0 top-10 w-72 sm:w-80 bg-white border border-sky-100 rounded-2xl shadow-xl p-3 z-50">
                    <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                        <h4 class="type-card-title text-slate-800">Thông báo mới</h4>
                        @if ($bellViewAllRoute)
                            <a href="{{ $bellViewAllRoute }}" class="type-action text-blue-600 cursor-pointer hover:underline">Xem tất cả</a>
                        @endif
                    </div>
                    <div class="flex flex-col gap-2 py-2 max-h-80 overflow-y-auto">
                        @forelse ($bellItems as $n)
                            <a href="{{ route('notifications.read', $n['id']) }}"
                               class="p-2 rounded-xl text-xs transition-colors {{ empty($n['read']) ? 'bg-blue-50/60 border border-blue-100/80' : 'bg-slate-50 hover:bg-sky-50' }}">
                                <p class="type-card-title flex items-center gap-1.5">
                                    <span class="shrink-0">{{ $n['icon'] ?? '🔔' }}</span>{{ $n['title'] }}
                                </p>
                                @if (!empty($n['body']))
                                    <p class="type-body mt-0.5 text-slate-600">{{ $n['body'] }}</p>
                                @endif
                                <span class="type-meta mt-1 block text-slate-400">{{ $n['time'] ?? '' }}</span>
                            </a>
                        @empty
                            <p class="type-body py-4 text-center text-slate-400">Chưa có thông báo nào.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- [GLOBAL-01C3] MENU NGƯỜI DÙNG — tài khoản, quyền và kích hoạt --}}
            <div class="relative" @mouseenter="openMenu()" @mouseleave="scheduleClose()" @click.outside="roleDropdownOpen = false">
                <button type="button" @click="roleDropdownOpen = !roleDropdownOpen"
                        class="flex items-center gap-1.5 sm:gap-2 pl-2 border-l border-slate-200 cursor-pointer shrink-0 select-none rounded-r-full focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-400"
                        aria-label="Mở menu người dùng" :aria-expanded="roleDropdownOpen">
                    <img src="{{ asset('assets/user-avatar.png') }}" alt="Avatar"
                         class="w-8 h-8 rounded-full border-2 border-sky-300 object-cover shadow-2xs shrink-0">
                    <span class="text-left hidden min-[1800px]:block leading-tight">
                        <span class="block text-[13px] font-bold text-slate-800 leading-snug truncate max-w-[140px]">{{ $headerUser?->name ?? 'Khách khám phá' }}</span>
                        <span class="flex items-center gap-1">
                            <span class="text-xs text-blue-600 font-bold bg-blue-50 px-1.5 py-0.5 rounded-md border border-blue-200">{{ $headerRoleLabel }}</span>
                        </span>
                    </span>
                    <x-lucide name="chevron-down" class="w-4 h-4 text-slate-500 shrink-0 transition-transform" ::class="roleDropdownOpen ? 'rotate-180' : ''" />
                </button>

                <div x-show="roleDropdownOpen" x-cloak x-transition
                     @mouseenter="openMenu()" @mouseleave="scheduleClose()"
                     class="fixed left-3 right-3 top-[68px] max-h-[calc(100dvh-156px)] overflow-y-auto rounded-3xl border border-sky-100 bg-white p-3 shadow-xl z-[70] sm:absolute sm:left-auto sm:right-0 sm:top-[calc(100%+6px)] sm:max-h-[min(70vh,600px)] sm:w-72 sm:rounded-2xl">
                    <div class="px-2 py-1.5 mb-2 border-b border-slate-100">
                        <p class="type-label uppercase tracking-[0.06em] text-slate-500">Tài khoản của tôi</p>
                    </div>

                    <div class="flex items-center gap-3 p-2.5 rounded-xl bg-blue-50/90 border border-blue-200">
                        <img src="{{ asset('assets/user-avatar.png') }}" alt="{{ $headerUser?->name ?? 'Khách' }}"
                             class="w-8 h-8 rounded-full object-cover border border-sky-200">
                        <span class="overflow-hidden">
                            <span class="type-card-title block truncate">{{ $headerUser?->name ?? 'Khách khám phá' }}</span>
                            <span class="type-body mt-0.5 block truncate text-slate-500">{{ $headerSub }}</span>
                        </span>
                    </div>

                    <div class="mt-2.5 pt-2.5 border-t border-slate-100 flex flex-col gap-1.5">
                        @auth
                            <a href="{{ route('dashboard') }}"
                               class="w-full py-2.5 px-3 rounded-xl text-[13px] font-bold text-blue-700 bg-blue-50 hover:bg-blue-100 flex items-center gap-2.5 transition-colors cursor-pointer">
                                <x-lucide name="graduation-cap" class="w-4 h-4 text-blue-600" />
                                <span>Vào khu học tập</span>
                            </a>
                            <a href="{{ route('access.myAccess') }}"
                               class="w-full py-2.5 px-3 rounded-xl text-[13px] font-bold text-slate-700 bg-slate-50 hover:bg-slate-100 flex items-center gap-2.5 transition-colors cursor-pointer">
                                <x-lucide name="shield-check" class="w-4 h-4 text-slate-500" />
                                <span>Quyền của tôi</span>
                            </a>
                            <a href="{{ route('access.activate') }}"
                               class="w-full py-2.5 px-3 rounded-xl text-[13px] font-bold text-amber-700 bg-amber-50 hover:bg-amber-100 flex items-center gap-2.5 transition-colors cursor-pointer">
                                <x-lucide name="key-round" class="w-4 h-4 text-amber-600" />
                                <span>Nhập mã kích hoạt quyền</span>
                            </a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit"
                                        class="w-full py-2.5 px-3 rounded-xl text-[13px] font-bold text-rose-600 bg-rose-50 hover:bg-rose-100 flex items-center gap-2.5 transition-colors cursor-pointer">
                                    <x-lucide name="log-out" class="w-4 h-4 text-rose-500" />
                                    <span>Đăng xuất</span>
                                </button>
                            </form>
                        @else
                            <a href="{{ route('login') }}"
                               class="w-full py-2.5 px-3 rounded-xl text-[13px] font-bold text-blue-700 bg-blue-50 hover:bg-blue-100 flex items-center gap-2.5 transition-colors cursor-pointer">
                                <x-lucide name="shield-check" class="w-4 h-4 text-blue-600" />
                                <span>Đăng nhập</span>
                            </a>
                            <a href="{{ route('register') }}"
                               class="w-full py-2.5 px-3 rounded-xl text-[13px] font-bold text-amber-700 bg-amber-50 hover:bg-amber-100 flex items-center gap-2.5 transition-colors cursor-pointer">
                                <x-lucide name="key-round" class="w-4 h-4 text-amber-600" />
                                <span>Đăng ký tài khoản</span>
                            </a>
                        @endauth
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Ngăn kéo điều hướng cho màn hình nhỏ --}}
    <div x-show="mobileNavOpen" x-cloak x-transition
         class="absolute left-0 right-0 top-full border-t border-sky-100 bg-white p-3 shadow-xl lg:hidden">
        <nav aria-label="Điều hướng chính trên thiết bị di động" class="grid grid-cols-2 gap-2 sm:grid-cols-4">
            @foreach ($publicNavItems as $item)
                @php $isActive = request()->routeIs($item['route']); @endphp
                <a href="{{ route($item['route']) }}"
                   class="flex min-h-11 items-center gap-2 rounded-xl border px-3 py-2 text-left text-[11px] font-bold transition-colors {{ $isActive ? 'border-blue-200 bg-blue-50 text-blue-700' : 'border-slate-100 bg-slate-50 text-slate-700 hover:border-sky-200 hover:bg-sky-50' }}">
                    <x-lucide :name="$item['icon']" class="h-4 w-4 shrink-0 {{ $isActive ? 'text-blue-600' : 'text-slate-500' }}" />
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>
    </div>
</header>
