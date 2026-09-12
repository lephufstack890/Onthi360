{{-- Menu tài khoản ở góc phải thanh trên cùng của khu quản trị.
     SỬA 12/9 — bản riêng cho admin (partials/profile-menu.blade.php gốc giữ nguyên cho
     layouts/app). LOGIC GIỮ NGUYÊN: vẫn suy ra route hồ sơ theo vai trò y như cũ. --}}
@auth
    @php
        $authUser = auth()->user();
        $profileRouteName = null;
        if ($authUser->hasAnyRole(\App\Models\Role::ADMIN, \App\Models\Role::SUPER_ADMIN)) {
            $profileRouteName = 'admin.profile.show';
        } elseif ($authUser->hasRole(\App\Models\Role::STUDENT)) {
            $profileRouteName = 'student.profile';
        }
    @endphp
    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
        <button type="button" @click="open = !open" :aria-expanded="open"
                class="flex min-h-9 items-center gap-2 rounded-xl border border-transparent px-1.5 py-1 text-xs font-bold text-slate-600 transition-colors hover:border-sky-100 hover:bg-sky-50">
            <x-admin.avatar :name="$authUser->name ?? ''" size="sm" />
            <span class="hidden max-w-[140px] truncate sm:inline">{{ $authUser->name }}</span>
            <x-lucide name="chevron-down" class="h-3.5 w-3.5 shrink-0 text-slate-400 transition-transform" ::class="open ? 'rotate-180' : ''" />
        </button>

        <div x-show="open" x-cloak x-transition
             class="absolute right-0 z-50 mt-2 w-52 overflow-hidden rounded-2xl border border-sky-100 bg-white py-1 shadow-xl">
            <div class="border-b border-slate-100 px-3 py-2">
                <p class="truncate text-xs font-bold text-slate-700">{{ $authUser->name }}</p>
                <p class="truncate text-[10px] text-slate-400">{{ $authUser->email }}</p>
            </div>

            @if ($profileRouteName)
                <a href="{{ route($profileRouteName) }}"
                   class="flex items-center gap-2 px-3 py-2.5 text-xs font-bold text-slate-600 transition-colors hover:bg-sky-50 hover:text-blue-700">
                    <x-lucide name="user-cog" class="h-3.5 w-3.5" />Hồ sơ
                </a>
            @endif

            <a href="{{ route('home') }}"
               class="flex items-center gap-2 px-3 py-2.5 text-xs font-bold text-slate-600 transition-colors hover:bg-sky-50 hover:text-blue-700">
                <x-lucide name="home" class="h-3.5 w-3.5" />Về trang công khai
            </a>

            <form method="POST" action="{{ route('logout') }}" class="border-t border-slate-100">
                @csrf
                <button type="submit"
                        class="flex w-full items-center gap-2 px-3 py-2.5 text-left text-xs font-bold text-rose-600 transition-colors hover:bg-rose-50">
                    <x-lucide name="log-out" class="h-3.5 w-3.5" />Đăng xuất
                </button>
            </form>
        </div>
    </div>
@endauth
