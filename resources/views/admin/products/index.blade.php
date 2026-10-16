
@extends('layouts.admin')

@section('title', 'Tài liệu')
@section('page-title', 'Tài liệu')

@section('content')
    @php
        $tabs = $tabs ?? [];
        $products = $products ?? [];
        $typeIcons = ['Sách' => 'book-open', 'Chuyên đề' => 'layers', 'Bộ đề' => 'file-text', 'Khóa học' => 'graduation-cap'];
        // Đúng 3 nhóm tab như bản mẫu: Sách / Chuyên đề / Bộ đề (lọc theo "Loại" sẵn có của từng dòng).
        $groupLabels = ['Sách', 'Chuyên đề', 'Bộ đề'];
        $groupCounts = [];
        foreach ($groupLabels as $g) { $groupCounts[$g] = collect($products)->where('type', $g)->count(); }
        // Mở sẵn nhóm đầu tiên có dữ liệu (không có thì Sách).
        $firstGroup = collect($groupLabels)->first(fn ($g) => $groupCounts[$g] > 0) ?? $groupLabels[0];
        // SỬA 16/10 — sau khi bấm ↑/↓ quay về đúng tab đang đứng (?tab=Sách|Chuyên đề|Bộ đề).
        if (in_array(request('tab'), $groupLabels, true)) { $firstGroup = request('tab'); }
        // Vị trí đầu/cuối của từng tài liệu TRONG LOẠI của nó -> khoá nút ↑ ở dòng đầu, ↓ ở dòng cuối.
        $typeFirstId = []; $typeLastId = [];
        foreach ($products as $pp) { $typeFirstId[$pp['type']] ??= $pp['id']; $typeLastId[$pp['type']] = $pp['id']; }
        $visOptions = collect($products)->pluck('visibility')->unique()->values();
        // Liên kết sang tab "Quyền đã cấp" (route cũ) — giữ lối vào sau khi bỏ dải tab 2 mục.
        $accessTab = collect($tabs)->first(fn ($t) => ! ($t['active'] ?? false));
    @endphp

    @if (session('status') === 'product-moved')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã đổi thứ tự tài liệu — trang Tài liệu public hiển thị theo đúng thứ tự này.'])
    @endif
    @if (in_array(session('status'), ['product-created', 'product-deleted'], true))
        @include('partials.toast-flash', ['type' => 'success', 'message' => session('status') === 'product-created' ? 'Đã tạo tài liệu mới.' : 'Đã xóa tài liệu (xóa mềm, đã ghi lý do).'])
    @endif

    {{--
      SỬA 8/10 (khách: "trang danh sách có 3 tab Sách / Chuyên đề / Bộ đề, chỉnh giống UI, logic giữ nguyên") —
      dựng lại theo AdminContentWorkspace.jsx: 3 tab nhóm có số đếm, ô tìm, "n kết quả", bảng tiêu đề in hoa,
      trạng thái rỗng + phân trang. Dữ liệu ($products — tối đa 50 mục mới nhất) và route Xem/Sửa/Tạo giữ nguyên;
      tab/tìm/lọc/phân trang chạy ngay trên các dòng đã có theo "Loại" sẵn có, không thêm truy vấn hay tham số nào.
      Cột "Độ khó" của bản mẫu không có dữ liệu ở đây nên thay bằng "Giá"; nút "Lưu trữ" thay bằng lối vào
      "Quyền đã cấp" (route cũ của tab thứ hai).
    --}}
    @include('partials.admin-products-ui')
    <style>
        /* SỬA 16/10 — dòng vừa đổi chỗ được tô sáng thoáng qua. */
        #apxBody tr.apx-row-flash > * { background: #fff7d6 !important; transition: background .3s; }
        #apxBody .acx-actions { align-items: center; }
    </style>

    <x-ws.page-header title="Quản lý tài liệu" icon="book-open" eyebrow="Quản trị & biên soạn" subtitle="Sách, Chuyên đề và Bộ đề · nội dung được tổ chức theo chương hoặc phần.">
        <x-slot:actions>
            <a href="{{ route('admin.products.create') }}" class="inline-flex min-h-10 shrink-0 items-center justify-center gap-1.5 rounded-xl bg-white px-4 py-2 text-xs font-bold text-blue-700 shadow-sm transition-colors hover:bg-sky-50">+ Thêm tài liệu</a>
        </x-slot:actions>
    </x-ws.page-header>

    <nav class="apx-tabs" aria-label="Nhóm tài liệu" id="apxTabs">
        @foreach ($groupLabels as $g)
            <button type="button" class="apx-tab {{ $g === $firstGroup ? 'is-on' : '' }}" data-group="{{ $g }}">{{ $g }}<span>{{ $groupCounts[$g] }}</span></button>
        @endforeach
    </nav>

    <section class="acx-panel" style="margin-top:16px" aria-label="Danh sách tài liệu" id="apxList">
        <div class="acx-toolbar">
            <label class="acx-search">
                <x-lucide name="search" />
                <input type="search" id="apxQuery" aria-label="Tìm tài liệu" autocomplete="off" placeholder="Tìm tên tài liệu…">
                <button type="button" class="acx-clear" id="apxClear" aria-label="Xóa tìm kiếm"><x-lucide name="x" class="h-4 w-4" /></button>
            </label>
            @if ($accessTab)
                <a href="{{ $accessTab['href'] }}" class="acx-btn"><x-lucide name="ticket" /> {{ $accessTab['label'] }}@isset($accessTab['count']) ({{ $accessTab['count'] }})@endisset</a>
            @endif
        </div>

        <div class="apx-sub">
            <span><b id="apxCount">0</b> kết quả</span>
            <span class="apx-sub__r">
                <label class="acx-filter">Hiển thị
                    <select class="acx-field" id="apxVis" aria-label="Lọc theo hiển thị">
                        <option value="">Tất cả</option>
                        @foreach ($visOptions as $o)
                            <option value="{{ $o }}">{{ $o }}</option>
                        @endforeach
                    </select>
                </label>
            </span>
        </div>

        <div class="acx-scroll">
            <table class="acx-table apx-list-table apx-up">
                <caption class="acx-sr">Danh sách tài liệu</caption>
                <thead>
                    <tr>
                        <th scope="col">Tên tài liệu</th>
                        <th scope="col">Loại</th>
                        <th scope="col">Giá</th>
                        <th scope="col">Hiển thị</th>
                        <th scope="col">Thao tác</th>
                    </tr>
                </thead>
                <tbody id="apxBody">
                    @foreach ($products as $p)
                        <tr data-row data-id="{{ $p['id'] }}" data-search="{{ $p['title'] }} {{ $p['type'] }}" data-type="{{ $p['type'] }}" data-vis="{{ $p['visibility'] }}">
                            <th scope="row">
                                <div class="acx-ident">
                                    <span class="apx-tile"><x-lucide :name="$typeIcons[$p['type']] ?? 'file-text'" /></span>
                                    <div>
                                        <a href="{{ route('admin.products.show', $p['id']) }}" class="acx-name">{{ $p['title'] }}</a>
                                        <small class="apx-cell-sub">{{ $p['type'] }} · {{ $p['price'] }}</small>
                                    </div>
                                </div>
                            </th>
                            <td>{{ $p['type'] }}</td>
                            <td><span class="apx-money">{{ $p['price'] }}</span></td>
                            <td><span class="acx-badge {{ ($p['tone'] ?? '') === 'info' ? 'acx-badge--info' : '' }}">{{ $p['visibility'] }}</span></td>
                            <td>
                                <div class="acx-actions">
                                    {{-- SỬA 16/10 (khách: "2 nút mũi tên lên xuống gần nút Xem/Sửa — đẩy lên trước / đẩy xuống dưới, cả 3 tab") —
                                         đổi chỗ với tài liệu liền trên/dưới CÙNG LOẠI; trang Tài liệu public hiện đúng thứ tự này. --}}
                                    <div class="apx-move" role="group" aria-label="Đổi thứ tự">
                                        <form action="{{ route('admin.products.move', $p['id']) }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="direction" value="up">
                                            <button type="submit" class="apx-move__btn" title="Đẩy lên trước" aria-label="Đẩy {{ $p['title'] }} lên trước" {{ ($typeFirstId[$p['type']] ?? null) === $p['id'] ? 'disabled' : '' }}>
                                                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"/></svg>
                                            </button>
                                        </form>
                                        <form action="{{ route('admin.products.move', $p['id']) }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="direction" value="down">
                                            <button type="submit" class="apx-move__btn" title="Đẩy xuống dưới" aria-label="Đẩy {{ $p['title'] }} xuống dưới" {{ ($typeLastId[$p['type']] ?? null) === $p['id'] ? 'disabled' : '' }}>
                                                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                    <a href="{{ route('admin.products.show', $p['id']) }}" class="acx-link">Xem</a>
                                    <a href="{{ route('admin.products.edit', $p['id']) }}" class="acx-link">Sửa</a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="apx-bigempty is-on" id="apxEmpty">
                <x-lucide name="search" />
                <strong id="apxEmptyTitle">Chưa có nội dung trong nhóm này</strong>
                <p id="apxEmptyHint">Thêm nội dung đầu tiên để bắt đầu.</p>
                <a href="{{ route('admin.products.create') }}" class="acx-btn acx-btn--primary" id="apxEmptyBtn"><x-lucide name="plus" /> Thêm tài liệu</a>
            </div>
        </div>

        <nav class="acx-pager" aria-label="Phân trang tài liệu" id="apxPager">
            <span id="apxRange">0–0 / 0 kết quả</span>
            <div>
                <button type="button" id="apxPrev" aria-label="Trang trước"><x-lucide name="chevron-left" /></button>
                <span id="apxPage">1 / 1</span>
                <button type="button" id="apxNext" aria-label="Trang sau"><x-lucide name="chevron-right" /></button>
            </div>
        </nav>
    </section>
