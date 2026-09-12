@extends('layouts.admin')

@section('title', 'Rà soát đề nhập')
@section('page-title', 'Rà soát đề nhập — Kho chung')

@section('content')
    @php
        $document = $document ?? null;
        $drafts = $drafts ?? [];
        $documentLabel = $document->original_filename ?? 'chưa chọn tệp';
        $isPdf = $document && str_ends_with(strtolower($document->original_filename), '.pdf');
    @endphp

    <a href="{{ route('admin.content.questions.import') }}" class="text-[13px] text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-blue-600">‹ Quay lại Nhập đề</a>

    <x-ws.page-header title="Rà soát: {{ $documentLabel }}" subtitle="Kết quả trích xuất/OCR là bản nháp — phải rà soát và xác nhận trước khi chuyển vào Kho chung (6.4, 6.5)." />

    @if (session('status') === 'draft-added')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã thêm câu thủ công — điền nội dung bên dưới.'])
    @endif
    @if (session('status') === 'draft-promoted-one')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu và chuyển câu này vào Kho chung (dạng Nháp) — vào tab "Kho câu hỏi chung" để phát hành.'])
    @endif
    @if (session('status') === 'draft-saved-pending')
        @include('partials.toast-flash', ['type' => 'warning', 'message' => 'Đã lưu nội dung — chưa chuyển vào Kho chung: '.session('draftPendingReason', 'còn thiếu thông tin.')])
    @endif
    @if (session('status') === 'draft-merged')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã gộp 2 câu — kiểm tra lại nội dung trước khi lưu.'])
    @endif
    @if (session('status') === 'draft-discarded')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã bỏ câu này.'])
    @endif
    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Tệp gốc --}}
        <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
            <h3 class="font-medium text-slate-700 mb-3 flex items-center gap-2"><span><x-lucide name="scroll-text" class="h-4 w-4" /></span> Tệp gốc</h3>
            @if ($document)
                @if ($isPdf)
                    <iframe src="{{ route('admin.content.documents.download', $document->id) }}"
                            class="w-full aspect-[3/4] rounded-xl border border-slate-100"></iframe>
                @else
                    <div class="aspect-[3/4] bg-slate-50 rounded-xl flex flex-col items-center justify-center text-slate-400 text-[13px] gap-3">
                        <span class="text-4xl"><x-lucide name="pen-line" class="h-4 w-4" /></span>
                        <p>Không xem trước được file Word trực tiếp trên trình duyệt.</p>
                    </div>
                @endif
                <a href="{{ route('admin.content.documents.download', $document->id) }}"
                   class="mt-3 inline-flex items-center gap-1.5 text-[13px] text-blue-600 font-medium">⬇ Tải xuống tệp gốc để đối chiếu</a>
            @else
                <div class="aspect-[3/4] bg-slate-50 rounded-xl flex items-center justify-center text-slate-300 text-[13px]">
                    Chưa chọn tệp — vào "Nhập đề" và chọn 1 tệp "Cần rà soát".
                </div>
            @endif
        </div>

        {{-- Danh sách câu tách --}}
        <div class="space-y-3">
            @forelse ($drafts as $d)
                <div class="bg-white rounded-3xl border {{ $d['flagged'] ? 'border-amber-200' : 'border-sky-100' }} p-4"
                     x-data="{ type: '{{ old('type_guess', $d['type'] ?? 'mcq') }}' }">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-medium text-slate-500">Câu {{ $d['no'] }}</span>
                        <div class="flex items-center gap-2">
                            @if ($d['promoted'])
                                <x-ws.badge tone="success">✓ Đã vào Kho chung</x-ws.badge>
                            @endif
                            <x-ws.badge :tone="$d['tone']">Độ tin cậy: {{ $d['confidence'] }}</x-ws.badge>
                        </div>
                    </div>

                    @if ($d['flagged'])
                        <p class="text-xs text-amber-700 bg-amber-50 rounded-xl px-3 py-2 mb-3 flex items-start gap-1.5">
                            <span><x-lucide name="alert-triangle" class="h-4 w-4" /></span> Vùng nhận dạng kém hoặc chưa xác định dạng câu — vui lòng kiểm tra kỹ nội dung/đáp án trước khi lưu.
                        </p>
                    @endif

                    <form method="POST" action="{{ route('admin.content.drafts.update', $d['id']) }}" class="space-y-3">
                        @csrf
                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1">Dạng câu hỏi</label>
                            <select name="type_guess" x-model="type" class="admin-input">
                                <option value="mcq">Trắc nghiệm</option>
                                <option value="fill_blank">Điền đáp án</option>
                                <option value="coding">Lập trình</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1">Tiêu đề</label>
                            <input type="text" name="title" maxlength="255" value="{{ old('title', $d['title']) }}"
                                   class="admin-input" required>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1">Nội dung đề bài</label>
                            <textarea name="body" rows="3" class="admin-input" required>{{ old('body', $d['body']) }}</textarea>
                        </div>

                        <div x-show="type === 'mcq'" x-cloak class="space-y-2">
                            @foreach (['A', 'B', 'C', 'D'] as $i => $letter)
                                <div class="flex items-center gap-2">
                                    <input type="radio" name="correct_option" value="{{ $letter }}"
                                           {{ old('correct_option', $d['correctOption']) === $letter ? 'checked' : '' }}
                                           class="shrink-0">
                                    <span class="text-xs font-medium text-slate-400 w-4">{{ $letter }}</span>
                                    <input type="text" name="options[{{ $i }}]" maxlength="500"
                                           value="{{ old('options.'.$i, $d['options'][$i] ?? '') }}"
                                           class="flex-1 rounded-xl border border-sky-100 text-[13px] p-2" placeholder="Phương án {{ $letter }}">
                                </div>
                            @endforeach
                        </div>

                        <div x-show="type === 'fill_blank'" x-cloak class="space-y-2">
                            <div>
                                <label class="block text-xs font-medium text-slate-500 mb-1">Đáp án chấp nhận (cách nhau dấu phẩy)</label>
                                <input type="text" name="accepted_answers" maxlength="1000"
                                       value="{{ old('accepted_answers', $d['acceptedAnswers']) }}"
                                       class="admin-input">
                            </div>
                            <label class="flex items-center gap-2 text-xs text-slate-500">
                                <input type="checkbox" name="case_sensitive" value="1" {{ old('case_sensitive', $d['caseSensitive']) ? 'checked' : '' }}>
                                Phân biệt hoa/thường
                            </label>
                        </div>

                        <div x-show="type === 'coding'" x-cloak class="space-y-2">
                            <div>
                                <label class="block text-xs font-medium text-slate-500 mb-1">Test case (mỗi dòng: input=>output)</label>
                                <textarea name="test_cases" rows="3" class="admin-input font-mono">{{ old('test_cases', $d['testCases']) }}</textarea>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-1">Giới hạn thời gian (ms)</label>
                                    <input type="number" name="time_limit_ms" min="100" max="60000"
                                           value="{{ old('time_limit_ms', $d['timeLimitMs']) }}" class="admin-input">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-1">Giới hạn bộ nhớ (MB)</label>
                                    <input type="number" name="memory_limit_mb" min="16" max="2048"
                                           value="{{ old('memory_limit_mb', $d['memoryLimitMb']) }}" class="admin-input">
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1">Điểm</label>
                            <input type="number" name="points" min="1" max="100" value="{{ old('points', $d['points']) }}"
                                   class="w-32 rounded-xl border border-sky-100 text-[13px] p-2">
                        </div>

                        <button type="submit" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700">
                            {{ $d['promoted'] ? 'Cập nhật câu trong Kho chung' : 'Lưu câu này' }}
                        </button>
                        @if (! $d['promoted'])
                            <p class="text-xs text-slate-400 mt-1">Đủ điều kiện sẽ tự chuyển vào Kho chung ngay khi lưu.</p>
                        @endif
                    </form>

                    @if (! $d['promoted'])
                        <div class="flex items-center gap-3 text-[13px] mt-3 pt-3 border-t border-slate-100">
                            @if (count($d['otherDrafts']) > 0)
                                <form method="POST" action="{{ route('admin.content.drafts.merge', $d['id']) }}" class="flex items-center gap-2">
                                    @csrf
                                    <select name="merge_with_id" class="rounded-xl border border-sky-100 text-xs p-1.5">
                                        @foreach ($d['otherDrafts'] as $other)
                                            <option value="{{ $other['id'] }}">Gộp với {{ $other['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="text-slate-600 text-[13px]">Gộp</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('admin.content.drafts.discard', $d['id']) }}"
                                  onsubmit="return confirm('Bỏ câu này khỏi danh sách rà soát?');">
                                @csrf
                                <button type="submit" class="text-blue-500 text-[13px]">Xóa</button>
                            </form>
                        </div>
                    @else
                        <p class="text-xs text-slate-400 mt-3 pt-3 border-t border-slate-100">Câu đã ở trong Kho chung — vào "Kho câu hỏi chung" để gộp/xóa/phát hành.</p>
                    @endif
                </div>
            @empty
                <x-ws.empty-state title="Không có câu nào cần rà soát" description="Chọn một tệp đang 'Cần rà soát' từ trang Nhập đề." />
            @endforelse

            @if ($document)
                <form method="POST" action="{{ route('admin.content.drafts.store', $document->id) }}">
                    @csrf
                    <button type="submit" class="w-full rounded-3xl border-2 border-dashed border-sky-100 text-slate-400 text-[13px] py-3 hover:border-blue-300 hover:text-blue-500">
                        + Thêm câu thủ công
                    </button>
                </form>
            @endif
        </div>
    </div>

    @if ($document)
        <div class="rounded-3xl bg-slate-50 border border-slate-100 p-4 mt-6">
            <p class="text-[13px] text-slate-500 flex items-center gap-2">
                <span><x-lucide name="info" class="h-4 w-4" /></span> Mỗi câu tự chuyển vào Kho chung (dạng Nháp) ngay khi bấm "Lưu câu này" và đủ điều kiện — không cần bước gộp riêng.
            </p>
        </div>
    @endif
@endsection
