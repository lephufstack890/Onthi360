@extends('layouts.teacher')

{{-- SỬA 7/10 (khách: "Kho câu hỏi của tôi đổi lại thành Kho bài tập / câu hỏi và đề và xây như
     admin, chỉ khác là giáo viên không xem được Tag/Chuyên đề") — dựng theo khung
     admin/content/index.blade.php: banner, các TAB (đã dùng / chưa dùng trong đề / Đề-bộ bài /
     Kho chung), chip Môn, tab Dạng câu, bộ lọc Khối-Tỉnh thành-Năm-Tìm kiếm rồi tới bảng.
     KHÔNG có tab Tag/Chuyên đề và không có ô lọc Chuyên đề — phần đó dành cho Admin.

     SỬA 8/10 (khách: "UI Kho bài tập / câu hỏi và đề bên giáo viên cũng chỉnh lại UI luôn") — đổi sang
     phong cách của source mới như bên Admin (dùng chung partials.admin-content-ui): dải tab, chip lọc,
     bảng "Nội dung/Nguồn · Phân loại · Độ khó · Trạng thái · Thao tác" (gộp các cột cũ vào cùng ô, KHÔNG
     bỏ dữ liệu nào), phân trang 10 dòng/trang cho MỌI tab (chia trang ngay trên các dòng đã có, không đổi
     truy vấn). Bộ lọc, route, nút Sửa/Phát hành/Lưu trữ giữ nguyên. --}}
@section('title', 'Kho bài tập / câu hỏi và đề')
@section('page-title', 'Kho bài tập / câu hỏi và đề')

@section('content')
    @php
        $tab = $tab ?? 'used';
        $tabs = $tabs ?? [];
        $questions = $questions ?? [];
        $papers = $papers ?? [];
        $total = $total ?? count($questions);
        $isAssessments = $tab === 'assessments';
        $isShared = $isShared ?? ($tab === 'shared');
        $filters = $filters ?? [];
        $subjectOptions = $subjectOptions ?? [];
        $gradeOptions = $gradeOptions ?? [];
        $questionTypeOptions = $questionTypeOptions ?? [];
        $subjectCounts = $subjectCounts ?? [];
        $typeCounts = $typeCounts ?? [];

        // Icon theo MÃ loại câu ('mcq'/'fill_blank'/…), không theo nhãn tiếng Việt (xem App\Enums\QuestionType::label()).
        $typeIcons = ['mcq' => '🔤', 'fill_blank' => '✏️', 'coding' => '💻', 'composite' => '🧩'];

        $hasActiveFilter = collect($filters)->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();

        // Link chip/tab GIỮ NGUYÊN các bộ lọc đang bật (cùng cách làm bên admin).
        $filterLink = function (array $override = []) use ($filters, $tab) {
            $query = array_merge([
                'tab' => $tab,
                'subject' => $filters['subject'] ?? null,
                'grade' => $filters['grade'] ?? null,
                'type' => $filters['type'] ?? null,
                'province' => $filters['province'] ?? null,
                'exam_year' => $filters['exam_year'] ?? null,
                'io' => $filters['io'] ?? null,
                'q' => $filters['q'] ?? null,
            ], $override);

            return route('teacher.questions.index', array_filter($query, fn ($v) => $v !== null && $v !== ''));
        };
    @endphp

    @include('partials.admin-content-ui')
    <style>.akx-title--static:hover{color:#1e3a5f;text-decoration:none}.akx-ico{margin-right:6px}</style>

    @php
        $badgeOf = fn ($tone) => match ($tone) {
            'success' => 'acx-badge--ok',
            'warning' => 'acx-badge--warn',
            'danger', 'error' => 'acx-badge--off',
            'info' => 'acx-badge--info',
            default => '',
        };
        $activeTabLabel = collect($tabs)->first(fn ($t) => $t['active'] ?? false)['label'] ?? '';
    @endphp

    <x-ws.page-header title="Kho bài tập / câu hỏi và đề" icon="library" eyebrow="Giáo viên · Biên soạn" subtitle="Kho riêng chỉ bạn tạo/sửa/sử dụng — xem thêm Kho chung ở tab riêng (không sửa được).">
        <x-slot:actions>
            @if ($isAssessments)
                <a href="{{ route('teacher.papers.create') }}" class="inline-flex min-h-10 shrink-0 items-center justify-center gap-1.5 rounded-xl bg-white px-4 py-2 text-xs font-bold text-blue-700 shadow-sm transition-colors hover:bg-sky-50">+ Tạo đề PDF mới</a>
            @else
                <a href="{{ route('teacher.assessments.import') }}" class="inline-flex min-h-10 shrink-0 items-center justify-center gap-1.5 rounded-xl border border-white/35 bg-white/10 px-4 py-2 text-xs font-bold text-white backdrop-blur-sm transition-colors hover:bg-white/20">+ Nhập đề (Word/PDF/OCR)</a>
                <a href="{{ route('teacher.questions.create') }}" class="inline-flex min-h-10 shrink-0 items-center justify-center gap-1.5 rounded-xl bg-white px-4 py-2 text-xs font-bold text-blue-700 shadow-sm transition-colors hover:bg-sky-50">+ Tạo câu hỏi</a>
            @endif
        </x-slot:actions>
    </x-ws.page-header>

    @if (session('status') === 'question-created')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu nháp câu hỏi.'])
    @elseif (session('status') === 'question-updated')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu thay đổi.'])
    @elseif (session('status') === 'question-published')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã phát hành câu hỏi.'])
    @elseif (session('status') === 'question-archived')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu trữ câu hỏi.'])
    @elseif (session('status') === 'paper-created')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã tạo đề — tiếp tục tải PDF và nhập đáp án.'])
    @elseif (session('status') === 'assessment-published')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã phát hành đề.'])
    @elseif (session('status') === 'papers-bulk-created')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã tạo '.session('bulkCreatedCount').' đề PDF — vào từng đề để nhập đáp án.'])
    @endif
    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <nav class="apx-tabs" aria-label="Nhóm nội dung">
        @foreach ($tabs as $t)
            <a href="{{ $t['href'] }}" class="apx-tab {{ ($t['active'] ?? false) ? 'is-on' : '' }}" @if ($t['active'] ?? false) aria-current="page" @endif>{{ $t['label'] }}@isset($t['count'])<span>{{ $t['count'] }}</span>@endisset</a>
        @endforeach
    </nav>

    @if (! $isAssessments)
        <section class="acx-panel" style="margin-top:16px" aria-label="Bộ lọc câu hỏi">
            {{-- Hàng chip Môn: nhìn phát biết kho đang có bao nhiêu câu mỗi môn, bấm 1 phát lọc luôn. --}}
            <div class="akx-pad">
                <div class="akx-chips">
                    <a href="{{ $filterLink(['subject' => null]) }}" class="akx-chip {{ ! ($filters['subject'] ?? null) ? 'is-on' : '' }}">Tất cả môn</a>
                    @foreach ($subjectOptions as $code => $label)
                        @php $count = $subjectCounts[$code] ?? 0; @endphp
                        @if ($count > 0 || ($filters['subject'] ?? null) === $code)
                            <a href="{{ $filterLink(['subject' => $code]) }}" class="akx-chip {{ ($filters['subject'] ?? null) === $code ? 'is-on' : '' }}">{{ $label }} <small>({{ $count }})</small></a>
                        @endif
                    @endforeach
                    @if (($subjectCounts[''] ?? 0) > 0 || ($filters['subject'] ?? null) === 'none')
                        <a href="{{ $filterLink(['subject' => 'none']) }}" class="akx-chip akx-chip--warn {{ ($filters['subject'] ?? null) === 'none' ? 'is-on' : '' }}">Chưa phân loại <small>({{ $subjectCounts[''] ?? 0 }})</small></a>
                    @endif
                </div>
            </div>

            {{-- Dạng câu là dải TAB kèm số câu từng dạng, như admin. --}}
            <div class="akx-pad">
                <div class="akx-chips">
                    <a href="{{ $filterLink(['type' => null]) }}" class="akx-chip akx-chip--type {{ ! ($filters['type'] ?? null) ? 'is-on' : '' }}">Tất cả dạng <small>({{ array_sum($typeCounts) }})</small></a>
                    @foreach ($questionTypeOptions as $value => $label)
                        <a href="{{ $filterLink(['type' => $value]) }}" class="akx-chip akx-chip--type {{ ($filters['type'] ?? null) === $value ? 'is-on' : '' }}">{{ $label }} <small>({{ $typeCounts[$value] ?? 0 }})</small></a>
                    @endforeach
                </div>
            </div>

            <div class="akx-pad">
                <form method="GET" action="{{ route('teacher.questions.index') }}" class="akx-form">
                    {{-- Giữ tab đang đứng + Môn/Dạng (chọn bằng chip/tab phía trên) khi bấm "Lọc". --}}
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <input type="hidden" name="subject" value="{{ $filters['subject'] ?? '' }}">
                    <input type="hidden" name="type" value="{{ $filters['type'] ?? '' }}">
                    <div class="min-w-[120px]">
                        <label class="akx-lbl" for="filter-grade">Khối lớp</label>
                        <x-ws.select id="filter-grade" name="grade">
                            <option value="">Tất cả khối</option>
                            @foreach ($gradeOptions as $g)
                                <option value="{{ $g }}" @selected((string) ($filters['grade'] ?? '') === (string) $g)>Lớp {{ $g }}</option>
                            @endforeach
                            <option value="none" @selected(($filters['grade'] ?? null) === 'none')>Chưa gán khối</option>
                        </x-ws.select>
                    </div>
                    @include('partials.question-province-year-filter')
                    <div class="akx-grow">
                        <label class="akx-lbl" for="filter-q">Tìm theo tên hoặc mã</label>
                        <div class="acx-search">
                            <x-lucide name="search" />
                            <input id="filter-q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" maxlength="100"
                                   placeholder="Ví dụ: ước chung, TOAN6…" class="admin-input">
                        </div>
                    </div>
                    <button type="submit" class="acx-btn acx-btn--primary">Lọc</button>
                    @if ($hasActiveFilter)
                        <a href="{{ route('teacher.questions.index', ['tab' => $tab]) }}" class="acx-btn">Xoá lọc</a>
                    @endif
                </form>
            </div>
        </section>

        <section class="acx-panel" style="margin-top:16px" aria-label="Danh sách câu hỏi">
            <div class="akx-sub">
                <span><b>{{ $total }}</b> kết quả{{ $activeTabLabel ? ' · '.$activeTabLabel : '' }}</span>
                @if (count($questions) < $total)
                    <span>Đang hiện {{ count($questions) }} / {{ $total }} — dùng bộ lọc để thu hẹp</span>
                @endif
            </div>

            <div class="acx-scroll" data-pg="rows" data-unit="câu hỏi">
                <table class="acx-table apx-up akx-table is-q" style="min-width:1040px">
                    <caption class="acx-sr">Danh sách câu hỏi</caption>
                    <thead>
                        <tr>
                            <th scope="col">Nội dung / Nguồn</th>
                            <th scope="col">Phân loại</th>
                            <th scope="col">Độ khó</th>
                            {{-- SỬA 28/10 — câu Lập trình đọc từ tệp hay bàn phím (partials/question-io-cell). --}}
                            <th scope="col">Input/Output</th>
                            <th scope="col">Trạng thái</th>
                            <th scope="col">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($questions as $q)
                            <tr data-pg-item data-qid="{{ $q['id'] }}" data-qtype="{{ $q['typeValue'] ?? '' }}" @if ($q['readOnly'] ?? false) data-ro="1" @endif>
                                <td>
                                    <span class="akx-title akx-title--static"><span class="akx-ico">{{ $typeIcons[$q['typeValue'] ?? ''] ?? '❓' }}</span>{{ $q['title'] }}</span>
                                    <div class="akx-meta">
                                        @if (! empty($q['code']))<code>{{ $q['code'] }}</code>@endif
                                        @if (($q['subject'] ?? '') === 'Chưa phân loại')
                                            <span class="akx-pill akx-pill--warn">Chưa phân loại</span>
                                        @elseif (! empty($q['subject']))
                                            <span>{{ $q['subject'] }}</span>
                                        @endif
                                        @if (! empty($q['grade']))<span>· {{ $q['grade'] }}</span>@endif
                                    </div>
                                    <div class="akx-src">Nguồn: {{ $q['owner'] }}</div>
                                </td>
                                <td>
                                    <div class="akx-k">{{ $q['type'] }}</div>
                                    <div class="akx-k2">{{ $q['province'] ?? '—' }}</div>
                                    <div class="akx-k2">Năm: {{ $q['examYear'] ?? '—' }}</div>
                                </td>
                                {{-- Chưa đặt thì hiện mờ + chú thích: giá trị đang được SUY theo điểm câu hỏi. --}}
                                <td>
                                    @if ($q['difficultySet'] ?? false)
                                        <span class="akx-k">{{ $q['difficulty'] }}</span>
                                    @else
                                        <span class="akx-mute" title="Chưa đặt — hệ thống tự suy theo điểm câu hỏi">{{ $q['difficulty'] }} <span style="font-size:11px">(tự suy)</span></span>
                                    @endif
                                </td>
                                <td>@include('partials.question-io-cell', ['io' => $q['io'] ?? null])</td>
                                <td><span class="acx-badge {{ $badgeOf($q['tone'] ?? null) }}">{{ $q['status'] }}</span></td>
                                <td>
                                    <div class="akx-act">
                                        @if ($q['readOnly'] ?? false)
                                            <span class="akx-mute" style="font-size:12px;text-align:right">Thuộc Kho chung — chỉ Admin/Editor sửa được (6.5).</span>
                                        @else
                                            {{-- SỬA 10/10 — đổi chỗ câu với câu liền trên/dưới (cùng dạng bài); thứ tự này cũng là thứ tự trang Luyện tập public. --}}
                                            <span class="tq-mv">
                                                <button type="button" class="tq-mv__b" data-tmove="up" title="Đưa câu này lên đứng ngay trước câu phía trên nó">Đưa lên trước</button>
                                                <button type="button" class="tq-mv__b tq-mv__b--dn" data-tmove="down" title="Đưa câu này xuống đứng ngay sau câu phía dưới nó">Đưa xuống dưới</button>
                                            </span>
                                            <div class="akx-act__links">
                                                <a href="{{ route('teacher.questions.edit', $q['id']) }}" class="akx-lnk">Sửa</a>
                                                @if ($q['canPublish'])
                                                    <form method="POST" action="{{ route('teacher.questions.publish', $q['id']) }}" class="inline">
                                                        @csrf
                                                        <button type="submit" class="akx-lnk akx-lnk--ok">Phát hành</button>
                                                    </form>
                                                @endif
                                                @if ($q['canArchive'])
                                                    <form method="POST" action="{{ route('teacher.questions.archive', $q['id']) }}" class="inline">
                                                        @csrf
                                                        <button type="submit" class="akx-lnk akx-lnk--mute">Lưu trữ</button>
                                                    </form>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if (count($questions) === 0)
                    <div class="akx-empty">
                        <strong>{{ $hasActiveFilter ? 'Không có câu hỏi nào khớp bộ lọc' : 'Chưa có câu hỏi nào' }}</strong>
                        {{ $hasActiveFilter ? 'Thử bỏ bớt điều kiện hoặc bấm "Xoá lọc".' : ($isShared ? 'Kho chung chưa có câu hỏi nào.' : ($tab === 'unused' ? 'Chưa có câu hỏi nào ngoài đề.' : 'Chưa có câu hỏi nào nằm trong đề.')) }}
                    </div>
                @endif
            </div>
            {{-- SỬA 8/10 (khách: "quá 10 item thì phân trang") — chia trang 10 dòng/trang ngay trên các dòng đã có. --}}
            <div class="akx-pg" data-pg-nav="rows" hidden></div>
        </section>
    @else
        {{-- Tab Đề/bộ bài — thay cho trang "Đề PDF của tôi" cũ (đề PDF riêng tư của giáo viên, chờ Admin duyệt đưa ra kho chung). --}}
        <section class="acx-panel" style="margin-top:16px" aria-label="Danh sách đề">
            <div class="akx-sub">
                <span><b>{{ count($papers) }}</b> kết quả{{ $activeTabLabel ? ' · '.$activeTabLabel : '' }}</span>
            </div>
            <div class="acx-scroll" data-pg="rows" data-unit="đề">
                <table class="acx-table apx-up akx-table" style="min-width:760px">
                    <caption class="acx-sr">Danh sách đề PDF</caption>
                    <thead>
                        <tr>
                            <th scope="col">Tên đề / Mã đề</th>
                            <th scope="col">Loại</th>
                            <th scope="col">PDF · Câu/đáp án</th>
                            <th scope="col">Trạng thái</th>
                            <th scope="col">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($papers as $p)
                            <tr data-pg-item>
                                <td>
                                    <span class="akx-title akx-title--static">{{ $p['title'] }}</span>
                                    <div class="akx-src">Mã đề: {{ $p['examCode'] ?: '—' }}</div>
                                </td>
                                <td><div class="akx-k">{{ $p['type'] }}</div></td>
                                <td>
                                    <div class="akx-k" style="color:{{ $p['hasPdf'] ? '#059669' : '#d97706' }}">PDF: {{ $p['hasPdf'] ? 'Đã tải' : 'Chưa tải' }}</div>
                                    <div class="akx-k2">{{ $p['answerKeysCount'] }} câu · {{ $p['codingItemsCount'] }} bài code</div>
                                </td>
                                <td><span class="acx-badge {{ $badgeOf($p['tone'] ?? null) }}">{{ $p['status'] }}</span></td>
                                <td>
                                    <div class="akx-act">
                                        <div class="akx-act__links">
                                            <a href="{{ route('teacher.papers.pdf.edit', $p['id']) }}" class="akx-lnk">Quản lý đề PDF</a>
                                            @if ($p['canPublish'])
                                                <form method="POST" action="{{ route('teacher.assessments.publish', $p['id']) }}" class="inline">
                                                    @csrf
                                                    <button type="submit" class="akx-lnk akx-lnk--ok">Phát hành</button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if (count($papers) === 0)
                    <div class="akx-empty"><strong>Chưa có đề nào</strong>Bấm "+ Tạo đề PDF mới" để bắt đầu.</div>
                @endif
            </div>
            <div class="akx-pg" data-pg-nav="rows" hidden></div>
        </section>
    @endif

@push('scripts')
<style>
    .tq-mv{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:4px 12px;margin-bottom:6px}
    .tq-mv__b{border:0;background:transparent;padding:0;color:#2563eb;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap}
    .tq-mv__b::before{content:"\2191";margin-right:4px}
    .tq-mv__b--dn::before{content:"\2193"}
    .tq-mv__b:hover{text-decoration:underline}
    .tq-mv__b[disabled]{color:#8aa0b6;cursor:default;text-decoration:none}
    .tq-flash{background:#ecfdf5 !important;transition:background 1.2s}
</style>
<script>
    (function () {
        var csrf = @json(csrf_token());
        var moveUrl = @json(url('teacher/questions/__ID__/move'));
        function rows() { return Array.prototype.slice.call(document.querySelectorAll('tr[data-qid]:not([data-ro])')); }
        function refresh() {
            var all = rows();
            all.forEach(function (tr, i) {
                var up = tr.querySelector('[data-tmove="up"]'), dn = tr.querySelector('[data-tmove="down"]');
                if (!up || !dn) return;
                var prev = all[i - 1], next = all[i + 1], t = tr.dataset.qtype;
                var noUp = !prev || prev.dataset.qtype !== t, noDn = !next || next.dataset.qtype !== t;
                up.disabled = noUp; dn.disabled = noDn;
                up.textContent = noUp ? 'Đang ở đầu' : 'Đưa lên trước';
                dn.textContent = noDn ? 'Đang ở cuối' : 'Đưa xuống dưới';
            });
        }
        document.addEventListener('click', function (e) {
            var b = e.target.closest ? e.target.closest('[data-tmove]') : null;
            if (!b || b.disabled) return;
            var tr = b.closest('tr'), all = rows(), i = all.indexOf(tr), dir = b.dataset.tmove;
            var nb = dir === 'up' ? all[i - 1] : all[i + 1];
            if (!nb || nb.dataset.qtype !== tr.dataset.qtype) return;
            b.disabled = true;
            fetch(moveUrl.replace('__ID__', tr.dataset.qid), {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                body: JSON.stringify({ dir: dir, neighbor_id: parseInt(nb.dataset.qid, 10) })
            }).then(function (res) { return res.json().catch(function () { return {}; }).then(function (j) { return { ok: res.ok, j: j }; }); })
              .then(function (r) {
                  if (!r.ok) { alert((r.j && (r.j.message || (r.j.errors && Object.values(r.j.errors)[0][0]))) || 'Không đổi được thứ tự.'); refresh(); return; }
                  // Đổi chỗ 2 dòng trên màn hình; ẩn/hiện theo VỊ TRÍ (trang đang xem) nên hoán đổi cờ hidden cho 2 dòng.
                  var h1 = tr.hidden, h2 = nb.hidden;
                  if (dir === 'up') nb.parentNode.insertBefore(tr, nb); else nb.parentNode.insertBefore(tr, nb.nextSibling);
                  tr.hidden = h2; nb.hidden = h1;
                  tr.classList.add('tq-flash'); setTimeout(function () { tr.classList.remove('tq-flash'); }, 1400);
                  refresh();
              })
              .catch(function () { alert('Không đổi được thứ tự — kiểm tra kết nối rồi thử lại.'); refresh(); });
        });
        document.addEventListener('DOMContentLoaded', refresh);
    })();
</script>
@endpush
@endsection
