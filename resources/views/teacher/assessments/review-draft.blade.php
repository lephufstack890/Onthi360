@extends('layouts.teacher')

@section('title', 'Rà soát đề nhập')
@section('page-title', 'Rà soát đề nhập')

@section('content')
    @php
        $document = $document ?? null;
        $drafts = $drafts ?? [];
        $documentLabel = $document->original_filename ?? 'chưa chọn tệp';
        $isPdf = $document && str_ends_with(strtolower($document->original_filename), '.pdf');
    @endphp

    <a href="{{ route('teacher.assessments.import') }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại Nhập đề</a>

    <x-page-header title="Rà soát: {{ $documentLabel }}" subtitle="Kết quả trích xuất/OCR là bản nháp — phải rà soát và xác nhận trước khi chuyển vào kho (6.4)." />

    @if (session('status') === 'draft-added')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã thêm câu thủ công — điền nội dung bên dưới.'])
    @endif
    @if (session('status') === 'draft-promoted-one')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu và chuyển câu này vào kho câu hỏi (dạng Nháp) — vào Kho câu hỏi để phát hành.'])
    @endif
    @if (session('status') === 'draft-saved-pending')
        @include('partials.toast-flash', ['type' => 'warning', 'message' => 'Đã lưu nội dung — chưa chuyển vào kho câu hỏi: '.session('draftPendingReason', 'còn thiếu thông tin.')])
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
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <h3 class="font-medium text-slate-700 mb-3 flex items-center gap-2"><span>📄</span> Tệp gốc</h3>
            @if ($document)
                @if ($isPdf)
                    <iframe src="{{ route('teacher.assessments.documents.download', $document->id) }}"
                            class="w-full aspect-[3/4] rounded-xl border border-slate-100"></iframe>
                @else
                    <div class="aspect-[3/4] bg-slate-50 rounded-xl flex flex-col items-center justify-center text-slate-400 text-sm gap-3">
                        <span class="text-4xl">📝</span>
                        <p>Không xem trước được file Word trực tiếp trên trình duyệt.</p>
                    </div>
                @endif
                <a href="{{ route('teacher.assessments.documents.download', $document->id) }}"
                   class="mt-3 inline-flex items-center gap-1.5 text-sm text-rose-600 font-medium">⬇ Tải xuống tệp gốc để đối chiếu</a>
            @else
                <div class="aspect-[3/4] bg-slate-50 rounded-xl flex items-center justify-center text-slate-300 text-sm">
                    Chưa chọn tệp — vào "Nhập đề" và chọn 1 tệp "Cần rà soát".
                </div>
            @endif
        </div>

        {{-- Danh sách câu tách --}}
        <div class="space-y-3">
            @forelse ($drafts as $d)
                <div class="bg-white rounded-2xl border {{ $d['flagged'] ? 'border-amber-200' : 'border-slate-200' }} p-4"
                     x-data="{ type: '{{ old('type_guess', $d['type'] ?? 'mcq') }}' }">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-medium text-slate-500">Câu {{ $d['no'] }}</span>
                        <div class="flex items-center gap-2">
                            @if ($d['promoted'])
                                <x-status-badge tone="success">✓ Đã vào kho câu hỏi</x-status-badge>
                            @endif
                            <x-status-badge :tone="$d['tone']">Độ tin cậy: {{ $d['confidence'] }}</x-status-badge>
                        </div>
                    </div>

                    @if ($d['flagged'])
                        <p class="text-xs text-amber-700 bg-amber-50 rounded-lg px-3 py-2 mb-3 flex items-start gap-1.5">
                            <span>⚠</span> Vùng nhận dạng kém hoặc chưa xác định dạng câu — vui lòng kiểm tra kỹ nội dung/đáp án trước khi lưu.
                        </p>
                    @endif

                    <form method="POST" action="{{ route('teacher.assessments.drafts.update', $d['id']) }}" class="space-y-3">
                        @csrf
                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1">Dạng câu hỏi</label>
                            <select name="type_guess" x-model="type" class="w-full rounded-lg border border-slate-200 text-sm p-2">
                                <option value="mcq">Trắc nghiệm</option>
                                <option value="fill_blank">Điền đáp án</option>
                                <option value="coding">Lập trình</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1">Tiêu đề</label>
                            <input type="text" name="title" maxlength="255" value="{{ old('title', $d['title']) }}"
                                   class="w-full rounded-lg border border-slate-200 text-sm p-2" required>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1">Nội dung đề bài</label>
                            <textarea name="body" rows="3" class="w-full rounded-lg border border-slate-200 text-sm p-2" required>{{ old('body', $d['body']) }}</textarea>
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
                                           class="flex-1 rounded-lg border border-slate-200 text-sm p-2" placeholder="Phương án {{ $letter }}">
                                </div>
                            @endforeach
                        </div>

                        <div x-show="type === 'fill_blank'" x-cloak class="space-y-2">
                            <div>
                                <label class="block text-xs font-medium text-slate-500 mb-1">Đáp án chấp nhận (cách nhau dấu phẩy)</label>
                                <input type="text" name="accepted_answers" maxlength="1000"
                                       value="{{ old('accepted_answers', $d['acceptedAnswers']) }}"
                                       class="w-full rounded-lg border border-slate-200 text-sm p-2">
                            </div>
                            <label class="flex items-center gap-2 text-xs text-slate-500">
                                <input type="checkbox" name="case_sensitive" value="1" {{ old('case_sensitive', $d['caseSensitive']) ? 'checked' : '' }}>
                                Phân biệt hoa/thường
                            </label>
                        </div>

                        <div x-show="type === 'coding'" x-cloak class="space-y-2">
                            <div>
                                <label class="block text-xs font-medium text-slate-500 mb-1">Test case (mỗi dòng: input=>output)</label>
                                <textarea name="test_cases" rows="3" class="w-full rounded-lg border border-slate-200 text-sm p-2 font-mono">{{ old('test_cases', $d['testCases']) }}</textarea>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-1">Giới hạn thời gian (ms)</label>
                                    <input type="number" name="time_limit_ms" min="100" max="60000"
                                           value="{{ old('time_limit_ms', $d['timeLimitMs']) }}" class="w-full rounded-lg border border-slate-200 text-sm p-2">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-1">Giới hạn bộ nhớ (MB)</label>
                                    <input type="number" name="memory_limit_mb" min="16" max="2048"
                                           value="{{ old('memory_limit_mb', $d['memoryLimitMb']) }}" class="w-full rounded-lg border border-slate-200 text-sm p-2">
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1">Điểm</label>
                            <input type="number" name="points" min="1" max="100" value="{{ old('points', $d['points']) }}"
                                   class="w-32 rounded-lg border border-slate-200 text-sm p-2">
                        </div>

                        <button type="submit" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">
                            {{ $d['promoted'] ? 'Cập nhật câu trong kho' : 'Lưu câu này' }}
                        </button>
                        @if (! $d['promoted'])
                            <p class="text-xs text-slate-400 mt-1">Đủ điều kiện sẽ tự chuyển vào kho câu hỏi ngay khi lưu.</p>
                        @endif
                    </form>

                    @if (! $d['promoted'])
                        <div class="flex items-center gap-3 text-sm mt-3 pt-3 border-t border-slate-100">
                            @if (count($d['otherDrafts']) > 0)
                                <form method="POST" action="{{ route('teacher.assessments.drafts.merge', $d['id']) }}" class="flex items-center gap-2">
                                    @csrf
                                    <select name="merge_with_id" class="rounded-lg border border-slate-200 text-xs p-1.5">
                                        @foreach ($d['otherDrafts'] as $other)
                                            <option value="{{ $other['id'] }}">Gộp với {{ $other['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="text-slate-600 text-sm">Gộp</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('teacher.assessments.drafts.discard', $d['id']) }}"
                                  onsubmit="return confirm('Bỏ câu này khỏi danh sách rà soát?');">
                                @csrf
                                <button type="submit" class="text-rose-500 text-sm">Xóa</button>
                            </form>
                        </div>
                    @else
                        <p class="text-xs text-slate-400 mt-3 pt-3 border-t border-slate-100">Câu đã ở trong kho câu hỏi — vào "Kho câu hỏi" để gộp/xóa/phát hành.</p>
                    @endif
                </div>
            @empty
                <x-empty-state title="Không có câu nào cần rà soát" description="Chọn một tệp đang 'Cần rà soát' từ trang Nhập đề." />
            @endforelse

            @if ($document)
                <form method="POST" action="{{ route('teacher.assessments.drafts.store', $document->id) }}">
                    @csrf
                    <button type="submit" class="w-full rounded-2xl border-2 border-dashed border-slate-200 text-slate-400 text-sm py-3 hover:border-rose-300 hover:text-rose-500">
                        + Thêm câu thủ công
                    </button>
                </form>
            @endif
        </div>
    </div>

    @if ($document)
        <div class="rounded-2xl bg-slate-50 border border-slate-100 p-4 mt-6">
            <p class="text-sm text-slate-500 flex items-center gap-2">
                <span>ℹ️</span> Mỗi câu tự chuyển vào kho câu hỏi (dạng Nháp) ngay khi bấm "Lưu câu này" và đủ điều kiện — không cần bước gộp riêng.
            </p>
        </div>
    @endif
@endsection
