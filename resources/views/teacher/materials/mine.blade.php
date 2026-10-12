@extends('layouts.teacher')

@section('title', 'Học liệu')
@section('page-title', 'Học liệu')

@section('content')
    {{-- SỬA 12/10 — dựng lại theo TeacherResourcesPanel của source mới: tiêu đề "Học liệu", ô tìm, lưới thẻ (bìa, tên, quyền đọc),
         nút "Đọc tài liệu" và "Giao tài liệu" (giao quyền đọc có thời hạn cho học sinh của lớp). GIỮ NGUYÊN logic bản cũ: 3 tab
         Sách/Chuyên đề/Bộ đề, tìm kiếm, nút đọc liền mạch (readHref), ngăn "Tài nguyên" (tải tài nguyên + PDF hướng dẫn, xem đề bài
         từng bài tập). Thêm phân trang 10 thẻ/trang. Giao tài liệu gửi về materials.assign (MaterialAssignmentService::assignMany). --}}
    @php
        $tabs = $tabs ?? [];
        $products = $products ?? [];
        $canAssign = $canAssign ?? false;
        $assignClasses = $assignClasses ?? [];
        $assignRoster = $assignRoster ?? [];
        $accessDays = $accessDays ?? [7, 30, 90, 365];
        $titles = array_map(fn ($p) => mb_strtolower($p['title']), $products);
        $payload = [
            'titles' => $titles,
            'products' => array_map(fn ($p) => ['id' => $p['id'], 'title' => $p['title']], $products),
            'classes' => $assignClasses,
            'roster' => (object) $assignRoster,
            'canAssign' => $canAssign,
            'days' => $accessDays,
            'url' => route('materials.assign'),
            'searchUrl' => route('materials.assign.students'),
        ];
    @endphp

    <div class="tw-dashboard" data-section="TEACHER-MATERIALS" x-data="twMats(@js($payload))">
        @include('teacher._hero')

        <x-ws.tabs :tabs="$tabs" />

        {{-- ═════ Form giao tài liệu ═════ --}}
        <section class="tw-card" aria-label="Giao tài liệu" x-show="assignOpen" x-cloak>
            <div class="tw-heading"><div><h2><x-lucide name="send" />Giao tài liệu cho học sinh</h2><p>Mỗi học sinh có một lượt giao riêng; được cấp quyền đọc có thời hạn.</p></div></div>
            <form class="tw-form" @submit.prevent="submit()" novalidate>
                <div class="tw-fields">
                    <label>Tài liệu
                        <input type="text" :value="form.title" disabled>
                    </label>
                    <label>Lớp nhận
                        <select aria-label="Lớp nhận" x-model="form.classId" @change="form.recipient = 'all'; form.picked = []" x-init="$nextTick(() => { $el.value = form.classId })">
                            <template x-for="c in classes" :key="c.id"><option :value="String(c.id)" x-text="c.name"></option></template>
                            <option value="other">Học sinh khác (tìm theo tên / email)</option>
                        </select>
                    </label>
                    <label x-show="form.classId !== 'other'">Học sinh nhận
                        <select aria-label="Học sinh nhận" x-model="form.recipient">
                            <option value="all" x-text="'Cả lớp · ' + classStudents.length + ' học sinh'"></option>
                            <template x-for="s in classStudents" :key="s.id"><option :value="String(s.id)" x-text="s.name + ' · ' + s.account"></option></template>
                        </select>
                    </label>
                    <label>Hạn đọc (giờ Việt Nam)
                        <input type="datetime-local" x-model="form.deadline">
                    </label>
                    <label>Quyền đọc
                        <select aria-label="Quyền đọc" x-model="form.days" x-init="$nextTick(() => { $el.value = form.days })">
                            <template x-for="d in days" :key="d"><option :value="String(d)" x-text="d + ' ngày'"></option></template>
                        </select>
                    </label>
                </div>
                <div x-show="form.classId === 'other'" x-cloak>
                    <label>Tìm học sinh
                        <input type="search" x-model="form.studentQuery" @input.debounce.300ms="findStudents()" placeholder="Gõ tên, email hoặc số điện thoại" autocomplete="off">
                    </label>
                    <div class="tw-assign-pick" style="margin-top:6px" x-show="found.length" x-cloak>
                        <template x-for="s in found" :key="s.id">
                            <button type="button" :class="{ on: isPicked(s.id) }" @click="togglePick(s)" x-text="(isPicked(s.id) ? '✓ ' : '') + s.name + ' · ' + (s.email || s.phone || '')"></button>
                        </template>
                    </div>
                    <div class="tw-actions" x-show="form.picked.length" x-cloak>
                        <template x-for="s in form.picked" :key="s.id"><span class="tw-tag" x-text="s.name"></span></template>
                    </div>
                </div>
                <label>Lời nhắn
                    <textarea rows="3" maxlength="1000" x-model="form.note"></textarea>
                </label>
                <p class="tw-error" role="alert" x-show="err" x-cloak x-text="err"></p>
                <p class="tw-success" role="status" x-show="msg" x-cloak x-text="msg"></p>
                <div class="tw-actions">
                    <button type="submit" class="tw-button" :disabled="busy" x-text="busy ? 'Đang lưu…' : 'Lưu giao tài liệu'"></button>
                    <button type="button" class="tw-secondary" @click="assignOpen = false; err = ''; msg = ''">Hủy</button>
                </div>
            </form>
        </section>

        <div x-show="!assignOpen">
            @if (empty($products))
                <section class="tw-card">
                    <p class="tw-empty">Chưa có tài liệu nào trong mục này. Mua hoặc nhập mã kích hoạt ở trang Tài liệu để bắt đầu.</p>
                    <div style="text-align:center"><a class="tw-button" href="{{ route('materials.index') }}">Khám phá tài liệu <x-lucide name="arrow-right" /></a></div>
                </section>
            @else
                <section class="tw-card">
                    <div class="tw-heading">
                        <div><h2><x-lucide name="book-open" />Học liệu</h2><p>Đọc tài liệu có quyền sử dụng hoặc giao quyền đọc có thời hạn cho học sinh.</p></div>
                        <a class="tw-button" href="{{ route('materials.index') }}">Mở kho tài liệu</a>
                    </div>
                    <label class="tw-inline-filter">Tìm học liệu
                        <input type="search" x-model.debounce.150ms="q" @input="page = 1" placeholder="Tên tài liệu" aria-label="Tìm học liệu">
                    </label>
                    <p class="tw-muted" role="status"><span x-text="total"></span> / {{ count($products) }} tài liệu có quyền đọc</p>

                    <div class="sw-mats" style="margin-top:8px">
                        @foreach ($products as $p)
                            <article class="sw-mat" style="flex-wrap:wrap" aria-label="{{ $p['title'] }}" x-show="show({{ $loop->index }})" x-data="{ open: false }">
                                @if ($p['coverPath'])
                                    <img src="{{ \App\Support\ProductCover::url($p['coverPath']) }}" alt="Bìa {{ $p['title'] }}" loading="lazy">
                                @else
                                    <span style="display:grid;place-items:center;width:64px;height:96px;flex-shrink:0;border-radius:8px;background:#eef6fa;color:#23869b"><x-lucide name="book-open" class="h-6 w-6" /></span>
                                @endif
                                <div class="sw-mat-b">
                                    <span class="st ok">Có quyền đọc</span>
                                    <h3>{{ $p['title'] }}</h3>
                                    <p>@if ($p['lessonCount'] > 0){{ $p['lessonCount'] }} tệp nội dung · @endif{{ $p['access']['remainingLabel'] ?? 'Đã sở hữu' }}</p>
                                    @if (count($p['resources']) > 0 || count($p['exercises']) > 0)
                                        <p>{{ count($p['resources']) > 0 ? count($p['resources']).' tài nguyên' : '' }}{{ count($p['resources']) > 0 && count($p['exercises']) > 0 ? ' · ' : '' }}{{ count($p['exercises']) > 0 ? count($p['exercises']).' bài tập' : '' }}</p>
                                    @endif
                                    <div class="row">
                                        {{-- SỬA 29/9 — giáo viên đọc liền mạch cả sản phẩm như học sinh (cùng ProductReadService). --}}
                                        @if ($p['readHref'])
                                            <a href="{{ $p['readHref'] }}" class="student-class-enter"><x-lucide name="book-open" class="h-3.5 w-3.5" />Đọc tài liệu</a>
                                        @else
                                            <span class="sw-note" title="Sản phẩm chưa có bài đọc nào đính kèm file PDF">Chưa có nội dung đọc</span>
                                        @endif
                                        @if ($canAssign)
                                            <button type="button" class="tw-button" @click="openAssign({{ $p['id'] }})">Giao tài liệu</button>
                                        @endif
                                        @if (count($p['resources']) > 0 || count($p['exercises']) > 0)
                                            <button type="button" class="sw-act" @click="open = ! open" :aria-expanded="open ? 'true' : 'false'">
                                                <span x-text="open ? 'Thu gọn' : 'Tài nguyên'"></span>
                                                <svg class="sw-chev" :class="{ up: open }" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                                            </button>
                                        @endif
                                    </div>
                                </div>

                                @if (count($p['resources']) > 0 || count($p['exercises']) > 0)
                                    <div x-show="open" x-cloak style="width:100%;border-radius:12px;border-top:1px solid #e9f1f5;background:#f8fbfc;padding:12px">
                                        @if (count($p['resources']) > 0)
                                            <p class="tw-muted" style="font-weight:700;text-transform:uppercase;letter-spacing:.04em">Tài nguyên đính kèm</p>
                                            <div style="display:grid;gap:6px;margin:6px 0 10px">
                                                @foreach ($p['resources'] as $res)
                                                    <a href="{{ route('access.resource', ['product' => $p['id'], 'kind' => $res['kind']]) }}" target="_blank" rel="noopener"
                                                       style="display:flex;align-items:center;gap:8px;padding:8px 10px;border:1px solid #ddeaf0;border-radius:10px;background:#fff;color:#466278;font-size:12px;font-weight:500">
                                                        <span>{{ $res['icon'] }}</span>{{ $res['label'] }}
                                                    </a>
                                                @endforeach
                                            </div>
                                        @endif
                                        @if (count($p['exercises']) > 0)
                                            <p class="tw-muted" style="font-weight:700;text-transform:uppercase;letter-spacing:.04em">Bài tập</p>
                                            <div style="display:grid;gap:6px;margin-top:6px">
                                                @foreach ($p['exercises'] as $ex)
                                                    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px 10px;border:1px solid #ddeaf0;border-radius:10px;background:#fff">
                                                        <div style="min-width:0">
                                                            <p style="margin:0;color:#123b68;font-size:12px;font-weight:600;overflow-wrap:anywhere">{{ $ex['title'] }}</p>
                                                            <p class="tw-muted" style="margin:2px 0 0">{{ $ex['points'] }} điểm · {{ $ex['summary'] }}</p>
                                                        </div>
                                                        <a href="{{ route('access.resource.exerciseAttachment', [$p['id'], $ex['id'], 'statement']) }}" target="_blank" rel="noopener" style="flex-shrink:0;color:#176b50;font-size:11px;font-weight:700">Xem đề bài ↗</a>
                                                    </div>
                                                @endforeach
                                            </div>
                                            <p class="tw-muted" style="margin-top:8px">Học sinh làm bài trực tiếp (kiểu thi online) ở trang "Tài liệu của tôi" của các em.</p>
                                        @endif
                                    </div>
                                @endif
                            </article>
                        @endforeach
                    </div>

                    <p class="tw-empty" x-show="total === 0" x-cloak>Không tìm thấy tài liệu nào khớp từ khoá tìm kiếm.</p>
                    @include('student._pager')
                </section>
            @endif
        </div>
    </div>

    <script>
        function twMats(c) {
            return window.swPager(c.titles.length, 10, {
                q: '', titles: c.titles, products: c.products, classes: c.classes, roster: c.roster, days: c.days,
                assignOpen: false, busy: false, err: '', msg: '', found: [],
                form: { productId: 0, title: '', classId: c.classes.length ? String(c.classes[0].id) : 'other', recipient: 'all', deadline: '', days: String(c.days[1] || c.days[0] || 30), note: '', studentQuery: '', picked: [] },
                get idx() { var q = this.q.trim().toLowerCase(), out = []; this.titles.forEach(function (t, i) { if (!q || t.indexOf(q) !== -1) { out.push(i); } }); return out; },
                get total() { return this.idx.length; },
                show: function (i) { var p = this.idx.indexOf(i); return p !== -1 && p >= (this.page - 1) * this.size && p < this.page * this.size; },
                get classStudents() { return this.roster[this.form.classId] || []; },
                openAssign: function (id) {
                    var p = this.products.find(function (x) { return x.id === id; });
                    this.form.productId = id; this.form.title = p ? p.title : ''; this.err = ''; this.msg = ''; this.assignOpen = true;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },
                recipientIds: function () {
                    if (this.form.classId === 'other') { return this.form.picked.map(function (s) { return s.id; }); }
                    if (this.form.recipient === 'all') { return this.classStudents.map(function (s) { return s.id; }); }
                    return [parseInt(this.form.recipient, 10)];
                },
                isPicked: function (id) { return this.form.picked.some(function (s) { return s.id === id; }); },
                togglePick: function (s) { if (this.isPicked(s.id)) { this.form.picked = this.form.picked.filter(function (x) { return x.id !== s.id; }); } else { this.form.picked.push(s); } },
                findStudents: function () {
                    var self = this, q = this.form.studentQuery.trim();
                    if (q.length < 2) { this.found = []; return; }
                    fetch(c.searchUrl + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                        .then(function (res) { return res.ok ? res.json() : { students: [] }; })
                        .then(function (j) { self.found = j.students || []; })
                        .catch(function () { self.found = []; });
                },
                post: function (body) {
                    var m = document.querySelector('meta[name="csrf-token"]');
                    return fetch(c.url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': m ? m.content : '', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(body) })
                        .then(function (res) { return res.json().catch(function () { return {}; }).then(function (j) { return { ok: res.ok, status: res.status, json: j }; }); });
                },
                submit: async function () {
                    this.err = ''; this.msg = '';
                    var ids = this.recipientIds();
                    if (!ids.length) { this.err = this.form.classId === 'other' ? 'Chọn ít nhất 1 học sinh.' : 'Lớp này chưa có học sinh để giao.'; return; }
                    if (!this.form.deadline) { this.err = 'Vui lòng chọn ngày và giờ hạn đọc hợp lệ.'; return; }
                    this.busy = true;
                    try {
                        for (var i = 0; i < ids.length; i += 50) {
                            var res = await this.post({ product_id: this.form.productId, student_ids: ids.slice(i, i + 50), deadline: this.form.deadline, access_days: parseInt(this.form.days, 10), note: this.form.note });
                            if (!res.ok) {
                                var j = res.json || {};
                                this.err = j.errors ? j.errors[Object.keys(j.errors)[0]][0] : (res.status === 419 ? 'Phiên làm việc đã hết hạn. Hãy tải lại trang rồi thử lại.' : (j.message || 'Chưa lưu được. Vui lòng thử lại.'));
                                this.busy = false; return;
                            }
                        }
                        this.msg = 'Đã giao tài liệu cho ' + ids.length + ' học sinh.';
                        this.busy = false;
                    } catch (e) { this.err = 'Không kết nối được máy chủ. Vui lòng thử lại.'; this.busy = false; }
                },
            });
        }
    </script>
@endsection
