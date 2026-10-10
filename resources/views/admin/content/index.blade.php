@extends('layouts.admin')

@section('title', 'Kho bài tập / câu hỏi và đề')
@section('page-title', 'Kho bài tập / câu hỏi và đề')

@section('content')
    @php
        $tab = $tab ?? 'questions';
        $tabs = $tabs ?? [];
        $rows = $rows ?? [];
        $documents = $documents ?? [];
        $tags = $tags ?? [];
        $total = $total ?? count($rows);
        // SỬA 7/10 — phân trang tab Câu hỏi, xem ContentService::indexData().
        $pagination = $pagination ?? null;
        // SỬA 8/9 (3) ("phân loại kho câu hỏi theo môn") — dữ liệu bộ lọc chỉ có ở tab Câu hỏi,
        // xem ContentService::indexData().
        $isQuestions = $tab === 'questions';
        $filters = $filters ?? [];
        $subjectOptions = $subjectOptions ?? [];
        $gradeOptions = $gradeOptions ?? [];
        $questionTypeOptions = $questionTypeOptions ?? [];
        $statusOptions = $statusOptions ?? [];
        $difficultyOptions = $difficultyOptions ?? [];
        // SỬA 4/10 — ô lọc "Dùng trong đề" (xem ContentService::indexData()).
        $inExamOptions = $inExamOptions ?? [];
        $subjectCounts = $subjectCounts ?? [];
        // SỬA 30/9 — dải TAB theo dạng câu + ô lọc Chuyên đề (xem ContentService::indexData()).
        $typeCounts = $typeCounts ?? [];
        $tagOptions = $tagOptions ?? [];
        // SỬA 7/10 — in_exam giờ là TAB (luôn có giá trị) nên không tính là "đang lọc".
        $hasActiveFilter = collect($filters)->except('in_exam')->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();

        // Dựng link cho các chip/tab mà GIỮ NGUYÊN những bộ lọc đang bật — trước đây mỗi chip tự
        // liệt kê tay từng khoá, thêm 1 bộ lọc mới là phải sửa 3 chỗ và rất dễ sót (chip Môn từng
        // làm mất bộ lọc Độ khó theo kiểu đó).
        $filterLink = function (array $override = []) use ($filters) {
            $query = array_merge([
                'tab' => 'questions',
                'in_exam' => $filters['in_exam'] ?? 'used',
                'subject' => $filters['subject'] ?? null,
                'grade' => $filters['grade'] ?? null,
                'type' => $filters['type'] ?? null,
                'status' => $filters['status'] ?? null,
                'difficulty' => $filters['difficulty'] ?? null,
                // SỬA 1/10 — 2 bộ lọc mới PHẢI có ở đây, nếu không bấm chip Môn/dải tab Dạng câu
                // là mất lọc Tỉnh thành/Năm đang bật (đúng lỗi đã ghi trong ghi chú ngay trên).
                'province' => $filters['province'] ?? null,
                'exam_year' => $filters['exam_year'] ?? null,
                'tag' => $filters['tag'] ?? null,
                'q' => $filters['q'] ?? null,
            ], $override);

            return route('admin.content.index', array_filter($query, fn ($v) => $v !== null && $v !== ''));
        };

        // SỬA 8/10 — chỉ là chữ/màu hiển thị: tông huy hiệu -> lớp kiểu, và nhãn tab đang mở cho dòng "n kết quả".
        $badgeOf = fn ($tone) => match ($tone) {
            'success' => 'acx-badge--ok',
            'warning' => 'acx-badge--warn',
            'danger', 'error' => 'acx-badge--off',
            'info' => 'acx-badge--info',
            default => '',
        };
        $activeTabLabel = collect($tabs)->first(fn ($t) => $t['active'] ?? false)['label'] ?? '';
        $resultTotal = $isQuestions ? ($pagination['total'] ?? $total) : $total;
    @endphp

    {{--
      SỬA 8/10 (khách: "cập nhật UI màn Kho bài tập / câu hỏi và đề theo source mới, logic giữ nguyên") — dựng
      lại theo AdminContentWorkspace.jsx: dải tab nhóm có số đếm, khung tìm/lọc, dòng "n kết quả", bảng
      "Nội dung/Nguồn · Phân loại · Độ khó · Trạng thái · Thao tác" (gộp các cột cũ vào cùng ô, KHÔNG bỏ dữ
      liệu nào), phân trang. Dữ liệu từ ContentService::indexData(), bộ lọc/route/nút Sửa-Xem-Xoá-Duyệt,
      nút "Đưa lên trước" và script lưu thứ tự GIỮ NGUYÊN. Khách yêu cầu thêm: tab nào quá 10 mục cũng phân
      trang (tab Câu hỏi: phân trang máy chủ như cũ; Đề thi / Tag-Chuyên đề / Chờ rà soát: chia trang ngay
      trên các dòng đã có).
    --}}
    @include('partials.admin-content-ui')

    <x-ws.page-header title="Kho bài tập / câu hỏi và đề" icon="library" eyebrow="Quản trị & biên soạn" subtitle="Quản lý câu hỏi và đề — sửa là cập nhật trực tiếp.">
        <x-slot:actions>
            @if ($tab === 'questions')
                <a href="{{ route('admin.content.questions.create') }}" class="inline-flex min-h-10 shrink-0 items-center justify-center gap-1.5 rounded-xl bg-white px-4 py-2 text-xs font-bold text-blue-700 shadow-sm transition-colors hover:bg-sky-50">+ Tạo câu hỏi</a>
            @elseif ($tab === 'assessments')
                <a href="{{ route('admin.content.assessments.create') }}" class="inline-flex min-h-10 shrink-0 items-center justify-center gap-1.5 rounded-xl bg-white px-4 py-2 text-xs font-bold text-blue-700 shadow-sm transition-colors hover:bg-sky-50">+ Tạo đề/bộ bài</a>
                {{-- SỬA 19/8 (Giai đoạn 3 — "Bộ đề"): tạo nhiều đề PDF cùng lúc, khác hẳn nút
                     "+ Tạo đề/bộ bài" ở trên (tạo TỪNG đề trống 1 lần). --}}
                {{-- <a href="{{ route('admin.content.assessments.bulk.create') }}" class="inline-flex min-h-10 shrink-0 items-center justify-center gap-1.5 rounded-xl border border-white/35 bg-white/10 px-4 py-2 text-xs font-bold text-white backdrop-blur-sm transition-colors hover:bg-white/20">+ Tải bộ đề (nhiều đề PDF)</a> --}}
            @endif
            {{-- <a href="{{ route('admin.content.questions.import') }}" class="inline-flex min-h-10 shrink-0 items-center justify-center gap-1.5 rounded-xl border border-white/35 bg-white/10 px-4 py-2 text-xs font-bold text-white backdrop-blur-sm transition-colors hover:bg-white/20">+ Nhập đề (Word/PDF/OCR)</a> --}}
        </x-slot:actions>
    </x-ws.page-header>

    @if (session('status') === 'assessments-bulk-created')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã tạo '.session('bulkCreatedCount').' đề PDF — vào từng đề để nhập đáp án.'])
    @elseif (session('status') === 'materials-bulk-imported')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã tải lên '.session('bulkCreatedCount').' bài — vào từng bài nếu cần sửa tên/mã/PDF.'])
    @elseif (session('status') === 'assessment-promoted-shared')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã đưa đề vào Kho chung.'])
    @elseif (session('status') === 'tag-created')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã tạo tag mới.'])
    @elseif (session('status') === 'tag-updated')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã đổi tên tag.'])
    @elseif (session('status') === 'tag-deleted')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã xoá tag.'])
    @elseif (session('status') === 'material-deleted')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã xoá học liệu cùng bài con và file PDF liên quan.'])
    {{-- SỬA 4/10 — 2 trạng thái mới của nút Xoá. Phải đặt TRƯỚC nhánh session('status') chung ở
         dưới, nếu không chúng rơi vào đó và hiện câu "Đã cập nhật nội dung" chẳng ăn nhập gì.
         Lý do bị TỪ CHỐI (đã có người làm bài / đang nằm trong đề) đi theo đường $errors và đã
         được khối báo lỗi ngay bên dưới hiện ra. --}}
    @elseif (session('status') === 'question-deleted' || session('status') === 'assessment-deleted')
        @include('partials.toast-flash', ['type' => 'success', 'message' => session('statusMessage', 'Đã xoá.')])
    @elseif (session('status'))
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã cập nhật nội dung.'])
    @endif
    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <nav class="apx-tabs" aria-label="Nhóm nội dung">
        @foreach ($tabs as $t)
            <a href="{{ $t['href'] }}" class="apx-tab {{ ($t['active'] ?? false) ? 'is-on' : '' }}" @if ($t['active'] ?? false) aria-current="page" @endif>{{ $t['label'] }}@isset($t['count'])<span>{{ $t['count'] }}</span>@endisset</a>
        @endforeach
    </nav>

    @if ($isQuestions)
        <section class="acx-panel" style="margin-top:16px" aria-label="Bộ lọc câu hỏi">
            {{-- Hàng chip: nhìn phát biết kho đang có bao nhiêu câu mỗi môn, bấm 1 phát lọc luôn. --}}
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
                        {{-- Nhóm "Chưa phân loại" (subject IS NULL) — chỗ để dọn dần câu cũ, xem lệnh
                             `php artisan questions:backfill-subject --all`. --}}
                        <a href="{{ $filterLink(['subject' => 'none']) }}" class="akx-chip akx-chip--warn {{ ($filters['subject'] ?? null) === 'none' ? 'is-on' : '' }}">Chưa phân loại <small>({{ $subjectCounts[''] ?? 0 }})</small></a>
                    @endif
                </div>
            </div>

            {{-- SỬA 30/9 (khách: "dạng câu ở dưới làm tab phân chia dạng câu") — dạng câu giờ là
                 TAB, không còn là 1 ô chọn trong hàng bộ lọc. Mỗi tab in luôn số câu của dạng đó
                 để nhìn phát biết kho đang nặng dạng nào. --}}
            <div class="akx-pad">
                <div class="akx-chips">
                    <a href="{{ $filterLink(['type' => null]) }}" class="akx-chip akx-chip--type {{ ! ($filters['type'] ?? null) ? 'is-on' : '' }}">Tất cả dạng <small>({{ array_sum($typeCounts) }})</small></a>
                    @foreach ($questionTypeOptions as $value => $label)
                        <a href="{{ $filterLink(['type' => $value]) }}" class="akx-chip akx-chip--type {{ ($filters['type'] ?? null) === $value ? 'is-on' : '' }}">{{ $label }} <small>({{ $typeCounts[$value] ?? 0 }})</small></a>
                    @endforeach
                </div>
            </div>

            <div class="akx-pad">
                <form method="GET" action="{{ route('admin.content.index') }}" class="akx-form">
                    <input type="hidden" name="tab" value="questions">
                    {{-- SỬA 7/10 (khách: bỏ ô Môn học / Trạng thái / Độ khó / Chuyên đề / Dùng trong đề) —
                         Môn vẫn lọc bằng hàng chip phía trên, nên gửi kèm giá trị đang chọn để bấm "Lọc" không làm mất. --}}
                    <input type="hidden" name="subject" value="{{ $filters['subject'] ?? '' }}">
                    <input type="hidden" name="in_exam" value="{{ $filters['in_exam'] ?? 'used' }}">
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
                    {{-- SỬA 30/9 — "Dạng câu" đã chuyển thành DẢI TAB ở trên (khách: "dạng câu ở dưới
                         làm tab phân chia dạng câu"). Vẫn gửi kèm giá trị đang chọn để bấm "Lọc" ở
                         các ô còn lại không làm mất tab đang đứng. --}}
                    <input type="hidden" name="type" value="{{ $filters['type'] ?? '' }}">
                    <div class="akx-grow">
                        <label class="akx-lbl" for="filter-q">Tìm theo tên hoặc mã</label>
                        <div class="acx-search">
                            <x-lucide name="search" />
                            <input id="filter-q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" maxlength="100"
                                   placeholder="Ví dụ: ước chung, TOAN6…"
                                   class="admin-input">
                        </div>
                    </div>
                    <button type="submit" class="acx-btn acx-btn--primary">Lọc</button>
                    @if ($hasActiveFilter)
                        <a href="{{ route('admin.content.index', ['tab' => 'questions', 'in_exam' => $filters['in_exam'] ?? 'used']) }}" class="acx-btn">Xoá lọc</a>
                    @endif
                </form>
            </div>
        </section>
    @endif

    @if ($tab === 'drafts')
        <div class="acx-stack" style="margin-top:16px" data-pg="drafts" data-unit="tài liệu">
            @forelse ($documents as $d)
                <div class="acx-card acx-card--white" data-pg-item>
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <x-ws.icon-tile emoji="📄" tone="sky" />
                            <div>
                                <p class="akx-title" style="font-size:13px">{{ $d['name'] }}</p>
                                <p class="akx-src">Người tải lên: {{ $d['uploader'] }}</p>
                                <div class="w-48 mt-1"><x-ws.progress-bar :percent="$d['progress']" tone="{{ $d['tone'] === 'warning' ? 'warning' : ($d['tone'] === 'danger' ? 'danger' : 'info') }}" /></div>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="acx-badge {{ $badgeOf($d['tone'] ?? null) }}">{{ $d['status'] }}</span>
                            @if ($d['reviewable'])
                                <a href="{{ route('admin.content.questions.reviewDraft', ['document' => $d['id']]) }}" class="akx-lnk block mt-1">Rà soát ngay ›</a>
                            @endif
                        </div>
                    </div>
                    @if ($d['errorLog'])
                        <p class="text-xs text-blue-600 bg-blue-50 rounded-xl px-3 py-2 mt-3">⚠ {{ $d['errorLog'] }}</p>
                    @endif
                </div>
            @empty
                <x-ws.empty-state
                    title="Không có tài liệu nào đang chờ rà soát"
                    description="Kết quả OCR không tự phát hành — bấm &quot;+ Nhập đề (Word/PDF/OCR)&quot; ở trên để tải Word/PDF lên (6.4)."
                    actionLabel="+ Nhập đề (Word/PDF/OCR)"
                    :actionHref="route('admin.content.questions.import')" />
            @endforelse
        </div>
        <div class="akx-pg acx-panel" style="margin-top:12px" data-pg-nav="drafts" hidden></div>
    @elseif ($tab === 'tags')
        {{-- SỬA 19/8 (Giai đoạn 6 — "Gắn tag/chủ đề cho câu hỏi"): CRUD gọn trong 1 khối,
             không cần trang riêng — xem ContentService::indexData()/tagStore()/tagUpdate()/
             tagDestroy(). Tag dùng để lọc ở màn "Luyện tập theo câu" của học sinh và ở form
             tạo/sửa câu hỏi (Admin + Giáo viên). --}}
        <div class="acx-card acx-card--white" style="margin-top:16px">
            <h2><x-lucide name="plus" /> Thêm tag mới</h2>
            <form method="POST" action="{{ route('admin.content.tags.store') }}" class="flex flex-wrap items-center gap-3">
                @csrf
                <input type="text" name="name" required maxlength="120" placeholder="VD: Đại số, Hình học, Dao động cơ..."
                       class="apx-input apx-input--grow" style="min-width:220px">
                <button type="submit" class="acx-btn acx-btn--primary">Thêm tag</button>
            </form>
        </div>

        <section class="acx-panel" style="margin-top:16px">
            <div class="akx-sub"><span><b>{{ $resultTotal }}</b> kết quả · {{ $activeTabLabel }}</span></div>
            <div data-pg="tags" data-unit="tag">
                @forelse ($tags as $t)
                    <div class="apx-item" data-pg-item style="padding:12px 20px" x-data="{ editing: false }">
                        <form method="POST" action="{{ route('admin.content.tags.update', $t['id']) }}" class="flex-1 flex items-center gap-2" x-show="editing" x-cloak>
                            @csrf
                            @method('PUT')
                            <input type="text" name="name" value="{{ $t['name'] }}" required maxlength="120" class="apx-input apx-input--grow">
                            <button type="submit" class="akx-lnk">Lưu</button>
                            <button type="button" @click="editing = false" class="akx-lnk akx-lnk--mute">Huỷ</button>
                        </form>
                        <div class="apx-item__main" x-show="!editing">
                            <p class="apx-item__title">{{ $t['name'] }}</p>
                            <p class="apx-item__sub">{{ $t['questionsCount'] }} câu hỏi đang dùng</p>
                        </div>
                        <div class="apx-item__side" x-show="!editing">
                            <button type="button" @click="editing = true" class="acx-link">Đổi tên</button>
                            <form method="POST" action="{{ route('admin.content.tags.destroy', $t['id']) }}" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="acx-link acx-link--del">Xoá</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="akx-empty"><strong>Chưa có tag nào</strong>Thêm tag đầu tiên ở trên.</div>
                @endforelse
            </div>
            <div class="akx-pg" data-pg-nav="tags" hidden></div>
        </section>
    @else
        {{-- SỬA 8/9 (3) — tab Câu hỏi có thêm Môn/Khối (và mã câu hỏi dưới tên) để nhìn bảng là biết
             ngay câu nào chưa phân loại; SỬA 1/10 — thêm Tỉnh thành/Năm. SỬA 8/10: các thông tin này giờ gộp
             vào cùng ô theo bản mẫu (Nội dung/Nguồn, Phân loại) — không bỏ thông tin nào. --}}
        <section class="acx-panel" style="margin-top:16px" aria-label="Danh sách nội dung">
            <div class="akx-sub">
                <span><b>{{ $resultTotal }}</b> kết quả{{ $activeTabLabel ? ' · '.$activeTabLabel : '' }}</span>
                @if ($isQuestions)<span>Sắp xếp: ưu tiên cao trước</span>@endif
            </div>
            @if ($isQuestions)
                {{-- SỬA 7/10 (khách: "hiển thị cột thứ tự ra ngoài danh sách, sửa trực tiếp trên từng dòng")
                     — chú thích cách đọc cột Thứ tự. --}}
                <p class="oi-ord-hint akx-hint">Nút <strong>Đưa lên trước</strong> (cột cuối) chen câu lên đứng ngay trước câu phía trên nó — hệ thống tự lưu.</p>
            @endif

            <div class="acx-scroll" @unless ($isQuestions) data-pg="rows" data-unit="kết quả" @endunless>
                <table class="acx-table apx-up akx-table {{ $isQuestions ? 'is-q' : '' }}" @if ($isQuestions) style="min-width:900px" @endif>
                    <caption class="acx-sr">Danh sách nội dung</caption>
                    <thead>
                        <tr>
                            <th scope="col">Nội dung / Nguồn</th>
                            <th scope="col">Phân loại</th>
                            @if ($isQuestions)<th scope="col">Độ khó</th>@endif
                            <th scope="col">Trạng thái</th>
                            <th scope="col">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $r)
                            <tr @unless ($isQuestions) data-pg-item @endunless>
                                <td>
                                    {{-- SỬA 23/9 — kèm 'kind' để mở ĐÚNG loại nội dung, tránh trùng id giữa 3 bảng. --}}
                                    <a href="{{ route('admin.content.show', ['content' => $r['id'], 'kind' => $r['kind'] ?? null]) }}" class="akx-title">{{ $r['title'] }}</a>
                                    @if ($isQuestions)
                                        <div class="akx-meta">
                                            @if (! empty($r['code']))<code>{{ $r['code'] }}</code>@endif
                                            @if (($r['subject'] ?? '') === 'Chưa phân loại')
                                                <span class="akx-pill akx-pill--warn">Chưa phân loại</span>
                                            @elseif (! empty($r['subject']))
                                                <span>{{ $r['subject'] }}</span>
                                            @endif
                                            @if (! empty($r['grade']))<span>· {{ $r['grade'] }}</span>@endif
                                        </div>
                                    @endif
                                    <div class="akx-src">Nguồn: {{ $r['owner'] }}</div>
                                </td>
                                <td>
                                    <div class="akx-k">{{ $r['type'] }}</div>
                                    @if ($isQuestions)
                                        <div class="akx-k2">{{ $r['province'] ?? '—' }}</div>
                                        <div class="akx-k2">Năm: {{ $r['examYear'] ?? '—' }}</div>
                                    @endif
                                </td>
                                @if ($isQuestions)
                                    {{-- Chưa đặt thì hiện mờ + chú thích: giá trị đang được SUY theo điểm câu
                                         hỏi, chưa phải do người soạn chọn (xem App\Support\QuestionDifficulty). --}}
                                    <td>
                                        @if ($r['difficultySet'] ?? false)
                                            <span class="akx-k">{{ $r['difficulty'] }}</span>
                                        @else
                                            <span class="akx-mute" title="Chưa đặt — hệ thống tự suy theo điểm câu hỏi">{{ $r['difficulty'] }} <span style="font-size:11px">(tự suy)</span></span>
                                        @endif
                                    </td>
                                @endif
                                <td><span class="acx-badge {{ $badgeOf($r['tone'] ?? null) }}">{{ $r['status'] }}</span></td>
                                <td>
                                    <div class="akx-act">
                                        {{-- SỬA 7/10 (khách: "ẩn cột Thứ tự, đưa nút Đưa lên trước ra cùng cột với Sửa/Xem/Xoá")
                                             — chỉ còn nút chen câu này lên ngay trước câu phía trên nó; số thứ tự nằm trong
                                             ô ẩn để máy chủ trả số mới về. Dòng đầu trang 1 → "Đang ở đầu". --}}
                                        @if ($isQuestions)
                                            <span class="oi-ord" data-ord data-href="{{ $r['orderHref'] }}" data-saved="{{ $r['displayOrder'] }}" data-id="{{ $r['id'] }}" data-type="{{ $r['typeValue'] ?? '' }}">
                                                <input type="hidden" class="oi-ord__in" value="{{ $r['displayOrder'] }}">
                                                <button type="button" class="oi-ord__top" data-act="up" @if ($loop->first) data-prev="{{ $leadPrevId ?? '' }}" data-prevtype="{{ $leadPrevType ?? '' }}" @endif title="Đưa câu này lên đứng ngay trước câu phía trên nó">Đưa lên trước</button>
                                                <span class="oi-ord__st" aria-live="polite"></span>
                                            </span>
                                        @endif
                                        <div class="akx-act__links">
                                            {{-- SỬA 30/9 (khách: "thêm nút sửa bên này nữa để người ta tiện sửa câu hỏi")
                                                 — vào thẳng màn Sửa, khỏi phải bấm "Xem" rồi tìm nút Sửa trong trang chi
                                                 tiết. Link do ContentService::indexData() dựng sẵn theo đúng loại nội dung
                                                 của từng dòng (câu hỏi / đề / học liệu). --}}
                                            @if ($r['editHref'] ?? null)
                                                <a href="{{ $r['editHref'] }}" class="akx-lnk">Sửa</a>
                                            @endif
                                            <a href="{{ route('admin.content.show', ['content' => $r['id'], 'kind' => $r['kind'] ?? null]) }}" class="akx-lnk akx-lnk--mute">Xem</a>
                                            {{-- SỬA 19/8 (Giai đoạn 4): chỉ đề của giáo viên (tab "Đề/bộ bài") mới có nút
                                                 này — xem ContentService::indexData()/assessmentPromoteToShared(). --}}
                                            @if ($r['canPromoteToShared'] ?? false)
                                                <form method="POST" action="{{ route('admin.content.assessments.promoteShared', $r['id']) }}" class="inline">
                                                    @csrf
                                                    <button type="submit" class="akx-lnk akx-lnk--ok">Duyệt vào kho chung</button>
                                                </form>
                                            @endif
                                            {{-- SỬA 25/8 (7) — "thêm tính năng xóa cho admin": xoá THẬT, xoá luôn tệp
                                                 trên đĩa, không khôi phục được nên PHẢI xác nhận qua confirm().
                                                 SỬA 4/10 (khách: "phần danh sách bài không thấy nút xoá") — cả 3 tab
                                                 (Câu hỏi / Đề / Học liệu) đều có, địa chỉ lẫn câu hỏi xác nhận do
                                                 indexData() dựng sẵn theo đúng loại nội dung của từng dòng. --}}
                                            @if ($r['canDelete'] ?? false)
                                                <form method="POST" action="{{ $r['deleteHref'] }}" class="inline" onsubmit="return confirm('{{ $r['deleteLabel'] }}');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="akx-lnk akx-lnk--del"><x-lucide name="trash-2" /> Xoá</button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if (count($rows) === 0)
                    <div class="akx-empty">
                        <strong>{{ $isQuestions && $hasActiveFilter ? 'Không có câu hỏi nào khớp bộ lọc' : 'Chưa có dữ liệu.' }}</strong>
                        {{ $isQuestions && $hasActiveFilter ? 'Thử bỏ bớt điều kiện hoặc bấm "Xoá lọc".' : 'Thêm nội dung đầu tiên để bắt đầu.' }}
                    </div>
                @endif
            </div>

            {{-- SỬA 7/10 (khách: "quá 10 item thì phân trang") — tab Câu hỏi (cả "đã dùng" lẫn "chưa dùng
                 trong đề") phân trang thật, 10 câu/trang. Link đi qua $filterLink nên giữ nguyên mọi bộ lọc
                 đang bật (môn, khối, dạng câu, tỉnh, năm, từ khoá…). SỬA 8/10: thanh chuyển trang giờ hiện
                 ở cả khi chỉ có 1 trang (nút mờ) cho giống bản mẫu. --}}
            @if ($isQuestions)
                @php
                    $pgCurrent = $pagination['page'] ?? 1;
                    $pgLast = $pagination['lastPage'] ?? 1;
                    $pgTotalAll = $pagination['total'] ?? $total;
                    $pgFrom = $pgTotalAll > 0 ? ($pgCurrent - 1) * ($pagination['perPage'] ?? 10) + 1 : 0;
                    $pgTo = $pgTotalAll > 0 ? $pgFrom + count($rows) - 1 : 0;
                    // Cửa sổ số trang: luôn có trang đầu/cuối, ±1 quanh trang hiện tại, '…' cho khoảng bị bỏ.
                    $pgItems = [];
                    $pgPrev = 0;
                    for ($i = 1; $i <= $pgLast; $i++) {
                        if ($i === 1 || $i === $pgLast || abs($i - $pgCurrent) <= 1) {
                            if ($pgPrev && $i - $pgPrev > 1) {
                                $pgItems[] = '…';
                            }
                            $pgItems[] = $i;
                            $pgPrev = $i;
                        }
                    }
                @endphp
                <nav aria-label="Phân trang câu hỏi" class="akx-pg">
                    <span>{{ $pgFrom }}–{{ $pgTo }} / {{ $pgTotalAll }} câu hỏi{{ $pgLast > 1 ? ' · Trang '.$pgCurrent.'/'.$pgLast : '' }}</span>
                    <div class="akx-pg__btns">
                        @if ($pgCurrent > 1)
                            <a href="{{ $filterLink(['page' => $pgCurrent > 2 ? $pgCurrent - 1 : null]) }}" aria-label="Trang trước" class="akx-pgb"><x-lucide name="chevron-left" /></a>
                        @else
                            <span aria-hidden="true" class="akx-pgb is-off"><x-lucide name="chevron-left" /></span>
                        @endif

                        @foreach ($pgItems as $item)
                            @if ($item === '…')
                                <span class="akx-dots">…</span>
                            @elseif ($item === $pgCurrent)
                                <span aria-current="page" class="akx-pgb is-on">{{ $item }}</span>
                            @else
                                <a href="{{ $filterLink(['page' => $item > 1 ? $item : null]) }}" aria-label="Trang {{ $item }}" class="akx-pgb">{{ $item }}</a>
                            @endif
                        @endforeach

                        @if ($pgCurrent < $pgLast)
                            <a href="{{ $filterLink(['page' => $pgCurrent + 1]) }}" aria-label="Trang sau" class="akx-pgb"><x-lucide name="chevron-right" /></a>
                        @else
                            <span aria-hidden="true" class="akx-pgb is-off"><x-lucide name="chevron-right" /></span>
                        @endif
                    </div>
                </nav>
            @else
                <div class="akx-pg" data-pg-nav="rows" hidden></div>
            @endif
        </section>
    @endif

    @if ($isQuestions)
        {{-- SỬA 7/10 — style riêng (server không build Vite lại nên không dùng class Tailwind mới). --}}
        <style>
            .oi-ord-hint{margin:0;font-size:12px;line-height:1.5;color:#64748b}
            .oi-ord-hint strong{color:#334155}
            .oi-ord{display:inline-flex;align-items:center;gap:4px}
            .oi-ord__b{width:26px;height:28px;border:1px solid #cfe3f5;background:#fff;color:#334155;border-radius:8px;font-size:15px;line-height:1;font-weight:700;cursor:pointer;padding:0;transition:background .12s,border-color .12s,color .12s}
            .oi-ord__b:hover{background:#eff6ff;border-color:#93c5fd;color:#1d4ed8}
            .oi-ord__b:active{transform:translateY(1px)}
            .oi-ord__in{width:58px;height:28px;text-align:center;font-weight:700;font-size:13px;color:#0f172a;border:1px solid #cfe3f5;border-radius:8px;background:#fff;padding:0 4px;-moz-appearance:textfield;appearance:textfield}
            .oi-ord__in::-webkit-outer-spin-button,.oi-ord__in::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}
            .oi-ord__in:focus{outline:2px solid #bfdbfe;border-color:#3b82f6}
            .oi-ord__in.is-set{background:#eff6ff;border-color:#93c5fd;color:#1d4ed8}
            .oi-ord__top{height:auto;padding:0;border:0;background:transparent;color:#2563eb;border-radius:0;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;margin-left:0}
            .oi-ord__top::before{content:"\2191";margin-right:4px}
            .oi-ord__top:hover{background:transparent;text-decoration:underline}
            .oi-ord-flash{background:#ecfdf5 !important;transition:background 1.2s}
            .oi-ord__top[disabled]{background:transparent;color:#8aa0b6;cursor:default;text-decoration:none}
            .oi-ord__st{min-width:0;margin-right:6px;font-size:11px;font-weight:600;color:#64748b}
            .oi-ord__st.is-ok{color:#15803d}
            .oi-ord__st.is-err{color:#dc2626;white-space:normal;max-width:180px}
            .oi-ord.is-busy .oi-ord__in{opacity:.6}
            .oi-ord-toast{position:fixed;left:50%;bottom:22px;transform:translateX(-50%);z-index:60;display:none;align-items:center;gap:12px;background:#0f172a;color:#fff;border-radius:14px;padding:10px 14px 10px 16px;font-size:13px;box-shadow:0 10px 30px rgba(15,23,42,.3)}
            .oi-ord-toast.is-on{display:flex}
            .oi-ord-toast button{border:0;border-radius:9px;background:#3b82f6;color:#fff;font-weight:700;font-size:12px;padding:7px 12px;cursor:pointer}
            .oi-ord-toast button:hover{background:#2563eb}
        </style>
        <div class="oi-ord-toast" id="oiOrdToast" role="status">
            <span>Đã lưu thứ tự mới. Danh sách chưa xếp lại theo số mới.</span>
            <button type="button" id="oiOrdReload">Sắp xếp lại danh sách</button>
        </div>
    @endif
@if ($isQuestions)
    @push('scripts')
        <script>
            (function () {
                var MAX = 65535;
                var topMax = {{ (int) ($orderMax ?? 0) }};
                var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
                var toast = document.getElementById('oiOrdToast');
                var reload = document.getElementById('oiOrdReload');
                var dirty = false; // đã gõ/bấm ＋− làm thứ tự trên màn hình lệch với danh sách
                var pageNo = {{ (int) ($pagination['page'] ?? 1) }};
                if (reload) reload.addEventListener('click', function () { window.location.reload(); });

                function boxes() { return Array.prototype.slice.call(document.querySelectorAll('[data-ord]')); }
                function clamp(n) { return Math.max(0, Math.min(MAX, n)); }
                function parse(box) {
                    var raw = String(box.querySelector('.oi-ord__in').value).trim();
                    if (!/^\d+$/.test(raw)) return null;
                    return clamp(parseInt(raw, 10));
                }
                function status(box, text, cls) {
                    var st = box.querySelector('.oi-ord__st');
                    st.textContent = text || '';
                    st.className = 'oi-ord__st' + (cls ? ' ' + cls : '');
                }
                function setValue(box, v) {
                    box.dataset.saved = String(v);
                    box.querySelector('.oi-ord__in').value = v;
                }
                // Nút "Đưa lên trước": dòng đầu trang 1 → "Đang ở đầu"; nếu số trên màn hình đã lệch
                // với danh sách (dirty) thì khoá cho tới khi bấm "Sắp xếp lại danh sách".
                function refreshButtons() {
                    var all = boxes();
                    all.forEach(function (box, i) {
                        var v = parseInt(box.dataset.saved, 10) || 0;
                        var btn = box.querySelector('[data-act="up"]');
                        var atTop = i === 0 && pageNo === 1;
                        // Thứ tự chỉ đổi được trong cùng một dạng bài: câu đầu nhóm dạng thì không có câu "phía trên" cùng dạng.
                        var prevType = i > 0 ? all[i - 1].dataset.type : (btn.dataset.prevtype || '');
                        var groupTop = !atTop && !!prevType && prevType !== (box.dataset.type || '');
                        btn.disabled = atTop || groupTop || dirty;
                        btn.textContent = atTop || groupTop ? 'Đang ở đầu' : 'Đưa lên trước';
                        btn.title = atTop ? 'Câu này đang đứng đầu danh sách'
                            : (groupTop ? 'Câu này đang đứng đầu dạng bài của nó'
                            : (dirty ? 'Bấm "Sắp xếp lại danh sách" trước khi chuyển vị trí' : 'Đưa câu này lên đứng ngay trước câu phía trên nó'));
                        box.querySelector('.oi-ord__in').classList.toggle('is-set', v > 0);
                    });
                }
                function showToast(msg) {
                    if (!toast) return;
                    toast.querySelector('span').textContent = msg;
                    toast.classList.add('is-on');
                }
                function applySnapshot(j) {
                    topMax = parseInt(j.max, 10) || 0;
                    var vals = j.values || {};
                    boxes().forEach(function (box) {
                        var k = box.dataset.id;
                        if (vals[k] !== undefined) setValue(box, vals[k]);
                    });
                }

                function send(box, body) {
                    var seq = (box._seq = (box._seq || 0) + 1);
                    box.classList.add('is-busy');
                    status(box, 'Đang lưu…', '');
                    body.visible_ids = boxes().map(function (b) { return parseInt(b.dataset.id, 10); });
                    return fetch(box.dataset.href, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(body)
                    }).then(function (res) {
                        return res.json().catch(function () { return {}; }).then(function (j) { return { ok: res.ok, j: j }; });
                    }).then(function (r) {
                        if (seq !== box._seq) return null; // đã có lần lưu mới hơn
                        box.classList.remove('is-busy');
                        if (!r.ok || !r.j || r.j.ok !== true) {
                            var msg = (r.j && r.j.errors && ((r.j.errors.value && r.j.errors.value[0]) || (r.j.errors.before_id && r.j.errors.before_id[0])))
                                || (r.j && r.j.message) || 'Lưu lỗi';
                            setValue(box, box.dataset.saved);
                            status(box, msg, 'is-err');
                            return null;
                        }
                        applySnapshot(r.j);
                        status(box, 'Đã lưu ✓', 'is-ok');
                        setTimeout(function () { if (seq === box._seq) status(box, '', ''); }, 1800);
                        return r.j;
                    }).catch(function () {
                        if (seq !== box._seq) return null;
                        box.classList.remove('is-busy');
                        setValue(box, box.dataset.saved);
                        status(box, 'Mất kết nối', 'is-err');
                        return null;
                    });
                }

                function commit(box) {
                    var v = parse(box);
                    if (v === null) { setValue(box, box.dataset.saved); return; }
                    box.querySelector('.oi-ord__in').value = v;
                    if (String(v) === box.dataset.saved || String(v) === box._pend) return;
                    box._pend = String(v);
                    send(box, { mode: 'set', value: v }).then(function (j) {
                        box._pend = null;
                        if (j) { dirty = true; showToast('Đã lưu thứ tự mới. Danh sách chưa xếp lại theo số mới.'); refreshButtons(); }
                    });
                }

                function moveUp(box) {
                    var all = boxes();
                    var i = all.indexOf(box);
                    var prev = i > 0 ? all[i - 1] : null;
                    var beforeId = prev ? prev.dataset.id : box.querySelector('[data-act="up"]').dataset.prev;
                    if (!beforeId) return;
                    send(box, { mode: 'before', before_id: parseInt(beforeId, 10) }).then(function (j) {
                        if (!j) return;
                        var row = box.closest('tr');
                        if (prev) {
                            // Đổi chỗ ngay trên màn hình: dòng này chen lên trước dòng phía trên.
                            row.parentNode.insertBefore(row, prev.closest('tr'));
                            row.classList.add('oi-ord-flash');
                            setTimeout(function () { row.classList.remove('oi-ord-flash'); }, 1400);
                            refreshButtons();
                        } else {
                            // Câu phía trên nằm ở trang trước → tải lại để thấy vị trí mới.
                            window.location.reload();
                        }
                    });
                }

                document.addEventListener('click', function (e) {
                    var btn = e.target.closest('[data-ord] [data-act]');
                    if (!btn || btn.disabled) return;
                    var box = btn.closest('[data-ord]');
                    var input = box.querySelector('.oi-ord__in');
                    var act = btn.dataset.act;
                    if (act === 'up') { moveUp(box); return; }
                    var cur = parse(box);
                    if (cur === null) cur = parseInt(box.dataset.saved, 10) || 0;
                    input.value = clamp(cur + (act === 'inc' ? 1 : -1));
                    // Bấm liên tiếp thì gộp lại: chỉ lưu sau khi dừng bấm 450ms.
                    clearTimeout(box._t);
                    status(box, '…', '');
                    box._t = setTimeout(function () { commit(box); }, 450);
                });
                document.addEventListener('keydown', function (e) {
                    if (!e.target.classList || !e.target.classList.contains('oi-ord__in')) return;
                    var box = e.target.closest('[data-ord]');
                    if (e.key === 'Enter') { e.preventDefault(); clearTimeout(box._t); commit(box); e.target.blur(); }
                    else if (e.key === 'Escape') { clearTimeout(box._t); setValue(box, box.dataset.saved); status(box, '', ''); e.target.blur(); }
                    else if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
                        e.preventDefault();
                        var cur = parse(box); if (cur === null) cur = parseInt(box.dataset.saved, 10) || 0;
                        e.target.value = clamp(cur + (e.key === 'ArrowUp' ? 1 : -1));
                        clearTimeout(box._t);
                        box._t = setTimeout(function () { commit(box); }, 450);
                    }
                });
                document.addEventListener('focusout', function (e) {
                    if (!e.target.classList || !e.target.classList.contains('oi-ord__in')) return;
                    var box = e.target.closest('[data-ord]');
                    clearTimeout(box._t);
                    commit(box);
                });
                refreshButtons();
            })();
        </script>
    @endpush
@endif
@endsection
