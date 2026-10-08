@extends('layouts.admin')

@section('title', 'Người dùng')
@section('page-title', 'Người dùng')

@section('content')
    @php
        $tab = $tab ?? 'all';
        $tabs = $tabs ?? [];
        $users = $users ?? [];
        $total = $total ?? count($users);
        // Nhãn vai trò -> màu (chỉ là kiểu hiển thị; nhãn do UserService::presentUser() trả về, không đổi).
        $roleClass = function (string $label): string {
            $l = mb_strtolower($label);
            return match (true) {
                str_contains($l, 'admin') || str_contains($l, 'quản trị') || str_contains($l, 'editor') || str_contains($l, 'biên tập') => 'aux-role--admin',
                str_contains($l, 'giáo viên') => 'aux-role--teacher',
                str_contains($l, 'phụ huynh') => 'aux-role--parent',
                str_contains($l, 'học sinh') => 'aux-role--student',
                default => '',
            };
        };
        $statusOptions = collect($users)->pluck('status')->unique()->values();
    @endphp

    @if (session('status') === 'user-created')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã tạo tài khoản mới.'])
    @endif

    {{--
      SỬA 8/10 (khách: "cập nhật lại UI màn người dùng theo source mới, logic giữ nguyên") — dựng lại theo
      AdminUsers.jsx: dải tab vai trò có số đếm, thanh tìm + lọc trạng thái, bảng ô định danh (avatar + tên + email),
      nhãn vai trò, chấm trạng thái, phân trang 10 người/trang. Dữ liệu ($tabs, $users) và route Xem/Thêm/Hàng đợi
      duyệt giáo viên GIỮ NGUYÊN; tìm/lọc/chia trang chạy ngay trên các dòng đã có, không thêm truy vấn hay tham số.
    --}}
    @include('partials.admin-users-ui')

    <x-ws.page-header title="Người dùng" icon="users" subtitle="Quản lý người dùng, vai trò và trạng thái phê duyệt giáo viên (3.3).">
        <x-slot:actions>
            <a href="{{ route('admin.users.create') }}" class="inline-flex min-h-10 shrink-0 items-center justify-center gap-1.5 rounded-xl bg-white px-4 py-2 text-xs font-bold text-blue-700 shadow-sm transition-colors hover:bg-sky-50">+ Thêm người dùng</a>
            <a href="{{ route('admin.teacher-approvals.index') }}" class="inline-flex min-h-10 shrink-0 items-center justify-center gap-1.5 rounded-xl border border-white/35 bg-white/10 px-4 py-2 text-xs font-bold text-white backdrop-blur-sm transition-colors hover:bg-white/20">
                Hàng đợi duyệt giáo viên
            </a>
        </x-slot:actions>
    </x-ws.page-header>

    <section class="acx-panel" style="margin-top:16px" aria-label="Danh sách người dùng">
        <nav class="acx-seg" aria-label="Nhóm người dùng">
            @foreach ($tabs as $t)
                <a href="{{ $t['href'] }}" @if ($t['active'] ?? false) aria-current="page" @endif>{{ $t['label'] }}@isset($t['count'])<span>{{ $t['count'] }}</span>@endisset</a>
            @endforeach
        </nav>

        <div class="acx-toolbar">
            <label class="acx-search">
                <x-lucide name="search" />
                <input type="search" id="auxQuery" aria-label="Tìm người dùng" autocomplete="off" placeholder="Tìm tên hoặc email…">
                <button type="button" class="acx-clear" id="auxClear" aria-label="Xóa tìm kiếm"><x-lucide name="x" class="h-4 w-4" /></button>
            </label>
            <label class="acx-filter">Trạng thái
                <select class="acx-field" id="auxStatus" aria-label="Lọc theo trạng thái">
                    <option value="">Tất cả</option>
                    @foreach ($statusOptions as $o)
                        <option value="{{ $o }}">{{ $o }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="akx-sub" style="padding-top:0"><span><b id="auxCount">{{ $total }}</b> người dùng</span><span>Mới tạo hiển thị trước</span></div>

        <div class="acx-scroll">
            <table class="acx-table akx-table apx-up">
                <caption class="acx-sr">Danh sách người dùng</caption>
                <thead>
                    <tr>
                        <th scope="col">Người dùng</th>
                        <th scope="col">Vai trò</th>
                        <th scope="col">Trạng thái</th>
                        <th scope="col">Ngày tạo</th>
                        <th scope="col">Thao tác</th>
                    </tr>
                </thead>
                <tbody id="auxBody" data-pg="users" data-unit="người dùng">
                    @foreach ($users as $u)
                        <tr data-pg-item data-search="{{ $u['name'] }} {{ $u['email'] }} {{ implode(' ', $u['roles']) }}" data-status="{{ $u['status'] }}">
                            <td>
                                <div class="aux-who">
                                    <x-ws.avatar :name="$u['name']" size="md" />
                                    <div>
                                        <a href="{{ route('admin.users.show', $u['id']) }}" class="akx-title">{{ $u['name'] }}</a>
                                        <span class="aux-email">{{ $u['email'] }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="aux-roles">
                                    @foreach ($u['roles'] as $r)
                                        <span class="aux-role {{ $roleClass($r) }}">{{ $r }}</span>
                                    @endforeach
                                </div>
                            </td>
                            <td><span class="aux-status {{ ($u['tone'] ?? '') === 'warning' ? 'aux-status--warn' : (in_array($u['tone'] ?? '', ['danger', 'error'], true) ? 'aux-status--off' : '') }}">{{ $u['status'] }}</span></td>
                            <td><span class="aux-date">{{ $u['created'] }}</span></td>
                            <td>
                                <div class="akx-act"><div class="akx-act__links"><a href="{{ route('admin.users.show', $u['id']) }}" class="akx-lnk">Chi tiết</a></div></div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="akx-empty" id="auxEmpty" style="display:{{ count($users) === 0 ? 'block' : 'none' }}">
                <strong id="auxEmptyTitle">Không có người dùng nào.</strong>
                <span id="auxEmptyHint"></span>
            </div>
        </div>

        <div class="akx-pg" data-pg-nav="users" hidden></div>
        <p class="acx-foot" style="padding-top:12px">Bấm "Chi tiết" để xem hồ sơ, đổi vai trò, đặt lại mật khẩu và xem lịch sử thay đổi.</p>
    </section>
@endsection

@push('scripts')
    <script>
        /* Tìm + lọc trạng thái chạy trên các dòng đã có; phân trang 10 người/trang do khối [data-pg] trong partials.admin-content-ui lo.
           Dòng không khớp được gỡ khỏi bộ đếm trang bằng thuộc tính data-pg-item (đổi tên tạm), rồi dựng lại thanh chuyển trang. */
        document.addEventListener('DOMContentLoaded', function () {
            var body = document.getElementById('auxBody');
            if (!body) return;
            var rows = Array.prototype.slice.call(body.querySelectorAll('tr'));
            var q = document.getElementById('auxQuery'), clear = document.getElementById('auxClear');
            var st = document.getElementById('auxStatus');
            var count = document.getElementById('auxCount'), empty = document.getElementById('auxEmpty');
            var title = document.getElementById('auxEmptyTitle'), hint = document.getElementById('auxEmptyHint');
            var total = parseInt(count.textContent, 10) || rows.length;
            var allShown = rows.length === total;
            function norm(s) { return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/g, 'd'); }
            rows.forEach(function (tr) { tr._s = norm(tr.dataset.search); });
            function apply() {
                var term = norm(q.value).trim(), s = st.value, hits = 0;
                rows.forEach(function (tr) {
                    var ok = (!term || tr._s.indexOf(term) !== -1) && (!s || tr.dataset.status === s);
                    tr.dataset.match = ok ? '1' : '0';
                    if (ok) hits++;
                });
                // Đưa các dòng khớp lên đầu danh sách chia trang bằng cách bật/tắt thuộc tính data-pg-item.
                rows.forEach(function (tr) {
                    if (tr.dataset.match === '1') tr.setAttribute('data-pg-item', ''); else { tr.removeAttribute('data-pg-item'); tr.hidden = true; }
                });
                window.akxRepage && window.akxRepage('users');
                count.textContent = (term || s) ? hits : (allShown ? total : total);
                empty.style.display = hits === 0 ? 'block' : 'none';
                if (hits === 0) {
                    title.textContent = (term || s) ? 'Không tìm thấy người dùng phù hợp' : 'Không có người dùng nào.';
                    hint.textContent = (term || s) ? ' Thử đổi từ khóa hoặc bộ lọc.' : '';
                }
                clear.classList.toggle('is-on', q.value !== '');
            }
            q.addEventListener('input', apply);
            st.addEventListener('change', apply);
            clear.addEventListener('click', function () { q.value = ''; apply(); q.focus(); });
        });
    </script>
@endpush
