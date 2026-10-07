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
    @endphp

    <x-ws.page-header title="Kho bài tập / câu hỏi và đề" icon="library" subtitle="Quản lý câu hỏi và đề — sửa là cập nhật trực tiếp.">
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

    <x-ws.tabs :tabs="$tabs" />

    @if ($isQuestions)
        <div class="bg-white rounded-3xl border border-sky-100 p-4 mb-4 space-y-3">
            {{-- Hàng chip: nhìn phát biết kho đang có bao nhiêu câu mỗi môn, bấm 1 phát lọc luôn. --}}
            <div class="flex flex-wrap gap-2">
                <a href="{{ $filterLink(['subject' => null]) }}"
                   class="px-3 py-1.5 rounded-full border text-xs font-medium transition {{ ! ($filters['subject'] ?? null) ? 'border-blue-600 bg-blue-600 text-white' : 'border-sky-100 text-slate-600 hover:border-blue-200 hover:text-blue-600' }}">
                    Tất cả môn
                </a>
                @foreach ($subjectOptions as $code => $label)
                    @php $count = $subjectCounts[$code] ?? 0; @endphp
                    @if ($count > 0 || ($filters['subject'] ?? null) === $code)
                        <a href="{{ $filterLink(['subject' => $code]) }}"
                           class="px-3 py-1.5 rounded-full border text-xs font-medium transition {{ ($filters['subject'] ?? null) === $code ? 'border-blue-600 bg-blue-600 text-white' : 'border-sky-100 text-slate-600 hover:border-blue-200 hover:text-blue-600' }}">
                            {{ $label }} <span class="opacity-70">({{ $count }})</span>
                        </a>
                    @endif
                @endforeach
                @if (($subjectCounts[''] ?? 0) > 0 || ($filters['subject'] ?? null) === 'none')
                    {{-- Nhóm "Chưa phân loại" (subject IS NULL) — chỗ để dọn dần câu cũ, xem lệnh
                         `php artisan questions:backfill-subject --all`. --}}
                    <a href="{{ $filterLink(['subject' => 'none']) }}"
                       class="px-3 py-1.5 rounded-full border text-xs font-medium transition {{ ($filters['subject'] ?? null) === 'none' ? 'bg-amber-500 border-amber-500 text-white' : 'border-amber-200 bg-amber-50 text-amber-700 hover:border-amber-400' }}">
                        Chưa phân loại <span class="opacity-70">({{ $subjectCounts[''] ?? 0 }})</span>
                    </a>
                @endif
            </div>

            {{-- SỬA 30/9 (khách: "dạng câu ở dưới làm tab phân chia dạng câu") — dạng câu giờ là
                 TAB, không còn là 1 ô chọn trong hàng bộ lọc. Mỗi tab in luôn số câu của dạng đó
                 để nhìn phát biết kho đang nặng dạng nào. --}}
            <div class="flex flex-wrap gap-2 border-t border-slate-100 pt-3">
                <a href="{{ $filterLink(['type' => null]) }}"
                   class="px-3.5 py-2 rounded-xl border text-xs font-bold transition {{ ! ($filters['type'] ?? null) ? 'border-blue-600 bg-blue-600 text-white' : 'border-sky-100 bg-white text-slate-600 hover:border-blue-200 hover:text-blue-600' }}">
                    Tất cả dạng <span class="opacity-70">({{ array_sum($typeCounts) }})</span>
                </a>
                @foreach ($questionTypeOptions as $value => $label)
                    <a href="{{ $filterLink(['type' => $value]) }}"
                       class="px-3.5 py-2 rounded-xl border text-xs font-bold transition {{ ($filters['type'] ?? null) === $value ? 'border-blue-600 bg-blue-600 text-white' : 'border-sky-100 bg-white text-slate-600 hover:border-blue-200 hover:text-blue-600' }}">
                        {{ $label }} <span class="opacity-70">({{ $typeCounts[$value] ?? 0 }})</span>
                    </a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('admin.content.index') }}" class="flex flex-wrap items-end gap-3 pt-3 border-t border-slate-100">
                <input type="hidden" name="tab" value="questions">
                {{-- SỬA 7/10 (khách: bỏ ô Môn học / Trạng thái / Độ khó / Chuyên đề / Dùng trong đề) —
                     Môn vẫn lọc bằng hàng chip phía trên, nên gửi kèm giá trị đang chọn để bấm "Lọc" không làm mất. --}}
                <input type="hidden" name="subject" value="{{ $filters['subject'] ?? '' }}">
                <input type="hidden" name="in_exam" value="{{ $filters['in_exam'] ?? 'used' }}">
                <div class="min-w-[120px]">
                    <label class="block text-xs font-medium text-slate-500 mb-1" for="filter-grade">Khối lớp</label>
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
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs font-medium text-slate-500 mb-1" for="filter-q">Tìm theo tên hoặc mã</label>
                    <input id="filter-q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" maxlength="100"
                           placeholder="Ví dụ: ước chung, TOAN6…"
                           class="admin-input">
                </div>
                <button type="submit" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700 shrink-0">Lọc</button>
                @if ($hasActiveFilter)
                    <a href="{{ route('admin.content.index', ['tab' => 'questions', 'in_exam' => $filters['in_exam'] ?? 'used']) }}" class="px-4 py-2.5 rounded-xl border border-sky-100 text-slate-600 text-[13px] font-medium shrink-0 hover:border-blue-200 hover:text-blue-600 transition">Xoá lọc</a>
                @endif
            </form>
        </div>
    @endif

    @if ($tab === 'drafts')
        <div class="space-y-3">
            @forelse ($documents as $d)
                <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4">
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <x-ws.icon-tile emoji="📄" tone="sky" />
                            <div>
                                <p class="text-[13px] font-medium text-slate-700">{{ $d['name'] }}</p>
                                <p class="text-xs text-slate-400">Người tải lên: {{ $d['uploader'] }}</p>
                                <div class="w-48 mt-1"><x-ws.progress-bar :percent="$d['progress']" tone="{{ $d['tone'] === 'warning' ? 'warning' : ($d['tone'] === 'danger' ? 'danger' : 'info') }}" /></div>
                            </div>
                        </div>
                        <div class="text-right">
                            <x-ws.badge :tone="$d['tone']">{{ $d['status'] }}</x-ws.badge>
                            @if ($d['reviewable'])
                                <a href="{{ route('admin.content.questions.reviewDraft', ['document' => $d['id']]) }}" class="block mt-1 text-[13px] text-blue-600 font-medium">Rà soát ngay ›</a>
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
    @elseif ($tab === 'tags')
        {{-- SỬA 19/8 (Giai đoạn 6 — "Gắn tag/chủ đề cho câu hỏi"): CRUD gọn trong 1 khối,
             không cần trang riêng — xem ContentService::indexData()/tagStore()/tagUpdate()/
             tagDestroy(). Tag dùng để lọc ở màn "Luyện tập theo câu" của học sinh và ở form
             tạo/sửa câu hỏi (Admin + Giáo viên). --}}
        <div class="bg-white rounded-3xl border border-sky-100 p-5 mb-5">
            <h2 class="font-medium text-slate-700 mb-3">+ Thêm tag mới</h2>
            <form method="POST" action="{{ route('admin.content.tags.store') }}" class="flex flex-wrap items-center gap-3">
                @csrf
                <input type="text" name="name" required maxlength="120" placeholder="VD: Đại số, Hình học, Dao động cơ..."
                       class="flex-1 min-w-[220px] rounded-xl border border-sky-100 text-[13px] p-2.5">
                <button type="submit" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700">Thêm tag</button>
            </form>
        </div>

        <div class="bg-white rounded-3xl border border-sky-100 divide-y divide-slate-100">
            @forelse ($tags as $t)
                <div class="flex items-center justify-between gap-3 px-5 py-3" x-data="{ editing: false }">
                    <form method="POST" action="{{ route('admin.content.tags.update', $t['id']) }}" class="flex-1 flex items-center gap-2" x-show="editing" x-cloak>
                        @csrf
                        @method('PUT')
                        <input type="text" name="name" value="{{ $t['name'] }}" required maxlength="120" class="flex-1 rounded-xl border border-sky-100 text-[13px] p-2">
                        <button type="submit" class="text-[13px] text-blue-600 font-medium">Lưu</button>
                        <button type="button" @click="editing = false" class="text-[13px] text-slate-400">Huỷ</button>
                    </form>
                    <div class="flex-1 flex items-center gap-2" x-show="!editing">
                        <span class="text-[13px] font-medium text-slate-700">{{ $t['name'] }}</span>
                        <span class="text-xs text-slate-400">{{ $t['questionsCount'] }} câu hỏi đang dùng</span>
                    </div>
                    <div class="flex items-center gap-3 shrink-0" x-show="!editing">
                        <button type="button" @click="editing = true" class="text-[13px] text-slate-500 hover:text-blue-600">Đổi tên</button>
                        <form method="POST" action="{{ route('admin.content.tags.destroy', $t['id']) }}" class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-[13px] text-blue-500 hover:text-blue-700">Xoá</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="px-5 py-6 text-center text-slate-400 text-[13px]">Chưa có tag nào — thêm tag đầu tiên ở trên.</div>
            @endforelse
        </div>
    @else
        {{-- SỬA 8/9 (3) — tab Câu hỏi có thêm 2 cột Môn/Khối (và mã câu hỏi dưới tên) để nhìn
             bảng là biết ngay câu nào chưa phân loại; các tab khác giữ nguyên bộ cột cũ. --}}
        {{-- SỬA 1/10 — thêm 2 cột Tỉnh thành/Năm để nhìn bảng là kiểm chứng được ngay 2 ô lọc mới. --}}
        @if ($isQuestions)
            {{-- SỬA 7/10 (khách: "hiển thị cột thứ tự ra ngoài danh sách, sửa trực tiếp trên từng dòng")
                 — chú thích cách đọc cột Thứ tự. --}}
            <p class="oi-ord-hint">Cột <strong>Thứ tự hiển thị</strong>: số <strong>càng lớn</strong> thì câu hỏi càng <strong>đứng trước</strong> (0 = mặc định, câu mới nhất lên trước). Bấm <strong>＋ / −</strong>, gõ số rồi Enter, hoặc <strong>Đưa lên trước</strong> (chen lên đứng ngay trước câu phía trên nó) — hệ thống tự lưu.</p>
        @endif
        <x-ws.table :columns="$isQuestions ? ['Tên', 'Thứ tự hiển thị', 'Môn', 'Khối', 'Tỉnh thành', 'Năm', 'Loại', 'Độ khó', 'Chủ sở hữu', 'Trạng thái', ''] : ['Tên', 'Loại', 'Chủ sở hữu', 'Trạng thái', '']">
            @forelse ($rows as $r)
                <tr>
                    <td class="px-4 py-3 font-medium text-slate-700">
                        {{ $r['title'] }}
                        @if ($isQuestions && ! empty($r['code']))
                            <div class="text-xs font-normal text-slate-400">{{ $r['code'] }}</div>
                        @endif
                    </td>
                    @if ($isQuestions)
                        {{-- SỬA 7/10 — ô Thứ tự: −/＋ tăng giảm 1, gõ số, hoặc "Đưa lên đầu"; tự lưu bằng
                             fetch PATCH (assets/JS ở @push('scripts') cuối trang). --}}
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="oi-ord" data-ord data-href="{{ $r['orderHref'] }}" data-saved="{{ $r['displayOrder'] }}" data-id="{{ $r['id'] }}">
                                <button type="button" class="oi-ord__b" data-act="dec" aria-label="Giảm 1" title="Giảm 1">−</button>
                                <input type="number" class="oi-ord__in" min="0" max="65535" step="1" inputmode="numeric" value="{{ $r['displayOrder'] }}" aria-label="Thứ tự hiển thị">
                                <button type="button" class="oi-ord__b" data-act="inc" aria-label="Tăng 1" title="Tăng 1 (đứng trước hơn)">＋</button>
                                <button type="button" class="oi-ord__top" data-act="up" @if ($loop->first) data-prev="{{ $leadPrevId ?? '' }}" @endif title="Đưa câu này lên đứng ngay trước câu phía trên nó">Đưa lên trước</button>
                                <span class="oi-ord__st" aria-live="polite"></span>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            @if (($r['subject'] ?? '') === 'Chưa phân loại')
                                <span class="text-xs px-2 py-1 rounded-full bg-amber-50 text-amber-700 border border-amber-200">Chưa phân loại</span>
                            @else
                                <span class="text-slate-600">{{ $r['subject'] }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-500 whitespace-nowrap">{{ $r['grade'] }}</td>
                        <td class="px-4 py-3 text-slate-500 whitespace-nowrap">{{ $r['province'] ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 whitespace-nowrap">{{ $r['examYear'] ?? '—' }}</td>
                    @endif
                    <td class="px-4 py-3 text-slate-500">{{ $r['type'] }}</td>
                    @if ($isQuestions)
                        {{-- Chưa đặt thì hiện mờ + chú thích: giá trị đang được SUY theo điểm câu
                             hỏi, chưa phải do người soạn chọn (xem App\Support\QuestionDifficulty). --}}
                        <td class="px-4 py-3 whitespace-nowrap">
                            @if ($r['difficultySet'] ?? false)
                                <span class="text-slate-600">{{ $r['difficulty'] }}</span>
                            @else
                                <span class="text-slate-400" title="Chưa đặt — hệ thống tự suy theo điểm câu hỏi">{{ $r['difficulty'] }} <span class="text-[11px]">(tự suy)</span></span>
                            @endif
                        </td>
                    @endif
                    <td class="px-4 py-3 text-slate-500">{{ $r['owner'] }}</td>
                    <td class="px-4 py-3"><x-ws.badge :tone="$r['tone']">{{ $r['status'] }}</x-ws.badge></td>
                    <td class="px-4 py-3 text-right space-x-3 whitespace-nowrap">
                        {{-- SỬA 30/9 (khách: "thêm nút sửa bên này nữa để người ta tiện sửa câu hỏi")
                             — vào thẳng màn Sửa, khỏi phải bấm "Xem" rồi tìm nút Sửa trong trang chi
                             tiết. Link do ContentService::indexData() dựng sẵn theo đúng loại nội dung
                             của từng dòng (câu hỏi / đề / học liệu). --}}
                        @if ($r['editHref'] ?? null)
                            <a href="{{ $r['editHref'] }}" class="text-blue-600 font-medium">Sửa</a>
                        @endif
                        {{-- SỬA 23/9 — kèm 'kind' để mở ĐÚNG loại nội dung, tránh trùng id giữa 3 bảng. --}}
                        <a href="{{ route('admin.content.show', ['content' => $r['id'], 'kind' => $r['kind'] ?? null]) }}" class="text-slate-500 font-medium hover:text-blue-600">Xem</a>
                        {{-- SỬA 19/8 (Giai đoạn 4): chỉ đề của giáo viên (tab "Đề/bộ bài") mới có nút
                             này — xem ContentService::indexData()/assessmentPromoteToShared(). --}}
                        @if ($r['canPromoteToShared'] ?? false)
                            <form method="POST" action="{{ route('admin.content.assessments.promoteShared', $r['id']) }}" class="inline">
                                @csrf
                                <button type="submit" class="text-emerald-600 font-medium">Duyệt vào kho chung</button>
                            </form>
                        @endif
                        {{-- SỬA 25/8 (7) — "thêm tính năng xóa cho admin": xoá THẬT, xoá luôn tệp
                             trên đĩa, không khôi phục được nên PHẢI xác nhận qua confirm().
                             SỬA 4/10 (khách: "phần danh sách bài không thấy nút xoá") — trước đây
                             chỉ tab Học liệu có nút này. Giờ cả 3 tab (Câu hỏi / Đề / Học liệu)
                             đều có, và địa chỉ lẫn câu hỏi xác nhận do indexData() dựng sẵn theo
                             đúng loại nội dung của từng dòng — view không tự đoán route nữa. --}}
                        @if ($r['canDelete'] ?? false)
                            <form method="POST" action="{{ $r['deleteHref'] }}" class="inline" onsubmit="return confirm('{{ $r['deleteLabel'] }}');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-rose-600 hover:text-rose-700 font-medium">Xoá</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ $isQuestions ? 9 : 5 }}" class="px-4 py-6 text-center text-slate-400">
                    {{ $isQuestions && $hasActiveFilter ? 'Không có câu hỏi nào khớp bộ lọc — thử bỏ bớt điều kiện hoặc bấm "Xoá lọc".' : 'Chưa có dữ liệu.' }}
                </td></tr>
            @endforelse
        </x-ws.table>
        {{-- SỬA 7/10 (khách: "quá 10 item thì phân trang") — tab Câu hỏi (cả "đã dùng" lẫn "chưa dùng
             trong đề") phân trang thật, 10 câu/trang. Chỉ hiện thanh chuyển trang khi có HƠN 1
             trang; ít hơn thì giữ ô ghi chú cũ. Link đi qua $filterLink nên giữ nguyên mọi bộ lọc
             đang bật (môn, khối, dạng câu, tỉnh, năm, từ khoá…). --}}
        @if ($isQuestions && ($pagination['lastPage'] ?? 1) > 1)
            @php
                $pgCurrent = $pagination['page'];
                $pgLast = $pagination['lastPage'];
                $pgFrom = ($pgCurrent - 1) * $pagination['perPage'] + 1;
                $pgTo = $pgFrom + count($rows) - 1;
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
                $pgBtn = 'inline-flex items-center justify-center rounded-lg border text-[12px] font-semibold transition-colors';
            @endphp
            <nav aria-label="Phân trang câu hỏi" class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-sky-100 bg-white px-4 py-3 text-[11px] text-slate-500 shadow-[0_2px_8px_rgba(0,90,180,.04)]">
                <span>Hiển thị <strong class="font-bold text-slate-700">{{ $pgFrom }}–{{ $pgTo }}</strong> / {{ $pagination['total'] }} câu hỏi · Trang {{ $pgCurrent }}/{{ $pgLast }}</span>
                <div class="flex flex-wrap items-center gap-1">
                    @if ($pgCurrent > 1)
                        <a href="{{ $filterLink(['page' => $pgCurrent > 2 ? $pgCurrent - 1 : null]) }}" aria-label="Trang trước"
                           class="{{ $pgBtn }} border-sky-100 bg-white text-slate-600 hover:border-blue-200 hover:text-blue-600" style="height:2rem;min-width:2rem;padding:0 .5rem">‹</a>
                    @else
                        <span aria-hidden="true" class="{{ $pgBtn }} border-sky-100 bg-white text-slate-300" style="height:2rem;min-width:2rem;padding:0 .5rem;cursor:not-allowed">‹</span>
                    @endif

                    @foreach ($pgItems as $item)
                        @if ($item === '…')
                            <span class="text-slate-400" style="min-width:1.25rem;text-align:center">…</span>
                        @elseif ($item === $pgCurrent)
                            <span aria-current="page" class="{{ $pgBtn }} border-blue-600 bg-blue-600 text-white" style="height:2rem;min-width:2rem;padding:0 .5rem">{{ $item }}</span>
                        @else
                            <a href="{{ $filterLink(['page' => $item > 1 ? $item : null]) }}" aria-label="Trang {{ $item }}"
                               class="{{ $pgBtn }} border-sky-100 bg-white text-slate-600 hover:border-blue-200 hover:text-blue-600" style="height:2rem;min-width:2rem;padding:0 .5rem">{{ $item }}</a>
                        @endif
                    @endforeach

                    @if ($pgCurrent < $pgLast)
                        <a href="{{ $filterLink(['page' => $pgCurrent + 1]) }}" aria-label="Trang sau"
                           class="{{ $pgBtn }} border-sky-100 bg-white text-slate-600 hover:border-blue-200 hover:text-blue-600" style="height:2rem;min-width:2rem;padding:0 .5rem">›</a>
                    @else
                        <span aria-hidden="true" class="{{ $pgBtn }} border-sky-100 bg-white text-slate-300" style="height:2rem;min-width:2rem;padding:0 .5rem;cursor:not-allowed">›</span>
                    @endif
                </div>
            </nav>
        @else
            <x-ws.pagination-note :shown="count($rows)" :total="$total" />
        @endif
    @endif

    @if ($isQuestions)
        {{-- SỬA 7/10 — style riêng (server không build Vite lại nên không dùng class Tailwind mới). --}}
        <style>
            .oi-ord-hint{margin:0 0 .6rem;font-size:12px;line-height:1.5;color:#64748b}
            .oi-ord-hint strong{color:#334155}
            .oi-ord{display:inline-flex;align-items:center;gap:4px}
            .oi-ord__b{width:26px;height:28px;border:1px solid #cfe3f5;background:#fff;color:#334155;border-radius:8px;font-size:15px;line-height:1;font-weight:700;cursor:pointer;padding:0;transition:background .12s,border-color .12s,color .12s}
            .oi-ord__b:hover{background:#eff6ff;border-color:#93c5fd;color:#1d4ed8}
            .oi-ord__b:active{transform:translateY(1px)}
            .oi-ord__in{width:58px;height:28px;text-align:center;font-weight:700;font-size:13px;color:#0f172a;border:1px solid #cfe3f5;border-radius:8px;background:#fff;padding:0 4px;-moz-appearance:textfield;appearance:textfield}
            .oi-ord__in::-webkit-outer-spin-button,.oi-ord__in::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}
            .oi-ord__in:focus{outline:2px solid #bfdbfe;border-color:#3b82f6}
            .oi-ord__in.is-set{background:#eff6ff;border-color:#93c5fd;color:#1d4ed8}
            .oi-ord__top{height:28px;padding:0 9px;border:1px solid #bbf7d0;background:#f0fdf4;color:#15803d;border-radius:8px;font-size:11px;font-weight:700;cursor:pointer;white-space:nowrap;margin-left:2px}
            .oi-ord__top:hover{background:#dcfce7;border-color:#4ade80}
            .oi-ord-flash{background:#ecfdf5 !important;transition:background 1.2s}
            .oi-ord__top[disabled]{background:#f1f5f9;border-color:#e2e8f0;color:#94a3b8;cursor:default}
            .oi-ord__st{min-width:62px;font-size:11px;font-weight:600;color:#64748b}
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
                    boxes().forEach(function (box, i) {
                        var v = parseInt(box.dataset.saved, 10) || 0;
                        var btn = box.querySelector('[data-act="up"]');
                        var atTop = i === 0 && pageNo === 1;
                        btn.disabled = atTop || dirty;
                        btn.textContent = atTop ? 'Đang ở đầu' : 'Đưa lên trước';
                        btn.title = atTop ? 'Câu này đang đứng đầu danh sách'
                            : (dirty ? 'Bấm "Sắp xếp lại danh sách" trước khi chuyển vị trí' : 'Đưa câu này lên đứng ngay trước câu phía trên nó');
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
