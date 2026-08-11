@auth
    <div class="relative">
        <button type="button" class="flex items-center gap-2 text-sm text-slate-600">
            <span class="w-8 h-8 rounded-full bg-slate-200 inline-block"></span>
            {{ auth()->user()->name }}
        </button>
        {{-- TODO: dropdown Hồ sơ / Đăng xuất --}}
    </div>
@endauth
