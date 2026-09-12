{{-- Nút đổi vai trò trên thanh trên cùng của khu quản trị.
     SỬA 12/9 — bản riêng cho admin. partials/role-switcher.blade.php gốc vẫn giữ nguyên vì
     layouts/app (giáo viên / học sinh / phụ huynh) đang dùng, không thuộc phạm vi lần này.
     LOGIC GIỮ NGUYÊN: vẫn chỉ hiện khi tài khoản có nhiều hơn 1 vai trò. --}}
@auth
    @if (auth()->user()->roles()->count() > 1)
        <div class="relative">
            <button type="button"
                    class="hidden min-h-9 items-center gap-1.5 rounded-xl border border-sky-100 bg-white px-3 py-1.5 text-[11px] font-bold text-slate-600 transition-colors hover:border-sky-200 hover:bg-sky-50 sm:inline-flex">
                <x-lucide name="users" class="h-3.5 w-3.5" />Vai trò
                <x-lucide name="chevron-down" class="h-3 w-3 text-slate-400" />
            </button>
        </div>
    @endif
@endauth
