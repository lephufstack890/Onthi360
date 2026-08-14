{{--
  Dùng chung cho cả 2 khối "Phụ huynh liên kết" (xem từ trang học sinh) và "Con đã liên kết"
  (xem từ trang phụ huynh) ở admin.users.show — cùng 1 dòng ParentLink, chỉ khác góc nhìn.
  Xác minh (10.3: "xác minh phụ huynh chặt chẽ") không cần lý do; Từ chối/thu hồi PHẢI có lý
  do (16 mục 4) — xem App\Services\Admin\UserService::approveParentLink()/rejectParentLink().
--}}
<div x-data="{ rejecting: false }" class="mt-2">
    <div class="flex items-center gap-2">
        <form method="POST" action="{{ route('admin.parent-links.approve', $link->id) }}">
            @csrf
            <button type="submit" class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-medium hover:bg-emerald-700 transition">Xác minh</button>
        </form>
        <button type="button" @click="rejecting = !rejecting" class="px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 text-xs font-medium hover:border-rose-200 hover:text-rose-600 transition">Từ chối</button>
    </div>
    <form method="POST" action="{{ route('admin.parent-links.reject', $link->id) }}" x-show="rejecting" x-cloak class="mt-2 flex gap-2">
        @csrf
        <input type="text" name="reason" required maxlength="1000" placeholder="Lý do từ chối..."
               class="flex-1 rounded-lg border border-slate-200 text-xs p-2 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
        <button type="submit" class="px-3 py-1.5 rounded-lg bg-rose-600 text-white text-xs font-medium shrink-0 hover:bg-rose-700 transition">Gửi</button>
    </form>
</div>
