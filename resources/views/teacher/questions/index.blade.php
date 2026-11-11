@extends('layouts.teacher')

{{-- SỬA 7/10 (khách: "Kho câu hỏi của tôi đổi lại thành Kho bài tập / câu hỏi và đề và xây như
     admin, chỉ khác là giáo viên không xem được Tag/Chuyên đề") — dựng theo khung
     admin/content/index.blade.php: banner, các TAB (đã dùng / chưa dùng trong đề / Đề-bộ bài /
     Kho chung), chip Môn, tab Dạng câu, bộ lọc Khối-Tỉnh thành-Năm-Tìm kiếm rồi tới bảng.
     KHÔNG có tab Tag/Chuyên đề và không có ô lọc Chuyên đề — phần đó dành cho Admin.

     SỬA 8/10 — đổi sang phong cách của source mới như bên Admin (dùng chung partials.admin-content-ui).

     SỬA 6/11 (khách: "bên giáo viên UI cũng như admin, tối ưu luôn, giáo viên nào chỉ xem của giáo viên đó") — bảng y hệt admin
     (bỏ icon emoji trước tên, tên câu bấm vào là mở Sửa, dòng "Sắp xếp: ưu tiên cao trước" + chú thích nút đổi chỗ), PHÂN TRANG
     MÁY CHỦ 10 dòng/trang cho cả câu hỏi lẫn đề (trước: nạp 500 dòng rồi chia trên trình duyệt → chậm). Tab Đề thi: MỌI đề của
     chính giáo viên + ô tìm như admin. Nút theo quyền giáo viên: Sửa / Phát hành / Lưu trữ (không có Xoá như admin). --}}
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
        // SỬA 11/11 — danh sách chuyên đề cho ô lọc mới (Teacher\QuestionService::listForTeacher()).
        $tagOptions = $tagOptions ?? [];
        $pagination = $pagination ?? ['page' => 1, 'perPage' => 10, 'lastPage' => 1, 'total' => $isAssessments ? count($papers) : $total];
        $pgPage = (int) ($pagination['page'] ?? 1);

        $hasActiveFilter = collect($filters)->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();

        // Link chip/tab/trang GIỮ NGUYÊN các bộ lọc đang bật (cùng cách làm bên admin). Đổi bộ lọc thì về trang 1.
        $filterLink = function (array $override = []) use ($filters, $tab) {
            $query = array_merge([
                'tab' => $tab,
                'subject' => $filters['subject'] ?? null,
                'grade' => $filters['grade'] ?? null,
                'type' => $filters['type'] ?? null,
                'province' => $filters['province'] ?? null,
                'exam_year' => $filters['exam_year'] ?? null,
                'io' => $filters['io'] ?? null,
                'tag' => $filters['tag'] ?? null, // SỬA 11/11 — giữ chuyên đề đang lọc khi bấm chip Môn / tab Dạng câu
                'q' => $filters['q'] ?? null,
                'page' => null,
            ], $override);

            return route('teacher.questions.index', array_filter($query, fn ($v) => $v !== null && $v !== ''));
        };
    @endphp

    @include('partials.admin-content-ui')
    <style>.akx-title--static:hover{color:#1e3a5f;text-decoration:none}</style>

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

    <x-ws.page-header title="Kho bài tập / câu hỏi và đề" icon="library" eyebrow="Giáo viên · Biên soạn" subtitle="Kho riêng — chỉ bạn tạo, sửa và sử dụng.">
        <x-slot:actions>
            @if ($isAssessments)
                {{-- SỬA 6/11 (khách: "giống admin") — dùng đúng màn "Tạo đề/bộ bài" của quản trị (đề vào kho riêng của giáo viên, xem TeacherContentScope). --}}
                <a href="{{ route('admin.content.assessments.create') }}" class="inline-flex min-h-10 shrink-0 items-center justify-center gap-1.5 rounded-xl bg-white px-4 py-2 text-xs font-bold text-blue-700 shadow-sm transition-colors hover:bg-sky-50">+ Tạo đề/bộ bài</a>
            @else
                {{-- SỬA 6/11 (khách: "bỏ đi") — bỏ nút "+ Nhập đề (Word/PDF/OCR)"; route teacher.assessments.import vẫn giữ. --}}
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
    @elseif (session('status') === 'assessment-deleted')
        @include('partials.toast-flash', ['type' => 'success', 'message' => session('statusMessage', 'Đã xoá đề.')])
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

            {{-- Dạng câu: chỉ Lập trình và Phổ thông (SỬA 3/11). --}}
            <div class="akx-pad">
                <div class="akx-chips">
                    @php
                        $akxCoding = (int) ($typeCounts['coding'] ?? 0);
                        $akxGsTotal = (int) ($generalSetCount ?? 0); // tổng bài phổ thông (không phụ thuộc ô tìm)
                        $akxGeneral = array_sum($typeCounts) - $akxCoding + $akxGsTotal;
                    @endphp
                    <a href="{{ $filterLink(['type' => null]) }}" class="akx-chip akx-chip--type {{ ! ($filters['type'] ?? null) ? 'is-on' : '' }}">Tất cả dạng <small>({{ array_sum($typeCounts) + $akxGsTotal }})</small></a>
                    <a href="{{ $filterLink(['type' => 'coding']) }}" class="akx-chip akx-chip--type {{ ($filters['type'] ?? null) === 'coding' ? 'is-on' : '' }}">{{ $questionTypeOptions['coding'] ?? 'Lập trình' }} <small>({{ $akxCoding }})</small></a>
                    <a href="{{ $filterLink(['type' => 'general']) }}" class="akx-chip akx-chip--type {{ ($filters['type'] ?? null) === 'general' ? 'is-on' : '' }}">Phổ thông <small>({{ $akxGeneral }})</small></a>
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
                    {{-- SỬA 11/11 (khách: "Bài chung trong admin và giáo viên cho thêm lọc theo tag chuyên đề nữa, thêm 1 cái lọc")
                         — ô lọc Chuyên đề (tag) quay lại hàng bộ lọc. Lọc chạy ở QuestionRepository::applyQuestionBankFilters()
                         bằng whereHas trên quan hệ nhiều-nhiều question_tag (KHÔNG join tay, tránh nhân đôi dòng khi 1 câu mang
                         nhiều tag); 'none' = câu CHƯA gắn chuyên đề nào, để dò ra mà gắn dần. --}}
                    <div class="min-w-[160px]">
                        <label class="akx-lbl" for="filter-tag">Chuyên đề</label>
                        <x-ws.select id="filter-tag" name="tag">
                            <option value="">Tất cả chuyên đề</option>
                            @foreach ($tagOptions as $tagId => $tagName)
                                <option value="{{ $tagId }}" @selected((string) ($filters['tag'] ?? '') === (string) $tagId)>{{ $tagName }}</option>
                            @endforeach
                            <option value="none" @selected(($filters['tag'] ?? null) === 'none')>Chưa gắn chuyên đề</option>
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

        {{-- SỬA 3/11 — tab "Bài chung": "Bài phổ thông" của giáo viên (1 bài nhiều câu) hiện 1 dòng / bài, chỉ khi lọc Phổ thông.
             SỬA 6/11 — như admin: chỉ ở trang 1 (danh sách câu lẻ đã phân trang máy chủ). --}}
        @if ($tab === 'unused' && ($filters['type'] ?? null) === 'general' && $pgPage <= 1)
            @include('partials.general-set-bank', ['gsbMode' => 'teacher', 'gsbRows' => app(\App\Services\GeneralSetBankService::class)->rows((int) auth()->id(), request('q'), $filters['tag'] ?? null)])
        @endif
        {{-- SỬA 3/11 (khách: "lọc Phổ thông thì bỏ bảng này đi") — chỉ còn khối Bài phổ thông; nếu có câu trắc nghiệm lẻ thì bảng vẫn hiện. --}}
        @if (! (($filters['type'] ?? null) === 'general' && (int) $total === 0))
        <section class="acx-panel" style="margin-top:16px" aria-label="Danh sách câu hỏi">
            <div class="akx-sub">
                <span><b>{{ $total }}</b> kết quả{{ $activeTabLabel ? ' · '.$activeTabLabel : '' }}</span>
                <span>Sắp xếp: ưu tiên cao trước</span>
            </div>
            @unless ($isShared)
                <p class="oi-ord-hint akx-hint">Nút <strong>Đưa lên trước</strong> / <strong>Đưa xuống dưới</strong> (cột cuối) đổi chỗ câu với câu liền trên / liền dưới trong cùng dạng bài — hệ thống tự lưu.</p>
            @endunless

            <div class="acx-scroll">
                <table class="acx-table apx-up akx-table is-q" style="min-width:1040px"
                       data-lead-prev="{{ $leadPrevId ?? '' }}" data-lead-prevtype="{{ $leadPrevType ?? '' }}"
                       data-lead-next="{{ $leadNextId ?? '' }}" data-lead-nexttype="{{ $leadNextType ?? '' }}">
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
                            <tr data-qid="{{ $q['id'] }}" data-qtype="{{ $q['typeValue'] ?? '' }}" @if ($q['readOnly'] ?? false) data-ro="1" @endif>
                                <td>
                                    @if ($q['readOnly'] ?? false)
                                        <span class="akx-title akx-title--static">{{ $q['title'] }}</span>
                                    @else
                                        <a href="{{ route('teacher.questions.edit', $q['id']) }}" class="akx-title">{{ $q['title'] }}</a>
                                    @endif
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
                                            <span class="akx-mute" style="font-size:12px;text-align:right">Thuộc Kho chung — chỉ Admin/Editor sửa được.</span>
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
            @include('teacher.questions._pager', ['pgCount' => count($questions), 'pgUnit' => 'câu hỏi'])
        </section>
        @endif{{-- /SỬA 3/11 ẩn bảng câu lẻ khi lọc Phổ thông --}}
    @else
        {{-- Tab Đề thi — SỬA 6/11 (khách: "giống admin") — y hệt tab Đề thi của quản trị: ô tìm, bảng Nội dung/Nguồn · Phân loại ·
             Trạng thái · Thao tác (Sửa / Xem / Xoá dùng thẳng màn của quản trị, chỉ trên đề của chính giáo viên). 10 đề/trang. --}}
        <section class="acx-panel" style="margin-top:16px" aria-label="Tìm đề thi">
            <div class="akx-pad">
                <form method="GET" action="{{ route('teacher.questions.index') }}" class="akx-form">
                    <input type="hidden" name="tab" value="assessments">
                    <div class="akx-grow">
                        <label class="akx-lbl" for="filter-assess-q">Tìm theo tên đề thi</label>
                        <div class="acx-search">
                            <x-lucide name="search" />
                            <input id="filter-assess-q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" maxlength="100"
                                   placeholder="Ví dụ: Hưng Yên, HSG, khảo sát tin 9…" class="admin-input">
                        </div>
                    </div>
                    <button type="submit" class="acx-btn acx-btn--primary">Tìm</button>
                    @if (($filters['q'] ?? '') !== '')
                        <a href="{{ route('teacher.questions.index', ['tab' => 'assessments']) }}" class="acx-btn">Xoá tìm</a>
                    @endif
                </form>
            </div>
        </section>

        <section class="acx-panel" style="margin-top:16px" aria-label="Danh sách nội dung">
            <div class="akx-sub">
                <span><b>{{ $pagination['total'] ?? count($papers) }}</b> kết quả{{ $activeTabLabel ? ' · '.$activeTabLabel : '' }}</span>
            </div>
            <div class="acx-scroll">
                <table class="acx-table apx-up akx-table">
                    <caption class="acx-sr">Danh sách nội dung</caption>
                    <thead>
                        <tr>
                            <th scope="col">Nội dung / Nguồn</th>
                            <th scope="col">Phân loại</th>
                            <th scope="col">Trạng thái</th>
                            <th scope="col">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($papers as $p)
                            <tr>
                                <td>
                                    <a href="{{ $p['showHref'] }}" class="akx-title">{{ $p['title'] }}</a>
                                    @if (($p['variantCount'] ?? 0) > 1)
                                        <a href="{{ $p['showHref'] }}#exam-variant-box"
                                           style="display:inline-flex;align-items:center;gap:4px;margin-left:6px;padding:2px 9px;border-radius:999px;background:#E0F2FE;color:#0369A1;font-size:11.5px;font-weight:800;text-decoration:none;vertical-align:middle"
                                           title="Xem / sửa các mã đề">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="8" y="8" width="13" height="13" rx="2"/><path d="M4 16V5a1 1 0 0 1 1-1h11"/></svg>{{ $p['variantCount'] }} mã đề
                                        </a>
                                    @elseif (! empty($p['variantOf']))
                                        <span style="display:inline-flex;margin-left:6px;padding:2px 9px;border-radius:999px;background:#F1F5F9;color:#475569;font-size:11.5px;font-weight:700;vertical-align:middle">Mã {{ $p['variantCode'] ?: 'con' }} của nhóm</span>
                                    @endif
                                    <div class="akx-src">Nguồn: {{ $p['owner'] }}</div>
                                </td>
                                <td><div class="akx-k">{{ $p['type'] }}</div></td>
                                <td><span class="acx-badge {{ $badgeOf($p['tone'] ?? null) }}">{{ $p['status'] }}</span></td>
                                <td>
                                    <div class="akx-act">
                                        <div class="akx-act__links">
                                            <a href="{{ $p['editHref'] }}" class="akx-lnk">Sửa</a>
                                            <a href="{{ $p['showHref'] }}" class="akx-lnk akx-lnk--mute">Xem</a>
                                            <form method="POST" action="{{ $p['deleteHref'] }}" class="inline" onsubmit="return confirm(@js($p['deleteLabel']));">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="akx-lnk akx-lnk--del"><x-lucide name="trash-2" /> Xoá</button>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if (count($papers) === 0)
                    <div class="akx-empty">
                        @if (($filters['q'] ?? '') !== '')
                            <strong>Không có đề nào khớp "{{ $filters['q'] }}"</strong>
                            Thử từ khoá ngắn hơn hoặc bấm "Xoá tìm".
                        @else
                            <strong>Chưa có dữ liệu.</strong>
                            Bấm "+ Tạo đề/bộ bài" để thêm đề đầu tiên.
                        @endif
                    </div>
                @endif
            </div>
            @include('teacher.questions._pager', ['pgCount' => count($papers), 'pgUnit' => 'đề'])
        </section>
    @endif

@push('scripts')
<style>
    .oi-ord-hint{margin:0;font-size:12px;line-height:1.5;color:#64748b}
    .oi-ord-hint strong{color:#334155}
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
        // SỬA 6/11 — phân trang máy chủ: dòng đầu / cuối trang đổi chỗ với câu ở trang trước / trang sau (id do máy chủ gửi kèm bảng).
        function neighbor(tr, dir) {
            var all = rows(), i = all.indexOf(tr), el = dir === 'up' ? all[i - 1] : all[i + 1];
            if (el) return { id: el.dataset.qid, type: el.dataset.qtype, el: el };
            var tb = tr.closest('table'); if (!tb) return null;
            var id = dir === 'up' ? tb.dataset.leadPrev : tb.dataset.leadNext;
            var ty = dir === 'up' ? tb.dataset.leadPrevtype : tb.dataset.leadNexttype;
            return id ? { id: id, type: ty, el: null } : null;
        }
        function refresh() {
            rows().forEach(function (tr) {
                var up = tr.querySelector('[data-tmove="up"]'), dn = tr.querySelector('[data-tmove="down"]');
                if (!up || !dn) return;
                var p = neighbor(tr, 'up'), n = neighbor(tr, 'down'), t = tr.dataset.qtype;
                var noUp = !p || p.type !== t, noDn = !n || n.type !== t;
                up.disabled = noUp; dn.disabled = noDn;
                up.textContent = noUp ? 'Đang ở đầu' : 'Đưa lên trước';
                dn.textContent = noDn ? 'Đang ở cuối' : 'Đưa xuống dưới';
            });
        }
        document.addEventListener('click', function (e) {
            var b = e.target.closest ? e.target.closest('[data-tmove]') : null;
            if (!b || b.disabled) return;
            var tr = b.closest('tr'), dir = b.dataset.tmove, nb = neighbor(tr, dir);
            if (!nb || nb.type !== tr.dataset.qtype) return;
            b.disabled = true;
            fetch(moveUrl.replace('__ID__', tr.dataset.qid), {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                body: JSON.stringify({ dir: dir, neighbor_id: parseInt(nb.id, 10) })
            }).then(function (res) { return res.json().catch(function () { return {}; }).then(function (j) { return { ok: res.ok, j: j }; }); })
              .then(function (r) {
                  if (!r.ok) { alert((r.j && (r.j.message || (r.j.errors && Object.values(r.j.errors)[0][0]))) || 'Không đổi được thứ tự.'); refresh(); return; }
                  if (!nb.el) { window.location.reload(); return; } // câu bên kia nằm ở trang khác → tải lại trang này
                  if (dir === 'up') nb.el.parentNode.insertBefore(tr, nb.el); else nb.el.parentNode.insertBefore(tr, nb.el.nextSibling);
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
