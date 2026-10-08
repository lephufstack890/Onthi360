@extends('layouts.admin')

@section('title', 'Khóa & Lớp')
@section('page-title', 'Khóa & Lớp')

@section('content')
    @php
        $tab = $tab ?? 'courses';
        $tabs = $tabs ?? [];
        $rows = $rows ?? [];
        $isCourses = $tab === 'courses';
        $kindLabel = $isCourses ? 'khóa học' : 'lớp học';

        // Nhãn trạng thái hiển thị (chỉ là chữ — dữ liệu và điều kiện lọc ở máy chủ không đổi).
        $statusText = fn (array $r) => match ($r['statusRaw'] ?? $r['status']) {
            'draft' => 'Bản nháp',
            'pending_review' => 'Chờ duyệt',
            'archived' => 'Lưu trữ',
            default => $r['status'],
        };
        $courseOptions = collect($rows)->pluck('course')->filter()->unique()->sort()->values();
    @endphp

    @if (in_array(session('status'), ['course-created', 'course-deleted', 'class-deleted'], true))
        @include('partials.toast-flash', ['type' => 'success', 'message' => session('status') === 'course-created' ? 'Đã tạo khóa học mới.' : session('statusMessage', 'Đã xóa.')])
    @endif
    {{-- SỬA 7/10 — việc xoá bị từ chối / lỗi đi theo $errors. --}}
    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    {{--
      SỬA 8/10 (khách: "cập nhật UI màn khóa và lớp từ source mới, logic giữ nguyên") — dựng lại theo
      AdminCourses.jsx: bộ chuyển Khóa học/Lớp học có số đếm, thanh tìm + lọc, bảng có ô định danh
      (ảnh nhỏ + tên + dòng phụ), phân trang. Route, nút Xoá (hộp xác nhận, ô "return"), nút Tạo khóa
      học và thứ tự dữ liệu GIỮ NGUYÊN; tìm/lọc/phân trang chạy ngay trên các dòng đã có (tối đa 50
      dòng mới nhất như trước), không thêm truy vấn hay tham số nào.
    --}}
    @include('partials.admin-courses-ui')

    <x-ws.page-header title="Khóa & Lớp" icon="graduation-cap" subtitle="Một khóa học có thể có nhiều lớp; lớp là nơi tổ chức lịch, học viên và tiến độ (8.1).">
        @if ($isCourses)
            <x-slot:actions>
                <a href="{{ route('admin.courses.create') }}" class="inline-flex min-h-10 shrink-0 items-center justify-center gap-1.5 rounded-xl bg-white px-4 py-2 text-xs font-bold text-blue-700 shadow-sm transition-colors hover:bg-sky-50">+ Tạo khóa học</a>
            </x-slot:actions>
        @endif
    </x-ws.page-header>

    <section class="acx-panel" style="margin-top:16px" aria-label="Quản lý khóa và lớp" id="acxList" data-kind="{{ $kindLabel }}">
        {{-- Bộ chuyển Khóa học / Lớp học (vẫn là liên kết tới đúng ?tab= như trước). --}}
        <nav class="acx-seg" aria-label="Loại quản lý">
            @foreach ($tabs as $t)
                <a href="{{ $t['href'] }}" @if ($t['active']) aria-current="page" @endif>{{ $t['label'] }}<span>{{ $t['count'] ?? 0 }}</span></a>
            @endforeach
        </nav>

        <div class="acx-toolbar">
            <label class="acx-search">
                <x-lucide name="search" />
                <input type="search" id="acxQuery" aria-label="Tìm khóa hoặc lớp" autocomplete="off"
                       placeholder="{{ $isCourses ? 'Tìm tên khóa học…' : 'Tìm tên, mã lớp hoặc giáo viên…' }}">
                <button type="button" class="acx-clear" id="acxClear" aria-label="Xóa tìm kiếm"><x-lucide name="x" class="h-4 w-4" /></button>
            </label>
            @unless ($isCourses)
                <label class="acx-filter">Khóa
                    <select class="acx-field" id="acxCourse" aria-label="Lọc theo khóa học">
                        <option value="">Tất cả khóa</option>
                        @foreach ($courseOptions as $co)
                            <option value="{{ $co }}">{{ $co }}</option>
                        @endforeach
                    </select>
                </label>
            @endunless
            <label class="acx-filter">Trạng thái
                <select class="acx-field" id="acxStatus" aria-label="Lọc theo trạng thái">
                    <option value="">Tất cả</option>
                    @foreach (collect($rows)->map($statusText)->unique()->values() as $st)
                        <option value="{{ $st }}">{{ $st }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="acx-scroll">
            <table class="acx-table">
                <caption class="acx-sr">Danh sách {{ $kindLabel }}</caption>
                <thead>
                    <tr>
                        <th scope="col">{{ $isCourses ? 'Khóa học' : 'Lớp học' }}</th>
                        <th scope="col">{{ $isCourses ? 'Triển khai' : 'Giáo viên & lịch học' }}</th>
                        <th scope="col">Trạng thái</th>
                        <th scope="col">Thao tác</th>
                    </tr>
                </thead>
                <tbody id="acxBody">
                    @foreach ($rows as $r)
                        @php
                            $title = $r['title'] ?? $r['name'];
                            $st = $statusText($r);
                            $badge = ($r['tone'] ?? 'neutral') === 'success' ? 'acx-badge--ok' : (($r['statusRaw'] ?? '') === 'archived' ? 'acx-badge--off' : (($r['statusRaw'] ?? '') === 'pending_review' ? 'acx-badge--warn' : ''));
                            $detailHref = $isCourses ? route('admin.courses.show', $r['id']) : route('admin.classes.edit', $r['id']);
                            $searchText = implode(' ', array_filter([$title, $r['code'] ?? null, $r['course'] ?? null, $r['teacher'] ?? null, $r['sub'] ?? null]));
                        @endphp
                        <tr data-row data-search="{{ $searchText }}" data-status="{{ $st }}" data-course="{{ $r['course'] ?? '' }}">
                            <th scope="row">
                                <div class="acx-ident">
                                    @if (! empty($r['thumb']))
                                        <img class="acx-thumb" src="{{ $r['thumb'] }}" alt="" loading="lazy">
                                    @else
                                        <span class="acx-thumb acx-thumb--blank"><x-lucide :name="$isCourses ? 'book-open' : 'graduation-cap'" /></span>
                                    @endif
                                    <div>
                                        <a href="{{ $detailHref }}" class="acx-name">{{ $title }}</a>
                                        @if ($isCourses)
                                            @if (! empty($r['sub']))<small>{{ $r['sub'] }}</small>@endif
                                        @else
                                            <small>{{ $r['code'] ?? '' }} · {{ $r['students'] ?? 0 }} học sinh</small>
                                            @if (! empty($r['course']))<small>{{ $r['course'] }}</small>@endif
                                        @endif
                                    </div>
                                </div>
                            </th>
                            <td>
                                @if ($isCourses)
                                    <strong>{{ $r['classes'] ?? 0 }} lớp</strong>
                                    <small>đang triển khai</small>
                                @else
                                    @if (! empty($r['teacher']))
                                        <strong>{{ $r['teacher'] }}</strong>
                                    @else
                                        <strong class="acx-missing">Chưa phân công</strong>
                                    @endif
                                    <small class="{{ empty($r['schedule']) ? 'acx-missing' : '' }}">{{ $r['schedule'] ?: 'Chưa xếp lịch' }}</small>
                                @endif
                            </td>
                            <td><span class="acx-badge {{ $badge }}">{{ $st }}</span></td>
                            <td>
                                <div class="acx-actions">
                                    <a href="{{ $detailHref }}" class="acx-link">{{ $isCourses ? 'Chi tiết' : 'Sửa' }}</a>
                                    {{-- SỬA 7/10 (khách: "làm thêm tính năng xoá, xoá khoá/lớp là xoá hết dữ liệu liên quan") —
                                         xoá vĩnh viễn kèm toàn bộ dữ liệu con. Nội dung cảnh báo (có tên khoá/lớp) đi qua
                                         data-confirm để khỏi vướng dấu nháy khi nhét vào onsubmit. --}}
                                    @if (! empty($r['deleteHref']))
                                        <form method="POST" action="{{ $r['deleteHref'] }}"
                                              data-confirm="{{ $r['deleteLabel'] }}" onsubmit="return confirm(this.dataset.confirm);">
                                            @csrf
                                            @method('DELETE')
                                            @if (! $isCourses)
                                                <input type="hidden" name="return" value="index">
                                            @endif
                                            <button type="submit" class="acx-link acx-link--del">Xóa</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="acx-empty {{ count($rows) === 0 ? 'is-on' : '' }}" id="acxEmpty">
                <strong>{{ count($rows) === 0 ? 'Chưa có dữ liệu.' : 'Chưa có '.$kindLabel.' phù hợp' }}</strong>
                <span id="acxEmptyHint">{{ count($rows) === 0 ? '' : 'Thử đổi từ khóa hoặc bộ lọc.' }}</span>
            </div>
        </div>

        <nav class="acx-pager" aria-label="Phân trang khóa và lớp" id="acxPager" @if (count($rows) === 0) hidden @endif>
            <div>
                <span id="acxRange"></span>
                <label>Số dòng
                    <select class="acx-field" id="acxSize" aria-label="Số dòng mỗi trang">
                        <option>5</option><option selected>10</option><option>20</option><option>50</option>
                    </select>
                </label>
            </div>
            <div>
                <button type="button" id="acxPrev" aria-label="Trang trước"><x-lucide name="chevron-left" /></button>
                <span id="acxPage"></span>
                <button type="button" id="acxNext" aria-label="Trang sau"><x-lucide name="chevron-right" /></button>
            </div>
        </nav>
        <p class="acx-foot">Một khóa có thể có nhiều lớp. Xóa khóa hoặc lớp là xóa vĩnh viễn cùng toàn bộ dữ liệu liên quan.</p>
    </section>
@endsection

@push('scripts')
    <script>
        (function () {
            var body = document.getElementById('acxBody');
            if (!body) return;
            var rows = Array.prototype.slice.call(body.querySelectorAll('tr[data-row]'));
            var q = document.getElementById('acxQuery'), clear = document.getElementById('acxClear');
            var statusSel = document.getElementById('acxStatus'), courseSel = document.getElementById('acxCourse');
            var sizeSel = document.getElementById('acxSize'), prev = document.getElementById('acxPrev'), next = document.getElementById('acxNext');
            var range = document.getElementById('acxRange'), pageEl = document.getElementById('acxPage');
            var empty = document.getElementById('acxEmpty'), hint = document.getElementById('acxEmptyHint');
            var kind = document.getElementById('acxList').dataset.kind;
            var page = 1;

            // Bỏ dấu tiếng Việt để gõ "toan" vẫn ra "Toán".
            function norm(s) {
                return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/g, 'd');
            }
            rows.forEach(function (tr) { tr._s = norm(tr.dataset.search); });

            function render() {
                var term = norm(q.value).trim();
                var st = statusSel.value, co = courseSel ? courseSel.value : '';
                var hit = rows.filter(function (tr) {
                    return (!term || tr._s.indexOf(term) !== -1)
                        && (!st || tr.dataset.status === st)
                        && (!co || tr.dataset.course === co);
                });
                var size = parseInt(sizeSel.value, 10) || 10;
                var pages = Math.max(1, Math.ceil(hit.length / size));
                page = Math.min(Math.max(1, page), pages);
                var from = (page - 1) * size;
                rows.forEach(function (tr) { tr.hidden = true; });
                hit.slice(from, from + size).forEach(function (tr) { tr.hidden = false; });

                range.textContent = (hit.length ? (from + 1) + '–' + Math.min(from + size, hit.length) + ' / ' + hit.length : '0') + ' ' + kind;
                pageEl.textContent = 'Trang ' + page + ' / ' + pages;
                prev.disabled = page === 1;
                next.disabled = page === pages;
                if (rows.length) {
                    empty.classList.toggle('is-on', hit.length === 0);
                    hint.textContent = hit.length === 0 ? 'Thử đổi từ khóa hoặc bộ lọc.' : '';
                    if (hit.length === 0) empty.querySelector('strong').textContent = 'Chưa có ' + kind + ' phù hợp';
                }
                clear.classList.toggle('is-on', q.value !== '');
            }

            q.addEventListener('input', function () { page = 1; render(); });
            clear.addEventListener('click', function () { q.value = ''; page = 1; render(); q.focus(); });
            statusSel.addEventListener('change', function () { page = 1; render(); });
            if (courseSel) courseSel.addEventListener('change', function () { page = 1; render(); });
            sizeSel.addEventListener('change', function () { page = 1; render(); });
            prev.addEventListener('click', function () { page--; render(); });
            next.addEventListener('click', function () { page++; render(); });
            render();
        })();
    </script>
@endpush
