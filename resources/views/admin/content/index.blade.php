@extends('layouts.admin')

@section('title', 'Kho câu hỏi và đề')
@section('page-title', 'Kho câu hỏi và đề')

@section('content')
    @php
        $tab = $tab ?? 'questions';
        $tabs = $tabs ?? [];
        $rows = $rows ?? [];
        $documents = $documents ?? [];
        $tags = $tags ?? [];
        $total = $total ?? count($rows);
        // SỬA 8/9 (3) ("phân loại kho câu hỏi theo môn") — dữ liệu bộ lọc chỉ có ở tab Câu hỏi,
        // xem ContentService::indexData().
        $isQuestions = $tab === 'questions';
        $filters = $filters ?? [];
        $subjectOptions = $subjectOptions ?? [];
        $gradeOptions = $gradeOptions ?? [];
        $questionTypeOptions = $questionTypeOptions ?? [];
        $statusOptions = $statusOptions ?? [];
        $subjectCounts = $subjectCounts ?? [];
        $hasActiveFilter = collect($filters)->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
    @endphp

    <x-page-header title="🗂️ Kho câu hỏi và đề" subtitle="Không sửa âm thầm câu/đề đã có người làm — mọi thay đổi tạo version mới.">
        <x-slot:actions>
            @if ($tab === 'questions')
                <a href="{{ route('admin.content.questions.create') }}" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">+ Tạo câu hỏi</a>
            @elseif ($tab === 'assessments')
                <a href="{{ route('admin.content.assessments.create') }}" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">+ Tạo đề/bộ bài</a>
                {{-- SỬA 19/8 (Giai đoạn 3 — "Bộ đề"): tạo nhiều đề PDF cùng lúc, khác hẳn nút
                     "+ Tạo đề/bộ bài" ở trên (tạo TỪNG đề trống 1 lần). --}}
                {{-- <a href="{{ route('admin.content.assessments.bulk.create') }}" class="px-4 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:border-rose-200 hover:text-rose-600 transition">+ Tải bộ đề (nhiều đề PDF)</a> --}}
            @endif
            {{-- <a href="{{ route('admin.content.questions.import') }}" class="px-4 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:border-rose-200 hover:text-rose-600 transition">+ Nhập đề (Word/PDF/OCR)</a> --}}
        </x-slot:actions>
    </x-page-header>

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
    @elseif (session('status'))
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã cập nhật nội dung.'])
    @endif
    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <x-tabs :tabs="$tabs" />

    @if ($isQuestions)
        <div class="bg-white rounded-2xl border border-slate-200 p-4 mb-4 space-y-3">
            {{-- Hàng chip: nhìn phát biết kho đang có bao nhiêu câu mỗi môn, bấm 1 phát lọc luôn. --}}
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.content.index', ['tab' => 'questions']) }}"
                   class="px-3 py-1.5 rounded-full border text-xs font-medium transition {{ ! ($filters['subject'] ?? null) ? 'bg-rose-600 border-rose-600 text-white' : 'border-slate-200 text-slate-600 hover:border-rose-200 hover:text-rose-600' }}">
                    Tất cả môn
                </a>
                @foreach ($subjectOptions as $code => $label)
                    @php $count = $subjectCounts[$code] ?? 0; @endphp
                    @if ($count > 0 || ($filters['subject'] ?? null) === $code)
                        <a href="{{ route('admin.content.index', array_filter(['tab' => 'questions', 'subject' => $code, 'grade' => $filters['grade'] ?? null, 'type' => $filters['type'] ?? null, 'status' => $filters['status'] ?? null, 'q' => $filters['q'] ?? null])) }}"
                           class="px-3 py-1.5 rounded-full border text-xs font-medium transition {{ ($filters['subject'] ?? null) === $code ? 'bg-rose-600 border-rose-600 text-white' : 'border-slate-200 text-slate-600 hover:border-rose-200 hover:text-rose-600' }}">
                            {{ $label }} <span class="opacity-70">({{ $count }})</span>
                        </a>
                    @endif
                @endforeach
                @if (($subjectCounts[''] ?? 0) > 0 || ($filters['subject'] ?? null) === 'none')
                    {{-- Nhóm "Chưa phân loại" (subject IS NULL) — chỗ để dọn dần câu cũ, xem lệnh
                         `php artisan questions:backfill-subject --all`. --}}
                    <a href="{{ route('admin.content.index', array_filter(['tab' => 'questions', 'subject' => 'none', 'grade' => $filters['grade'] ?? null, 'type' => $filters['type'] ?? null, 'status' => $filters['status'] ?? null, 'q' => $filters['q'] ?? null])) }}"
                       class="px-3 py-1.5 rounded-full border text-xs font-medium transition {{ ($filters['subject'] ?? null) === 'none' ? 'bg-amber-500 border-amber-500 text-white' : 'border-amber-200 bg-amber-50 text-amber-700 hover:border-amber-400' }}">
                        Chưa phân loại <span class="opacity-70">({{ $subjectCounts[''] ?? 0 }})</span>
                    </a>
                @endif
            </div>

            <form method="GET" action="{{ route('admin.content.index') }}" class="flex flex-wrap items-end gap-3 pt-3 border-t border-slate-100">
                <input type="hidden" name="tab" value="questions">
                <div class="min-w-[150px]">
                    <label class="block text-xs font-medium text-slate-500 mb-1" for="filter-subject">Môn học</label>
                    <x-select id="filter-subject" name="subject">
                        <option value="">Tất cả môn</option>
                        @foreach ($subjectOptions as $code => $label)
                            <option value="{{ $code }}" @selected(($filters['subject'] ?? null) === $code)>{{ $label }}</option>
                        @endforeach
                        <option value="none" @selected(($filters['subject'] ?? null) === 'none')>Chưa phân loại</option>
                    </x-select>
                </div>
                <div class="min-w-[120px]">
                    <label class="block text-xs font-medium text-slate-500 mb-1" for="filter-grade">Khối lớp</label>
                    <x-select id="filter-grade" name="grade">
                        <option value="">Tất cả khối</option>
                        @foreach ($gradeOptions as $g)
                            <option value="{{ $g }}" @selected((string) ($filters['grade'] ?? '') === (string) $g)>Lớp {{ $g }}</option>
                        @endforeach
                        <option value="none" @selected(($filters['grade'] ?? null) === 'none')>Chưa gán khối</option>
                    </x-select>
                </div>
                <div class="min-w-[150px]">
                    <label class="block text-xs font-medium text-slate-500 mb-1" for="filter-type">Dạng câu</label>
                    <x-select id="filter-type" name="type">
                        <option value="">Tất cả dạng</option>
                        @foreach ($questionTypeOptions as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['type'] ?? null) === $value)>{{ $label }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div class="min-w-[140px]">
                    <label class="block text-xs font-medium text-slate-500 mb-1" for="filter-status">Trạng thái</label>
                    <x-select id="filter-status" name="status">
                        <option value="">Tất cả trạng thái</option>
                        @foreach ($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? null) === $value)>{{ $label }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs font-medium text-slate-500 mb-1" for="filter-q">Tìm theo tên hoặc mã</label>
                    <input id="filter-q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" maxlength="100"
                           placeholder="Ví dụ: ước chung, TOAN6…"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                </div>
                <button type="submit" class="px-4 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium shrink-0">Lọc</button>
                @if ($hasActiveFilter)
                    <a href="{{ route('admin.content.index', ['tab' => 'questions']) }}" class="px-4 py-2.5 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium shrink-0 hover:border-rose-200 hover:text-rose-600 transition">Xoá lọc</a>
                @endif
            </form>
        </div>
    @endif

    @if ($tab === 'drafts')
        <div class="space-y-3">
            @forelse ($documents as $d)
                <div class="bg-white rounded-2xl border border-slate-200 p-4">
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <x-icon-tile emoji="📄" tone="sky" />
                            <div>
                                <p class="text-sm font-medium text-slate-700">{{ $d['name'] }}</p>
                                <p class="text-xs text-slate-400">Người tải lên: {{ $d['uploader'] }}</p>
                                <div class="w-48 mt-1"><x-progress-bar :percent="$d['progress']" tone="{{ $d['tone'] === 'warning' ? 'warning' : ($d['tone'] === 'danger' ? 'danger' : 'info') }}" /></div>
                            </div>
                        </div>
                        <div class="text-right">
                            <x-status-badge :tone="$d['tone']">{{ $d['status'] }}</x-status-badge>
                            @if ($d['reviewable'])
                                <a href="{{ route('admin.content.questions.reviewDraft', ['document' => $d['id']]) }}" class="block mt-1 text-sm text-rose-600 font-medium">Rà soát ngay ›</a>
                            @endif
                        </div>
                    </div>
                    @if ($d['errorLog'])
                        <p class="text-xs text-rose-600 bg-rose-50 rounded-lg px-3 py-2 mt-3">⚠ {{ $d['errorLog'] }}</p>
                    @endif
                </div>
            @empty
                <x-empty-state
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
        <div class="bg-white rounded-2xl border border-slate-200 p-5 mb-5">
            <h2 class="font-medium text-slate-700 mb-3">+ Thêm tag mới</h2>
            <form method="POST" action="{{ route('admin.content.tags.store') }}" class="flex flex-wrap items-center gap-3">
                @csrf
                <input type="text" name="name" required maxlength="120" placeholder="VD: Đại số, Hình học, Dao động cơ..."
                       class="flex-1 min-w-[220px] rounded-lg border border-slate-200 text-sm p-2.5">
                <button type="submit" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">Thêm tag</button>
            </form>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 divide-y divide-slate-100">
            @forelse ($tags as $t)
                <div class="flex items-center justify-between gap-3 px-5 py-3" x-data="{ editing: false }">
                    <form method="POST" action="{{ route('admin.content.tags.update', $t['id']) }}" class="flex-1 flex items-center gap-2" x-show="editing" x-cloak>
                        @csrf
                        @method('PUT')
                        <input type="text" name="name" value="{{ $t['name'] }}" required maxlength="120" class="flex-1 rounded-lg border border-slate-200 text-sm p-2">
                        <button type="submit" class="text-sm text-rose-600 font-medium">Lưu</button>
                        <button type="button" @click="editing = false" class="text-sm text-slate-400">Huỷ</button>
                    </form>
                    <div class="flex-1 flex items-center gap-2" x-show="!editing">
                        <span class="text-sm font-medium text-slate-700">{{ $t['name'] }}</span>
                        <span class="text-xs text-slate-400">{{ $t['questionsCount'] }} câu hỏi đang dùng</span>
                    </div>
                    <div class="flex items-center gap-3 shrink-0" x-show="!editing">
                        <button type="button" @click="editing = true" class="text-sm text-slate-500 hover:text-rose-600">Đổi tên</button>
                        <form method="POST" action="{{ route('admin.content.tags.destroy', $t['id']) }}" class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-sm text-rose-500 hover:text-rose-700">Xoá</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="px-5 py-6 text-center text-slate-400 text-sm">Chưa có tag nào — thêm tag đầu tiên ở trên.</div>
            @endforelse
        </div>
    @else
        {{-- SỬA 8/9 (3) — tab Câu hỏi có thêm 2 cột Môn/Khối (và mã câu hỏi dưới tên) để nhìn
             bảng là biết ngay câu nào chưa phân loại; các tab khác giữ nguyên bộ cột cũ. --}}
        <x-data-table :columns="$isQuestions ? ['Tên', 'Môn', 'Khối', 'Loại', 'Chủ sở hữu', 'Trạng thái', ''] : ['Tên', 'Loại', 'Chủ sở hữu', 'Trạng thái', '']">
            @forelse ($rows as $r)
                <tr>
                    <td class="px-4 py-3 font-medium text-slate-700">
                        {{ $r['title'] }}
                        @if ($isQuestions && ! empty($r['code']))
                            <div class="text-xs font-normal text-slate-400">{{ $r['code'] }}</div>
                        @endif
                    </td>
                    @if ($isQuestions)
                        <td class="px-4 py-3">
                            @if (($r['subject'] ?? '') === 'Chưa phân loại')
                                <span class="text-xs px-2 py-1 rounded-full bg-amber-50 text-amber-700 border border-amber-200">Chưa phân loại</span>
                            @else
                                <span class="text-slate-600">{{ $r['subject'] }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-500 whitespace-nowrap">{{ $r['grade'] }}</td>
                    @endif
                    <td class="px-4 py-3 text-slate-500">{{ $r['type'] }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ $r['owner'] }}</td>
                    <td class="px-4 py-3"><x-status-badge :tone="$r['tone']">{{ $r['status'] }}</x-status-badge></td>
                    <td class="px-4 py-3 text-right space-x-3 whitespace-nowrap">
                        <a href="{{ route('admin.content.show', $r['id']) }}" class="text-rose-600 font-medium">Xem</a>
                        {{-- SỬA 19/8 (Giai đoạn 4): chỉ đề của giáo viên (tab "Đề/bộ bài") mới có nút
                             này — xem ContentService::indexData()/assessmentPromoteToShared(). --}}
                        @if ($r['canPromoteToShared'] ?? false)
                            <form method="POST" action="{{ route('admin.content.assessments.promoteShared', $r['id']) }}" class="inline">
                                @csrf
                                <button type="submit" class="text-emerald-600 font-medium">Duyệt vào kho chung</button>
                            </form>
                        @endif
                        {{-- SỬA 25/8 (7) — "thêm tính năng xóa cho admin": chỉ tab Học liệu có nút
                             này (canDelete chỉ được set ở nhánh materials của indexData()) — xóa
                             THẬT, xóa luôn file PDF + bài con, không thể khôi phục nên PHẢI xác
                             nhận qua confirm() trước khi submit. --}}
                        @if ($r['canDelete'] ?? false)
                            <form method="POST" action="{{ route('admin.content.materials.destroy', $r['id']) }}" class="inline" onsubmit="return confirm('Xoá vĩnh viễn học liệu này cùng toàn bộ bài con và file PDF liên quan? Không thể khôi phục.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-rose-500 hover:text-rose-700 font-medium">Xoá</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ $isQuestions ? 7 : 5 }}" class="px-4 py-6 text-center text-slate-400">
                    {{ $isQuestions && $hasActiveFilter ? 'Không có câu hỏi nào khớp bộ lọc — thử bỏ bớt điều kiện hoặc bấm "Xoá lọc".' : 'Chưa có dữ liệu.' }}
                </td></tr>
            @endforelse
        </x-data-table>
        <x-pagination-note :shown="count($rows)" :total="$total" />
    @endif
@endsection
