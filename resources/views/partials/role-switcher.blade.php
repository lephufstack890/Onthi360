{{--
  Role switcher (4.3): user có nhiều role (vd vừa dạy vừa là phụ huynh)
  phải chuyển không gian rõ ràng, không trộn sidebar giáo viên/học sinh.
  TODO: bind vào $roles = auth()->user()->roles đã load; đổi route theo
  role đang chọn (lưu ở session hoặc query param).
--}}
@auth
    @if (auth()->user()->roles()->count() > 1)
        <div class="relative">
            <button type="button" class="text-sm px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600">
                Vai trò ▾
            </button>
            {{-- TODO: dropdown danh sách role, chuyển dashboard tương ứng --}}
        </div>
    @endif
@endauth
