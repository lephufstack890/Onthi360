{{-- Nút đổi vai trò trên thanh trên cùng của khu làm việc.
     SỬA 15/10 (khách: "admin có nút chuyển qua giáo viên, giao diện thành giáo viên, chuyển qua lại được; CHỈ admin") —
     · admin thật (đọc CSDL, User::isRealAdmin) đang ở giao diện Quản trị  -> nút "Xem như Giáo viên";
     · đang xem như Giáo viên                                              -> nút "Về Quản trị" (nổi màu cam);
     · người khác: giữ nguyên như cũ (chỉ hiện chữ "Vai trò" khi tài khoản có nhiều vai trò).
     Hai nút là form POST có CSRF; route + controller tự kiểm tra lại admin thật (ActingRoleController). --}}
@auth
    @php $rsUser = auth()->user(); @endphp
    @if ($rsUser->actingRole() !== null)
        <form method="POST" action="{{ route('acting.stop') }}" class="shrink-0">
            @csrf
            <button type="submit" title="Đang xem như Giáo viên — bấm để quay về giao diện Quản trị"
                    class="inline-flex min-h-9 items-center gap-1.5 rounded-xl border border-amber-300 bg-amber-50 px-2.5 py-1.5 text-[12px] font-bold text-amber-800 transition-colors hover:bg-amber-100 sm:px-3">
                <x-lucide name="arrow-left-right" class="h-3.5 w-3.5" />
                <span class="hidden sm:inline">Về Quản trị</span><span class="sm:hidden">Quản trị</span>
            </button>
        </form>
    @elseif ($rsUser->isRealAdmin())
        {{-- SỬA 15/10 (khách: "bỏ nút Xem như Giáo viên trên thanh") — admin chuyển sang Giáo viên ở menu tài khoản
             (partials/admin-profile-menu). Ở đây không hiện gì, kể cả chữ "Vai trò". --}}
    @elseif ($rsUser->roles()->count() > 1)
        <div class="relative">
            <button type="button"
                    class="hidden min-h-9 items-center gap-1.5 rounded-xl border border-sky-100 bg-white px-3 py-1.5 text-[11px] font-bold text-slate-600 transition-colors hover:border-sky-200 hover:bg-sky-50 sm:inline-flex">
                <x-lucide name="users" class="h-3.5 w-3.5" />Vai trò
                <x-lucide name="chevron-down" class="h-3 w-3 text-slate-400" />
            </button>
        </div>
    @endif
@endauth
