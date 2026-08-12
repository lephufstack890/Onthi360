{{--
  Dropdown Hồ sơ / Đăng xuất — dùng chung cho mọi layout (layouts.app @include).
  "Hồ sơ" trỏ theo vai trò hiện có trang thật: student.profile (học sinh) hoặc
  admin.profile.show (admin/super_admin). Vai trò chưa có trang hồ sơ riêng
  (giáo viên/phụ huynh) thì ẩn mục này đi, tránh link 404 — bổ sung route khi
  trang tương ứng được xây.
--}}
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
        <button type="button" @click="open = !open"
                class="flex items-center gap-2 text-sm text-slate-600">
            <span class="w-8 h-8 rounded-full bg-slate-200 inline-flex items-center justify-center text-xs font-medium text-slate-600">
                {{ mb_substr($authUser->name ?? '?', 0, 1) }}
            </span>
            {{ $authUser->name }}
        </button>

        <div x-show="open" x-cloak
             class="absolute right-0 mt-2 w-48 bg-white rounded-xl border border-slate-200 shadow-lg py-1 z-20">
            @if ($profileRouteName)
                <a href="{{ route($profileRouteName) }}"
                   class="block px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Hồ sơ</a>
            @endif
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-rose-600 hover:bg-rose-50">
                    Đăng xuất
                </button>
            </form>
        </div>
    </div>
@endauth
