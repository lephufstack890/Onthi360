@extends('layouts.teacher')

{{-- SỬA 7/10 (khách: "Kho câu hỏi của tôi đổi lại thành Kho bài tập / câu hỏi và đề và xây như
     admin, chỉ khác là giáo viên không xem được Tag/Chuyên đề") — dựng theo khung
     admin/content/index.blade.php: banner, các TAB (đã dùng / chưa dùng trong đề / Đề-bộ bài /
     Kho chung), chip Môn, tab Dạng câu, bộ lọc Khối-Tỉnh thành-Năm-Tìm kiếm rồi tới bảng.
     KHÔNG có tab Tag/Chuyên đề và không có ô lọc Chuyên đề — phần đó dành cho Admin. --}}
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
                'q' => $filters['q'] ?? null,
            ], $override);

            return route('teacher.questions.index', array_filter($query, fn ($v) => $v !== null && $v !== ''));
        };
    @endphp

    <x-ws.page-header title="Kho bài tập / câu hỏi và đề" icon="library" subtitle="Kho riêng chỉ bạn tạo/sửa/sử dụng — xem thêm Kho chung ở tab riêng (không sửa được).">
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

    <x-ws.tabs :tabs="$tabs" />

    @if (! $isAssessments)
        <div class="bg-white rounded-3xl border border-sky-100 p-4 mb-4 space-y-3">
            {{-- Hàng chip Môn: nhìn phát biết kho đang có bao nhiêu câu mỗi môn, bấm 1 phát lọc luôn. --}}
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
                    <a href="{{ $filterLink(['subject' => 'none']) }}"
                       class="px-3 py-1.5 rounded-full border text-xs font-medium transition {{ ($filters['subject'] ?? null) === 'none' ? 'bg-amber-500 border-amber-500 text-white' : 'border-amber-200 bg-amber-50 text-amber-700 hover:border-amber-400' }}">
                        Chưa phân loại <span class="opacity-70">({{ $subjectCounts[''] ?? 0 }})</span>
                    </a>
                @endif
            </div>

            {{-- Dạng câu là dải TAB kèm số câu từng dạng, như admin. --}}
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

            <form method="GET" action="{{ route('teacher.questions.index') }}" class="flex flex-wrap items-end gap-3 pt-3 border-t border-slate-100">
                {{-- Giữ tab đang đứng + Môn/Dạng (chọn bằng chip/tab phía trên) khi bấm "Lọc". --}}
                <input type="hidden" name="tab" value="{{ $tab }}">
                <input type="hidden" name="subject" value="{{ $filters['subject'] ?? '' }}">
                <input type="hidden" name="type" value="{{ $filters['type'] ?? '' }}">
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
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs font-medium text-slate-500 mb-1" for="filter-q">Tìm theo tên hoặc mã</label>
                    <input id="filter-q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" maxlength="100"
                           placeholder="Ví dụ: ước chung, TOAN6…" class="admin-input">
                </div>
                <button type="submit" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700 shrink-0">Lọc</button>
                @if ($hasActiveFilter)
                    <a href="{{ route('teacher.questions.index', ['tab' => $tab]) }}" class="px-4 py-2.5 rounded-xl border border-sky-100 text-slate-600 text-[13px] font-medium shrink-0 hover:border-blue-200 hover:text-blue-600 transition">Xoá lọc</a>
                @endif
            </form>
        </div>

        <x-ws.table :columns="['Tên câu hỏi', 'Môn', 'Khối', 'Tỉnh thành', 'Năm', 'Loại', 'Độ khó', 'Chủ sở hữu', 'Trạng thái', '']">
            @forelse ($questions as $q)
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 font-medium text-slate-700">
                        <span class="mr-2">{{ $typeIcons[$q['typeValue'] ?? ''] ?? '❓' }}</span>{{ $q['title'] }}
                        @if (! empty($q['code']))
                            <div class="text-xs font-normal text-slate-400">{{ $q['code'] }}</div>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @if (($q['subject'] ?? '') === 'Chưa phân loại')
                            <span class="text-xs px-2 py-1 rounded-full bg-amber-50 text-amber-700 border border-amber-200">Chưa phân loại</span>
                        @else
                            <span class="text-slate-600">{{ $q['subject'] }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-slate-500 whitespace-nowrap">{{ $q['grade'] }}</td>
                    <td class="px-4 py-3 text-slate-500 whitespace-nowrap">{{ $q['province'] ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-500 whitespace-nowrap">{{ $q['examYear'] ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ $q['type'] }}</td>
                    {{-- Chưa đặt thì hiện mờ + chú thích: giá trị đang được SUY theo điểm câu hỏi. --}}
                    <td class="px-4 py-3 whitespace-nowrap">
                        @if ($q['difficultySet'] ?? false)
                            <span class="text-slate-600">{{ $q['difficulty'] }}</span>
                        @else
                            <span class="text-slate-400" title="Chưa đặt — hệ thống tự suy theo điểm câu hỏi">{{ $q['difficulty'] }} <span class="text-[11px]">(tự suy)</span></span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-slate-500 whitespace-nowrap">{{ $q['owner'] }}</td>
                    <td class="px-4 py-3"><x-ws.badge :tone="$q['tone']">{{ $q['status'] }}</x-ws.badge></td>
                    <td class="px-4 py-3 text-right space-x-3">
                        @if ($q['readOnly'] ?? false)
                            <span class="text-xs text-slate-400">Thuộc Kho chung — chỉ Admin/Editor sửa được (6.5).</span>
                        @else
                            <a href="{{ route('teacher.questions.edit', $q['id']) }}" class="text-blue-600 font-medium">Sửa</a>
                            @if ($q['canPublish'])
                                <form method="POST" action="{{ route('teacher.questions.publish', $q['id']) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="text-emerald-600 font-medium">Phát hành</button>
                                </form>
                            @endif
                            @if ($q['canArchive'])
                                <form method="POST" action="{{ route('teacher.questions.archive', $q['id']) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="text-slate-400 font-medium">Lưu trữ</button>
                                </form>
                            @endif
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="px-4 py-6 text-center text-slate-400">
                    {{ $hasActiveFilter ? 'Không có câu hỏi nào khớp bộ lọc — thử bỏ bớt điều kiện hoặc bấm "Xoá lọc".' : ($isShared ? 'Kho chung chưa có câu hỏi nào.' : ($tab === 'unused' ? 'Chưa có câu hỏi nào ngoài đề.' : 'Chưa có câu hỏi nào nằm trong đề.')) }}
                </td></tr>
            @endforelse
        </x-ws.table>
        <x-ws.pagination-note :shown="count($questions)" :total="$total" />
    @else
        {{-- Tab Đề/bộ bài — thay cho trang "Đề PDF của tôi" cũ (đề PDF riêng tư của giáo viên, chờ Admin duyệt đưa ra kho chung). --}}
        <x-ws.table :columns="['Tên đề', 'Loại', 'Mã đề', 'PDF', 'Câu/đáp án', 'Trạng thái', '']">
            @forelse ($papers as $p)
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 font-medium text-slate-700">{{ $p['title'] }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ $p['type'] }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ $p['examCode'] ?: '—' }}</td>
                    <td class="px-4 py-3">
                        <span class="text-xs font-medium {{ $p['hasPdf'] ? 'text-emerald-600' : 'text-amber-600' }}">{{ $p['hasPdf'] ? 'Đã tải' : 'Chưa tải' }}</span>
                    </td>
                    <td class="px-4 py-3 text-slate-500">{{ $p['answerKeysCount'] }} câu · {{ $p['codingItemsCount'] }} bài code</td>
                    <td class="px-4 py-3"><x-ws.badge :tone="$p['tone']">{{ $p['status'] }}</x-ws.badge></td>
                    <td class="px-4 py-3 text-right space-x-3 whitespace-nowrap">
                        <a href="{{ route('teacher.papers.pdf.edit', $p['id']) }}" class="text-blue-600 font-medium">Quản lý đề PDF</a>
                        @if ($p['canPublish'])
                            <form method="POST" action="{{ route('teacher.assessments.publish', $p['id']) }}" class="inline">
                                @csrf
                                <button type="submit" class="text-emerald-600 font-medium">Phát hành</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-6 text-center text-slate-400">Chưa có đề nào — bấm "+ Tạo đề PDF mới" để bắt đầu.</td></tr>
            @endforelse
        </x-ws.table>
    @endif
@endsection
