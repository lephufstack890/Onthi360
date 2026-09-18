@extends('layouts.teacher')

@section('title', 'Kho câu hỏi của tôi')
@section('page-title', 'Kho câu hỏi của tôi')

@section('content')
    @php
        $tab = $tab ?? 'all';
        $tabs = $tabs ?? [];
        $questions = $questions ?? [];
        $total = $total ?? count($questions);
        // SỬA 8/9 (4) — icon map theo MÃ loại câu ('mcq'/'fill_blank'/…), không theo nhãn tiếng
        // Việt như trước: nhãn cũ ở đây ('Điền đáp án', 'Lập trình') không khớp nhãn thật nên mọi
        // dòng đều rơi về icon '❓'. Xem App\Enums\QuestionType::label().
        $typeIcons = ['mcq' => '🔤', 'fill_blank' => '✏️', 'coding' => '💻', 'composite' => '🧩'];

        // SỬA 18/9 (khách: "kho câu hỏi của tôi bên giáo viên hiển thêm phần lọc cho đầy đủ như
        // admin. Vs lại chỗ giáo viên và admin thêm lọc theo độ khó nữa nha") — dữ liệu dựng
        // thanh lọc, do Teacher\QuestionService::listForTeacher() đổ ra (cùng bộ khoá với
        // admin/content/index.blade.php để 2 màn không lệch nhau).
        $filters = $filters ?? [];
        $subjectOptions = $subjectOptions ?? [];
        $gradeOptions = $gradeOptions ?? [];
        $questionTypeOptions = $questionTypeOptions ?? [];
        $statusOptions = $statusOptions ?? [];
        $difficultyOptions = $difficultyOptions ?? [];
        $subjectCounts = $subjectCounts ?? [];
        $isShared = $isShared ?? ($tab === 'shared');

        // Tab "Kho chung (chỉ xem)" phải giữ lại ?tab=shared trên mọi link/form lọc, nếu không
        // bấm Lọc một phát là nhảy ngược về kho riêng.
        $scopeParams = $isShared ? ['tab' => 'shared'] : [];

        // Trạng thái đã do TAB quy định (Đã phát hành/Nháp) — xem listForTeacher(): 2 thứ dùng
        // chung 1 giá trị nên chip Môn bên dưới phải mang theo, không thì bấm chip là mất tab.
        $carry = array_filter([
            'grade' => $filters['grade'] ?? null,
            'type' => $filters['type'] ?? null,
            'status' => $filters['status'] ?? null,
            'difficulty' => $filters['difficulty'] ?? null,
            'q' => $filters['q'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        $hasActiveFilter = collect($filters)->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
    @endphp

    <div class="rounded-3xl border border-sky-100 bg-gradient-to-br from-sky-50 via-white to-blue-50 shadow-[0_2px_8px_rgba(0,90,180,.04)] p-5 lg:p-6 mb-4 flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-start gap-4">
            <div class="w-14 h-14 rounded-3xl bg-white flex items-center justify-center text-3xl shrink-0 shadow-sm">❓</div>
            <div>
                <h1 class="text-xl lg:text-2xl font-semibold text-slate-800">Kho câu hỏi của tôi</h1>
                <p class="text-[13px] text-slate-500 mt-1">Kho riêng chỉ bạn tạo/sửa/sử dụng — có thể xem thêm Kho chung của hệ thống ở tab riêng, nhưng không sửa được (6.5).</p>
            </div>
        </div>
        <div class="flex items-center gap-3 shrink-0">
            <a href="{{ route('teacher.assessments.import') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-sky-100 bg-white px-4 py-2 text-xs font-bold text-slate-700 transition-colors hover:border-sky-200 hover:bg-sky-50">+ Nhập đề (Word/PDF/OCR)</a>
            <a href="{{ route('teacher.questions.create') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700">+ Tạo câu hỏi</a>
        </div>
    </div>

    <x-ws.tabs :tabs="$tabs" />

    {{-- ══════ BỘ LỌC — dựng đúng như tab "Câu hỏi" bên Admin (admin/content/index.blade.php) ══════ --}}
    <div class="bg-white rounded-3xl border border-sky-100 p-4 mb-4 space-y-3">
        {{-- Hàng chip: nhìn phát biết kho đang có bao nhiêu câu mỗi môn, bấm 1 phát lọc luôn. --}}
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('teacher.questions.index', $scopeParams + $carry) }}"
               class="px-3 py-1.5 rounded-full border text-xs font-medium transition {{ ! ($filters['subject'] ?? null) ? 'border-blue-600 bg-blue-600 text-white' : 'border-sky-100 text-slate-600 hover:border-blue-200 hover:text-blue-600' }}">
                Tất cả môn
            </a>
            @foreach ($subjectOptions as $code => $label)
                @php $count = $subjectCounts[$code] ?? 0; @endphp
                @if ($count > 0 || ($filters['subject'] ?? null) === $code)
                    <a href="{{ route('teacher.questions.index', $scopeParams + ['subject' => $code] + $carry) }}"
                       class="px-3 py-1.5 rounded-full border text-xs font-medium transition {{ ($filters['subject'] ?? null) === $code ? 'border-blue-600 bg-blue-600 text-white' : 'border-sky-100 text-slate-600 hover:border-blue-200 hover:text-blue-600' }}">
                        {{ $label }} <span class="opacity-70">({{ $count }})</span>
                    </a>
                @endif
            @endforeach
            @if (($subjectCounts[''] ?? 0) > 0 || ($filters['subject'] ?? null) === 'none')
                {{-- Nhóm "Chưa phân loại" (subject IS NULL) — chỗ để dọn dần câu cũ. --}}
                <a href="{{ route('teacher.questions.index', $scopeParams + ['subject' => 'none'] + $carry) }}"
                   class="px-3 py-1.5 rounded-full border text-xs font-medium transition {{ ($filters['subject'] ?? null) === 'none' ? 'bg-amber-500 border-amber-500 text-white' : 'border-amber-200 bg-amber-50 text-amber-700 hover:border-amber-400' }}">
                    Chưa phân loại <span class="opacity-70">({{ $subjectCounts[''] ?? 0 }})</span>
                </a>
            @endif
        </div>

        <form method="GET" action="{{ route('teacher.questions.index') }}" class="flex flex-wrap items-end gap-3 pt-3 border-t border-slate-100">
            @if ($isShared)
                <input type="hidden" name="tab" value="shared">
            @endif
            {{-- CỐ Ý không gửi 'tab' ở kho riêng: ô "Trạng thái" bên dưới CHÍNH LÀ 3 tab Tất cả/
                 Đã phát hành/Nháp, tab active được suy ngược từ trạng thái đang chọn (xem
                 Teacher\QuestionService::listForTeacher()). Gửi cả 2 sẽ thành AND với nhau, chọn
                 tab Nháp + lọc "Phát hành" là luôn ra bảng rỗng — khó hiểu cho người dùng. --}}
            <div class="min-w-[150px]">
                <label class="block text-xs font-medium text-slate-500 mb-1" for="filter-subject">Môn học</label>
                <x-ws.select id="filter-subject" name="subject">
                    <option value="">Tất cả môn</option>
                    @foreach ($subjectOptions as $code => $label)
                        <option value="{{ $code }}" @selected(($filters['subject'] ?? null) === $code)>{{ $label }}</option>
                    @endforeach
                    <option value="none" @selected(($filters['subject'] ?? null) === 'none')>Chưa phân loại</option>
                </x-ws.select>
            </div>
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
            <div class="min-w-[150px]">
                <label class="block text-xs font-medium text-slate-500 mb-1" for="filter-type">Dạng câu</label>
                <x-ws.select id="filter-type" name="type">
                    <option value="">Tất cả dạng</option>
                    @foreach ($questionTypeOptions as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['type'] ?? null) === $value)>{{ $label }}</option>
                    @endforeach
                </x-ws.select>
            </div>
            <div class="min-w-[140px]">
                <label class="block text-xs font-medium text-slate-500 mb-1" for="filter-status">Trạng thái</label>
                <x-ws.select id="filter-status" name="status">
                    <option value="">Tất cả trạng thái</option>
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? null) === $value)>{{ $label }}</option>
                    @endforeach
                </x-ws.select>
            </div>
            {{-- Câu CHƯA đặt độ khó vẫn lọc ra đúng mức vì hệ thống suy theo điểm (giống hệt chỗ
                 hiển thị ngoài trang Luyện tập) — xem App\Support\QuestionDifficulty. --}}
            <div class="min-w-[150px]">
                <label class="block text-xs font-medium text-slate-500 mb-1" for="filter-difficulty">Độ khó</label>
                <x-ws.select id="filter-difficulty" name="difficulty">
                    <option value="">Tất cả độ khó</option>
                    @foreach ($difficultyOptions as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['difficulty'] ?? null) === $value)>{{ $label }}</option>
                    @endforeach
                    <option value="{{ \App\Support\QuestionDifficulty::UNSET }}" @selected(($filters['difficulty'] ?? null) === \App\Support\QuestionDifficulty::UNSET)>Chưa đặt độ khó</option>
                </x-ws.select>
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-medium text-slate-500 mb-1" for="filter-q">Tìm theo tên hoặc mã</label>
                <input id="filter-q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" maxlength="100"
                       placeholder="Ví dụ: ước chung, TOAN6…" class="admin-input">
            </div>
            <button type="submit" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700 shrink-0">Lọc</button>
            @if ($hasActiveFilter)
                <a href="{{ route('teacher.questions.index', $scopeParams) }}" class="px-4 py-2.5 rounded-xl border border-sky-100 text-slate-600 text-[13px] font-medium shrink-0 hover:border-blue-200 hover:text-blue-600 transition">Xoá lọc</a>
            @endif
        </form>
    </div>

    @if (session('status') === 'question-created')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu nháp câu hỏi.'])
    @elseif (session('status') === 'question-updated')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu thay đổi.'])
    @elseif (session('status') === 'question-published')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã phát hành câu hỏi.'])
    @elseif (session('status') === 'question-archived')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu trữ câu hỏi.'])
    @endif

    <x-ws.table :columns="['Tên câu hỏi', 'Môn', 'Khối', 'Loại', 'Độ khó', 'Chủ sở hữu', 'Trạng thái', '']">
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
                <td class="px-4 py-3 text-slate-500">{{ $q['type'] }}</td>
                {{-- Chưa đặt thì hiện mờ + chú thích: giá trị đang được SUY theo điểm câu hỏi,
                     chưa phải do người soạn chọn. --}}
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
            <tr><td colspan="8" class="px-4 py-6 text-center text-slate-400">
                {{ $hasActiveFilter ? 'Không có câu hỏi nào khớp bộ lọc — thử bỏ bớt điều kiện hoặc bấm "Xoá lọc".' : ($isShared ? 'Kho chung chưa có câu hỏi nào.' : 'Chưa có câu hỏi nào trong kho của bạn.') }}
            </td></tr>
        @endforelse
    </x-ws.table>
    <x-ws.pagination-note :shown="count($questions)" :total="$total" />
@endsection