@endsection

@push('scripts')
    <script>
        (function () {
            var body = document.getElementById('apxBody');
            if (!body) return;
            var SIZE = 10, page = 1;
            var rows = Array.prototype.slice.call(body.querySelectorAll('tr[data-row]'));
            var tabs = Array.prototype.slice.call(document.querySelectorAll('#apxTabs .apx-tab'));
            var q = document.getElementById('apxQuery'), clear = document.getElementById('apxClear');
            var visSel = document.getElementById('apxVis');
            var count = document.getElementById('apxCount'), range = document.getElementById('apxRange');
            var pageEl = document.getElementById('apxPage'), prev = document.getElementById('apxPrev'), next = document.getElementById('apxNext');
            var empty = document.getElementById('apxEmpty'), title = document.getElementById('apxEmptyTitle');
            var hint = document.getElementById('apxEmptyHint'), emptyBtn = document.getElementById('apxEmptyBtn');
            var table = body.closest('table');

            // Bỏ dấu tiếng Việt để gõ "toan" vẫn ra "Toán".
            function norm(s) {
                return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/g, 'd');
            }
            rows.forEach(function (tr) { tr._s = norm(tr.dataset.search); });

            function group() {
                var on = tabs.filter(function (t) { return t.classList.contains('is-on'); })[0];
                return on ? on.dataset.group : '';
            }

            function render() {
                var term = norm(q.value).trim(), g = group(), v = visSel.value;
                var hit = rows.filter(function (tr) {
                    return (!g || tr.dataset.type === g)
                        && (!term || tr._s.indexOf(term) !== -1)
                        && (!v || tr.dataset.vis === v);
                });
                var pages = Math.max(1, Math.ceil(hit.length / SIZE));
                page = Math.min(Math.max(1, page), pages);
                var from = (page - 1) * SIZE;
                rows.forEach(function (tr) { tr.hidden = true; });
                hit.slice(from, from + SIZE).forEach(function (tr) { tr.hidden = false; });

                count.textContent = hit.length;
                range.textContent = (hit.length ? (from + 1) + '–' + Math.min(from + SIZE, hit.length) : '0–0') + ' / ' + hit.length + ' kết quả';
                pageEl.textContent = page + ' / ' + pages;
                prev.disabled = page === 1;
                next.disabled = page === pages;
                table.style.display = hit.length ? '' : 'none';
                empty.classList.toggle('is-on', hit.length === 0);
                if (hit.length === 0) {
                    var filtered = term || v;
                    title.textContent = filtered ? 'Không tìm thấy tài liệu phù hợp' : 'Chưa có nội dung trong nhóm này';
                    hint.textContent = filtered ? 'Thử đổi từ khóa hoặc bộ lọc.' : 'Thêm nội dung đầu tiên để bắt đầu.';
                    emptyBtn.style.display = filtered ? 'none' : '';
                }
                clear.classList.toggle('is-on', q.value !== '');
            }

            tabs.forEach(function (t) {
                t.addEventListener('click', function () {
                    tabs.forEach(function (x) { x.classList.toggle('is-on', x === t); });
                    page = 1; render();
                });
            });
            q.addEventListener('input', function () { page = 1; render(); });
            clear.addEventListener('click', function () { q.value = ''; page = 1; render(); q.focus(); });
            visSel.addEventListener('change', function () { page = 1; render(); });
            prev.addEventListener('click', function () { page--; render(); });
            next.addEventListener('click', function () { page++; render(); });

            // SỬA 16/10 — vừa bấm ↑/↓ (?focus=id): mở đúng trang phân trang chứa tài liệu đó và tô sáng dòng.
            var focusId = new URLSearchParams(location.search).get('focus');
            if (focusId) {
                var g0 = group();
                var inGroup = rows.filter(function (tr) { return !g0 || tr.dataset.type === g0; });
                var at = inGroup.findIndex(function (tr) { return tr.dataset.id === focusId; });
                if (at >= 0) { page = Math.floor(at / SIZE) + 1; }
            }
            render();
            if (focusId) {
                var fr = body.querySelector('tr[data-id="' + focusId.replace(/[^0-9]/g, '') + '"]');
                if (fr) {
                    fr.classList.add('apx-row-flash');
                    setTimeout(function () { fr.classList.remove('apx-row-flash'); }, 1600);
                }
                if (history.replaceState) { history.replaceState(null, '', location.pathname + (location.hash || '')); }
            }
        })();
    </script>
@endpush
